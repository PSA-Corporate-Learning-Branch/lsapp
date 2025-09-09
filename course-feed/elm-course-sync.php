<?php
opcache_reset();
require('../inc/lsapp.php');
if (canAccess()):

function getCoursesFromCSV($filepath, $isActiveFilter = false, $itemCodeIndex = 0) {
    $courses = [];
    if (($handle = fopen($filepath, 'r')) !== false) {
        fgetcsv($handle); // Skip header row
        while ($row = fgetcsv($handle)) {
            $itemCode = strtoupper(trim($row[$itemCodeIndex]));
            $row[17] = sanitizeText($row[17] ?? ''); // Clean CourseAbstract
            if (!$isActiveFilter || $row[1] === 'Active' || $row[1] === 'Inactive') {
                $courses[$itemCode] = $row;
            }
        }
        fclose($handle);
    }
    return $courses;
}

function updateCourse($existingCourse, $newCourseData, &$logEntries) {
    $updatedCourse = $existingCourse;

    $fieldMappings = [
        2  => 1,  // CourseName (ELM index 1 -> LSApp index 2)
        16 => 2,  // CourseDescription (ELM index 2 -> LSApp index 16)
        50 => 11, // ELMCourseID (ELM index 11 -> LSApp index 50)
        19 => 12, // Keywords (ELM index 12 -> LSApp index 19)
        21 => 3,  // Method (ELM index 3 -> LSApp index 21)
        36 => 10, // LearningHubPartner (ELM index 10 -> LSApp index 36)
        38 => 15, // Topics (ELM index 15 -> LSApp index 38)
        39 => 14, // Audience (ELM index 14 -> LSApp index 39)
        40 => 13, // Levels (Group) (ELM index 13 -> LSApp index 40)
    ];

    $changes = [];

    foreach ($fieldMappings as $lsappIndex => $elmIndex) {
        $existingValue = sanitizeText(trim($existingCourse[$lsappIndex] ?? ''));
        $newValue = sanitizeText(trim($newCourseData[$elmIndex] ?? ''));

        // Special handling for LearningHubPartner field (index 36)
        if ($lsappIndex === 36) {
            // Convert partner name to ID
            $partnerId = getPartnerIdByName($newValue);
            if ($existingValue != $partnerId) {
                $updatedCourse[$lsappIndex] = $partnerId;
                $changes[] = "Updated LearningHubPartner to ID {$partnerId} (from name '{$newValue}')";
                $updatedCourse[51] = date('Y-m-d\TH:i:s');
            }
        } else {
            if ($existingValue !== $newValue) {
                $updatedCourse[$lsappIndex] = $newValue;
                $changes[] = "Updated field index $lsappIndex to '{$newValue}'";
                $updatedCourse[51] = date('Y-m-d\TH:i:s'); // Update modified timestamp only if there's a change
            }
        }
    }

    if (trim($existingCourse[1]) === 'Inactive') {
        $updatedCourse[1] = 'Active';
        $changes[] = "Updated status to 'Active'";
    } // we don't do the inverse action to make active courses inactive because of the flow
      // of operations here, where LSApp can be the first point of creation for a new course
      // request before it's actually entered in ELM. We need new courses to be "Active" before
      // they are available for registration, so if we just make everything that's active 
      // but not included in the hub inactive, then we loose the ability 

    
    if (trim($existingCourse[52]) !== 'PSA Learning System') {
        $updatedCourse[52] = 'PSA Learning System';
        $changes[] = "Updated Platform to 'PSA Learning System'";
    }

    // If course is found in ELM feed, always set HUBInclude to 'Yes' regardless of current state
    $hubIncludePersist = isset($existingCourse[59]) ? $existingCourse[59] : 'no';
    $currentHubInclude = trim($existingCourse[53]);
    
    // Always ensure HUBInclude is 'Yes' for courses in ELM feed
    if ($currentHubInclude !== 'Yes') {
        $updatedCourse[53] = 'Yes';
        if ($currentHubInclude === 'No') {
            $changes[] = "Restored HUBInclude to 'Yes' - course found in ELM feed (was previously 'No')";
        } else {
            $changes[] = "Updated HUBInclude to 'Yes' - course found in ELM feed";
        }
    }
    
    // For persistent courses that are back in the feed, set state to 'active'
    if ($hubIncludePersist === 'yes' && isset($existingCourse[61]) && $existingCourse[61] === 'inactive') {
        $updatedCourse[61] = 'active';
        $changes[] = "Updated HubIncludePersistState to 'active' - course is back in ELM feed";
    }

    if ($changes) {
        $logEntries[] = "Updated course '{$existingCourse[2]}' (Item Code: {$existingCourse[4]}) with changes:\n  " . implode("\n  ", $changes);
    }

    return $updatedCourse;
}

// Paths to course data and log files
$coursesPath = build_path(BASE_DIR, 'data', 'courses.csv');
$hubCoursesPath = build_path(BASE_DIR, 'course-feed', 'data', 'courses.csv');
$timestamp = date('YmdHis');
$isoDateTime = date('c'); // ISO 8601 date format for "elm_sync_log.txt"
$logEntries = [];
$logFilePath = build_path(BASE_DIR, 'data', 'course-sync-logs', "course-sync-log-$timestamp.txt");
$persistentLogPath = build_path(BASE_DIR, 'data', 'course-sync-logs', 'elm_sync_log.txt');

$lsappCourses = getCoursesFromCSV($coursesPath, false, 4);
$hubCourses = getCoursesFromCSV($hubCoursesPath, false, 0);

$updatedCourses = [];
$count = 0;
foreach ($hubCourses as $hcCode => $hc) {
    if (isset($lsappCourses[$hcCode])) {
        $updatedCourse = updateCourse($lsappCourses[$hcCode], $hc, $logEntries);
        $itemCode = $updatedCourse[4];
        $updatedCourses[$itemCode] = $updatedCourse;
    } else {
        $courseId = $timestamp . '-' . ++$count;
        $slug = createSlug($hc[1]);
        $newCourse = [
            $courseId,
            'Active',
            h($hc[1] ?? ''),        // CourseName
            '',                     // CourseShort
            h($hc[0] ?? ''),        // ItemCode
            '', '', '', '', '',     // ClassTimes, ClassDays, ELM, PreWork, PostWork
            '',                     // CourseOwner
            '', '',                 // MinMax, CourseNotes,
            $timestamp,             // Requested
            'SYNCBOT',              // RequestedBy
            $timestamp,             // EffectiveDate
            h($hc[2] ?? ''),        // CourseDescription
            '', '',                 // CourseAbstract, Prerequisites
            h($hc[12] ?? ''),       // Keywords
            '',                     // Category
            h($hc[3] ?? ''),        // Method
            '', 'No',               // elearning, WeShip
            '', '', '', '', '',     // ProjectNumber, Responsibility, ServiceLine, STOB, MinEnroll
            '', '', '',             // MaxEnroll, StartTime, EndTime
            '#F1F1F1',              // Color
            1,                      // Featured
            '', '',                 // Developer, EvaluationsLink
            getPartnerIdByName($hc[10] ?? ''), // LearningHubPartner (convert name to ID)
            'No',                   // Alchemer
            h($hc[15] ?? ''),       // Topics
            h($hc[14] ?? ''),       // Audience
            h($hc[13] ?? ''),       // Levels (Group)
            '', '', '', '', '',     // Reporting, PathLAN, PathStaging, PathLive, PathNIK
            '',                     // PathTeams
            0,                      // isMoodle
            0, '',                  // TaxProcessed, TaxProcessedBy
            h($hc[11] ?? ''),       // ELMCourseID
            $timestamp,             // Modified
            'PSA Learning System',  // Platform
            'Yes',                  // HUBInclude
            '',                     // RegistrationLink
            $slug,                  // CourseNameSlug
            '',                     // HubExpirationDate
            0,                     // OpenAccessOptin
            'yes',                 // HubIncludeSync (default: yes)
            'no',                  // HubIncludePersist (default: no)
            'This course is no longer available for registration.', // HubPersistMessage
            'active'               // HubIncludePersistState (default: active)
        ];
        $itemCode = $newCourse[4];
        $updatedCourses[$itemCode] = $newCourse;
        $logEntries[] = "Added new course '{$newCourse[2]}' (Item Code: {$newCourse[4]})";
    }
}

foreach ($lsappCourses as $lsappCode => $lsappCourse) {
    
    if ($lsappCourse[52] === 'PSA Learning System' && !isset($hubCourses[$lsappCode])) {
        // Check if course has HubIncludeSync set to 'no' (index 58)
        $hubIncludeSync = isset($lsappCourse[58]) ? $lsappCourse[58] : 'yes';
        // Check if course has HubIncludePersist set to 'yes' (index 59)
        $hubIncludePersist = isset($lsappCourse[59]) ? $lsappCourse[59] : 'no';
        
        // Only set HUBInclude to 'No' if:
        // 1. HubIncludeSync is not 'no' (meaning it should sync)
        // 2. HubIncludePersist is not 'yes' (meaning it should not persist)
        // 3. It isn't already 'No'
        if ($hubIncludeSync !== 'no' && $hubIncludePersist !== 'yes' && $lsappCourse[53] !== 'No') {
            $lsappCourse[53] = 'No';
            $updatedCourses[$lsappCode] = $lsappCourse;
            $logEntries[] = "Set HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode)";
        } elseif ($hubIncludeSync === 'no') {
            $logEntries[] = "Skipped setting HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode) - HubIncludeSync is 'no'";
        } elseif ($hubIncludePersist === 'yes') {
            // For persistent courses, set HubIncludePersistState to 'inactive' instead of removing from feed
            if (!isset($lsappCourse[61]) || $lsappCourse[61] !== 'inactive') {
                $lsappCourse[61] = 'inactive';
                $updatedCourses[$lsappCode] = $lsappCourse;
                $logEntries[] = "Set HubIncludePersistState to 'inactive' for '{$lsappCourse[2]}' (Class code: $lsappCode) - HubIncludePersist is 'yes'";
            }
        }
    }
}

// Check for expired courses based on HubExpirationDate
$currentDate = date('Y-m-d');
foreach ($lsappCourses as $lsappCode => $lsappCourse) {
    // Check if HubExpirationDate (index 56) is set and has passed
    if (!empty($lsappCourse[56]) && $lsappCourse[56] < $currentDate) {
        // Only update if HUBInclude is not already 'No'
        if ($lsappCourse[53] !== 'No') {
            $lsappCourse[53] = 'No';
            $updatedCourses[$lsappCode] = $lsappCourse;
            $logEntries[] = "Set HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode) - Expired on {$lsappCourse[56]}";
        }
    }
}

// Check if any updates occurred
if (!empty($logEntries)) {
    // Create the timestamped log file only if there are updates
    file_put_contents($logFilePath, implode("\n", $logEntries) . "\n", FILE_APPEND);
    $logEntries[] = "Logged updates to $logFilePath";
}

// Always update the persistent log (prepend latest sync time)
$currentPersistentLog = file_exists($persistentLogPath) ? file_get_contents($persistentLogPath) : '';
$newPersistentLogEntry = "$isoDateTime\n" . $currentPersistentLog;
file_put_contents($persistentLogPath, $newPersistentLogEntry);

$tempFilePath = build_path(BASE_DIR, 'data', 'temp_courses.csv');
$fpTemp = fopen($tempFilePath, 'w');

if ($fpTemp !== false) {
    // Write header to the temporary file
    fputcsv($fpTemp, [
        'CourseID', 'Status', 'CourseName', 'CourseShort', 'ItemCode', 'ClassTimes',
        'ClassDays', 'ELM', 'PreWork', 'PostWork', 'CourseOwner', 'MinMax', 'CourseNotes',
        'Requested', 'RequestedBy', 'EffectiveDate', 'CourseDescription', 'CourseAbstract',
        'Prerequisites', 'Keywords', 'Category', 'Method', 'elearning', 'WeShip', 'ProjectNumber',
        'Responsibility', 'ServiceLine', 'STOB', 'MinEnroll', 'MaxEnroll', 'StartTime', 'EndTime',
        'Color', 'Featured', 'Developer', 'EvaluationsLink', 'LearningHubPartner', 'Alchemer',
        'Topics', 'Audience', 'Levels', 'Reporting', 'PathLAN', 'PathStaging', 'PathLive',
        'PathNIK', 'PathTeams', 'isMoodle', 'TaxProcessed', 'TaxProcessedBy', 'ELMCourseID',
        'Modified', 'Platform', 'HUBInclude', 'RegistrationLink', 'CourseNameSlug', 
        'HubExpirationDate', 'OpenAccessOptin', 'HubIncludeSync', 'HubIncludePersist', 'HubPersistMessage',
        'HubIncludePersistState'
    ]);

    if (($fpOriginal = fopen($coursesPath, 'r')) !== false) {
        fgetcsv($fpOriginal); // Skip header row

        while (($row = fgetcsv($fpOriginal)) !== false) {
            $itemCode = $row[4]; // Assuming ItemCode is at index 4

            // Sanitize CourseAbstract (index 17)
            $row[17] = sanitizeText($row[17] ?? '');

            if (isset($updatedCourses[$itemCode])) {
                $updatedCourses[$itemCode][17] = sanitizeText($updatedCourses[$itemCode][17] ?? '');
                fputcsv($fpTemp, $updatedCourses[$itemCode]);
                unset($updatedCourses[$itemCode]);
            } else {
                fputcsv($fpTemp, $row);
            }
        }
        fclose($fpOriginal);
    }

    foreach ($updatedCourses as $newCourse) {
        $newCourse[17] = sanitizeText($newCourse[17] ?? '');
        fputcsv($fpTemp, $newCourse);
    }

    fclose($fpTemp);

    if (rename($tempFilePath, $coursesPath)) {
        $logEntries[] = "Successfully updated courses.csv with the latest data.";
    } else {
        $logEntries[] = "Failed to replace courses.csv with updated data.";
    }
} else {
    $logEntries[] = "Failed to open temp file for writing at $tempFilePath.";
}
// include($logFilePath);
// echo '<a href="feed-create.php">Proceed to create feed</a>';
header('Location: feed-create.php');
?>
<?php else: ?>
<?php include('templates/noaccess.php') ?>
<?php endif ?>