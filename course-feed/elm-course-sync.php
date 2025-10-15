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

// Build a lookup index by course name for duplicate detection
$lsappCoursesByName = [];
foreach ($lsappCourses as $lsCode => $lsCourse) {
    $normalizedName = strtolower(trim($lsCourse[2])); // CourseName at index 2
    $lsappCoursesByName[$normalizedName] = $lsCode;
}

$updatedCourses = [];
$potentialDuplicates = [];
$newCourses = [];
$count = 0;
foreach ($hubCourses as $hcCode => $hc) {
    if (isset($lsappCourses[$hcCode])) {
        $updatedCourse = updateCourse($lsappCourses[$hcCode], $hc, $logEntries);
        $itemCode = $updatedCourse[4];
        $updatedCourses[$itemCode] = $updatedCourse;
    } else {
        // Check for duplicate by course name before creating new course
        $elmCourseName = strtolower(trim($hc[1])); // ELM CourseName at index 1

        if (isset($lsappCoursesByName[$elmCourseName])) {
            // Found a course with the same name - potential duplicate!
            $matchedItemCode = $lsappCoursesByName[$elmCourseName];
            $matchedCourse = $lsappCourses[$matchedItemCode];

            $potentialDuplicates[] = [
                'elm_item_code' => $hcCode,
                'elm_course_name' => $hc[1],
                'lsapp_course_id' => $matchedCourse[0],
                'lsapp_item_code' => $matchedCourse[4],
                'lsapp_course_name' => $matchedCourse[2]
            ];

            $logEntries[] = "DUPLICATE DETECTED: ELM course '$hcCode - {$hc[1]}' matches existing LSApp course '{$matchedCourse[4]} - {$matchedCourse[2]}' by name. Skipped creation.";

            // Skip creating the new course
            continue;
        }

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

        // Track new course for email notification
        $newCourses[] = [
            'course_id' => $courseId,
            'course_name' => $hc[1],
            'item_code' => $hc[0],
            'method' => $hc[3] ?? '',
            'partner' => $hc[10] ?? ''
        ];
    }
}

foreach ($lsappCourses as $lsappCode => $lsappCourse) {
    
    if ($lsappCourse[52] === 'PSA Learning System' && $lsappCourse[1] === 'Active' && !isset($hubCourses[$lsappCode])) {
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
            // Use CourseID as key to avoid collisions when ItemCode is empty
            $courseId = $lsappCourse[0];
            $updatedCourses[$courseId] = $lsappCourse;
            $logEntries[] = "Set HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode)";
        } elseif ($hubIncludeSync === 'no') {
            $logEntries[] = "Skipped setting HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode) - HubIncludeSync is 'no'";
        } elseif ($hubIncludePersist === 'yes') {
            // For persistent courses, set HubIncludePersistState to 'inactive' instead of removing from feed
            if (!isset($lsappCourse[61]) || $lsappCourse[61] !== 'inactive') {
                $lsappCourse[61] = 'inactive';
                // Use CourseID as key to avoid collisions when ItemCode is empty
                $courseId = $lsappCourse[0];
                $updatedCourses[$courseId] = $lsappCourse;
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
            // Use CourseID as key to avoid collisions when ItemCode is empty
            $courseId = $lsappCourse[0];
            $updatedCourses[$courseId] = $lsappCourse;
            $logEntries[] = "Set HUBInclude to No for '{$lsappCourse[2]}' (Class code: $lsappCode) - Expired on {$lsappCourse[56]}";
        }
    }
}

// Send email notification if duplicates were detected
if (!empty($potentialDuplicates)) {
    require_once(BASE_DIR . '/inc/ches_client.php');

    try {
        $ches = new CHESClient();

        // Build email content
        $duplicateCount = count($potentialDuplicates);
        $subject = "Course Sync Alert: $duplicateCount Potential Duplicate" . ($duplicateCount > 1 ? 's' : '') . " Detected";

        $bodyHtml = "<h2>ELM Course Sync - Duplicate Detection Report</h2>";
        $bodyHtml .= "<p>The course sync process detected <strong>$duplicateCount potential duplicate course" . ($duplicateCount > 1 ? 's' : '') . "</strong>.</p>";
        $bodyHtml .= "<p>These courses were found in the ELM feed but matched existing LSApp courses by name. No new courses were created to prevent duplicates.</p>";
        $bodyHtml .= "<h3>Action Required:</h3>";
        $bodyHtml .= "<p>Please review the following courses and update the LSApp ItemCode if they are the same course:</p>";
        $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
        $bodyHtml .= "<tr style='background-color: #f2f2f2;'>";
        $bodyHtml .= "<th>ELM Item Code</th><th>ELM Course Name</th><th>LSApp Course ID</th><th>LSApp Item Code</th><th>LSApp Course Name</th>";
        $bodyHtml .= "</tr>";

        foreach ($potentialDuplicates as $dup) {
            $bodyHtml .= "<tr>";
            $bodyHtml .= "<td>" . htmlspecialchars($dup['elm_item_code']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($dup['elm_course_name']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($dup['lsapp_course_id']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($dup['lsapp_item_code']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($dup['lsapp_course_name']) . "</td>";
            $bodyHtml .= "</tr>";
        }

        $bodyHtml .= "</table>";
        $bodyHtml .= "<p><strong>Next Steps:</strong></p>";
        $bodyHtml .= "<ol>";
        $bodyHtml .= "<li>Verify if the ELM course and LSApp course are the same</li>";
        $bodyHtml .= "<li>If they are the same, update the ItemCode in LSApp to match the ELM Item Code</li>";
        $bodyHtml .= "<li>The next sync will then update the existing course instead of trying to create a duplicate</li>";
        $bodyHtml .= "</ol>";
        $bodyHtml .= "<p>Sync timestamp: $isoDateTime</p>";

        $bodyText = "ELM Course Sync - Duplicate Detection Report\n\n";
        $bodyText .= "The course sync process detected $duplicateCount potential duplicate course" . ($duplicateCount > 1 ? 's' : '') . ".\n\n";
        $bodyText .= "These courses were found in the ELM feed but matched existing LSApp courses by name.\n\n";

        foreach ($potentialDuplicates as $dup) {
            $bodyText .= "ELM: {$dup['elm_item_code']} - {$dup['elm_course_name']}\n";
            $bodyText .= "LSApp: {$dup['lsapp_course_id']} ({$dup['lsapp_item_code']}) - {$dup['lsapp_course_name']}\n\n";
        }

        $bodyText .= "Please review and update the ItemCode in LSApp if these are the same course.\n";
        $bodyText .= "Sync timestamp: $isoDateTime\n";

        // Send email
        $result = $ches->sendEmail(
            ['Corporatelearning.admin@gov.bc.ca', 'allan.haggett@gov.bc.ca'],
            $subject,
            $bodyText,
            $bodyHtml,
            'lsapp_syncbot_noreply@gov.bc.ca',
            null, // cc
            null, // bcc
            'high' // priority
        );

        $logEntries[] = "Sent duplicate detection email to Corporatelearning.admin@gov.bc.ca (Transaction ID: {$result['txId']})";

    } catch (Exception $e) {
        $logEntries[] = "ERROR: Failed to send duplicate detection email: " . $e->getMessage();
    }
}

// Send email notification if new courses were added
if (!empty($newCourses)) {
    require_once(BASE_DIR . '/inc/ches_client.php');

    try {
        $ches = new CHESClient();

        // Build email content
        $newCourseCount = count($newCourses);
        $subject = "Course Sync: $newCourseCount New Course" . ($newCourseCount > 1 ? 's' : '') . " Added";

        $bodyHtml = "<h2>ELM Course Sync - New Course" . ($newCourseCount > 1 ? 's' : '') . " Added</h2>";
        $bodyHtml .= "<p>The course sync process added <strong>$newCourseCount new course" . ($newCourseCount > 1 ? 's' : '') . "</strong> to LSApp.</p>";
        $bodyHtml .= "<h3>New Courses:</h3>";
        $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
        $bodyHtml .= "<tr style='background-color: #f2f2f2;'>";
        $bodyHtml .= "<th>Course Name</th><th>Item Code</th><th>Method</th><th>Partner</th><th>View Course</th>";
        $bodyHtml .= "</tr>";

        foreach ($newCourses as $course) {
            $courseUrl = "https://gww.bcpublicservice.gov.bc.ca/lsapp/course.php?courseid=" . urlencode($course['course_id']);
            $bodyHtml .= "<tr>";
            $bodyHtml .= "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($course['item_code']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($course['method']) . "</td>";
            $bodyHtml .= "<td>" . htmlspecialchars($course['partner']) . "</td>";
            $bodyHtml .= "<td><a href='" . htmlspecialchars($courseUrl) . "'>View Course</a></td>";
            $bodyHtml .= "</tr>";
        }

        $bodyHtml .= "</table>";
        $bodyHtml .= "<p>Sync timestamp: $isoDateTime</p>";

        $bodyText = "ELM Course Sync - New Course" . ($newCourseCount > 1 ? 's' : '') . " Added\n\n";
        $bodyText .= "The course sync process added $newCourseCount new course" . ($newCourseCount > 1 ? 's' : '') . " to LSApp.\n\n";
        $bodyText .= "New Courses:\n\n";

        foreach ($newCourses as $course) {
            $courseUrl = "https://gww.bcpublicservice.gov.bc.ca/lsapp/course.php?courseid=" . urlencode($course['course_id']);
            $bodyText .= "Course: {$course['course_name']}\n";
            $bodyText .= "Item Code: {$course['item_code']}\n";
            $bodyText .= "Method: {$course['method']}\n";
            $bodyText .= "Partner: {$course['partner']}\n";
            $bodyText .= "View: $courseUrl\n\n";
        }

        $bodyText .= "Sync timestamp: $isoDateTime\n";

        // Send email
        $result = $ches->sendEmail(
            ['allan.haggett@gov.bc.ca'],
            $subject,
            $bodyText,
            $bodyHtml,
            'lsapp_syncbot_noreply@gov.bc.ca'
        );

        $logEntries[] = "Sent new course notification email to Corporatelearning.admin@gov.bc.ca (Transaction ID: {$result['txId']})";

    } catch (Exception $e) {
        $logEntries[] = "ERROR: Failed to send new course notification email: " . $e->getMessage();
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
            $itemCode = $row[4]; // ItemCode at index 4
            $courseId = $row[0]; // CourseID at index 0

            // Sanitize CourseAbstract (index 17)
            $row[17] = sanitizeText($row[17] ?? '');

            // Check both ItemCode and CourseID for updates (to handle both keying strategies)
            if (isset($updatedCourses[$itemCode])) {
                $updatedCourses[$itemCode][17] = sanitizeText($updatedCourses[$itemCode][17] ?? '');
                fputcsv($fpTemp, $updatedCourses[$itemCode]);
                unset($updatedCourses[$itemCode]);
            } elseif (isset($updatedCourses[$courseId])) {
                $updatedCourses[$courseId][17] = sanitizeText($updatedCourses[$courseId][17] ?? '');
                fputcsv($fpTemp, $updatedCourses[$courseId]);
                unset($updatedCourses[$courseId]);
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