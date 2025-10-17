<?php
opcache_reset();
define('SLASH', DIRECTORY_SEPARATOR);
$docroot = 'E:\WebSites\Prod\BCPSA\Intranet\wwwroot\lsapp\\';
define('BASE_DIR', $docroot);
function build_path(...$segments) {
    return implode(SLASH, $segments);
}

// Load all courses into memory once
function loadAllCourses() {
    $path = build_path(BASE_DIR, 'data', 'courses.csv');
    $f = fopen($path, 'r');
    if (!$f) {
        die("Failed to open courses CSV.");
    }
    
    $header = fgetcsv($f); // Skip header row
    $courses = [];
    
    while ($row = fgetcsv($f)) {
        // Index by course ID for quick lookup
        $courses[$row[0]] = $row;
    }
    
    fclose($f);
    return $courses;
}

// Get a specific course from the preloaded array
function getCourse($cid, $coursesArray) {
    return $coursesArray[$cid] ?? '';
}

function getCoursesClassesUpcoming($courseid) {
    $path = build_path(BASE_DIR, 'data', 'classes.csv');
    $f = fopen($path, 'r');
    $list = array();
    $today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format
    while ($row = fgetcsv($f)) {
        $startDate = $row[8]; // StartDate is in YYYY-MM-DD format
        // Include only future classes (today or later)
        if ($row[5] == $courseid && $startDate >= $today) {
            $list[] = $row;
        }
    }
    fclose($f);
    // Sort classes by start date (earliest first)
    usort($list, function($a, $b) {
        return strcmp($a[8], $b[8]);
    });
    return $list;
}

// Load all courses once at the beginning
$allCourses = loadAllCourses();
$allCourseIds = array_keys($allCourses); // Track all course IDs for later cleanup

$accessCodeJson = '../data/open-access-code.json';
$accessCodeData = file_exists($accessCodeJson) ? json_decode(file_get_contents($accessCodeJson), true) : [];
$expectedCode = $accessCodeData[0]['code'] ?? '';

// Process each course from the preloaded array
foreach ($allCourses as $courseid => $course) {
    if (isset($course[57]) && (strtolower(trim($course[57])) === 'true' || strtolower(trim($course[57])) === 'on')) {
        // Course details are already in $course variable, no need to fetch again

        // Extract required course details
        $title = $course[2]; // CourseName
        $description = $course[16]; // CourseDescription
        $preWork = $course[8] ?? '';
        $courseShort = str_replace(' ', '-', strtolower($course[3])); // CourseShort

        // Fetch all upcoming classes
        $upcomingClasses = getCoursesClassesUpcoming($courseid);
        $nextOffering = "<div class='alert alert-warning'>No upcoming offerings.</div>";

        if (!empty($upcomingClasses)) {
            $nextOffering = "<table class='table table-bordered table-striped'>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Enrolment</th>
                                        <th>Webinar Link</th>
                                    </tr>
                                </thead>
                                <tbody>";
        
            $today = date('Y-m-d'); // Get today's date
        
            foreach ($upcomingClasses as $class) {
                if($class[1] === 'Active') {
                    $startDate = $class[8]; // StartDate
                    $startTime = $class[54]; // StartTime
                    $endTime = $class[55]; // EndTime
                    $webinarLink = $class[15]; // Webinar link
                    $currentEnrolment = $class[18]; // current enrolment number
                    $maxEnrolment = $class[12]; // maximum enrolment number
            
                    // Format the date
                    $dateObj = new DateTime($startDate);
                    $formattedDate = $dateObj->format('l, M j Y');
            
                    // Only show webinar link if the event is happening today
                    $webinarCell = "";
                    if ($startDate === $today) {
                        // Determine the help link for the webinar
                        $help = "";
                        if (strpos($webinarLink, 'teams.microsoft.com') !== false) {
                            $help = '<a href="https://aka.ms/JoinTeamsMeeting?omkt=en-US" target="_blank" rel="noopener">Need help?</a>';
                        } elseif (strpos($webinarLink, 'zoom.us') !== false) {
                            $help = ''; // Zoom help TBD
                        }
            
                        $webinarCell = "<a class='btn btn-primary' href='{$webinarLink}' target='_blank' rel='noopener'>Join</a> $help";
                    } else {
                        $webinarCell = "<span class='text-muted'>Available on the day</span>";
                    }
            
                    // Add class row
                    $nextOffering .= "<tr>
                                        <td>{$formattedDate}</td>
                                        <td>{$startTime} - {$endTime}</td>
                                        <td>{$currentEnrolment}/{$maxEnrolment}</td>
                                        <td>{$webinarCell}</td>
                                    </tr>";
                }
            }
        
            $nextOffering .= "</tbody></table>";
        }

        // Directory where the new index.php will be placed
        $directory = 'E:/WebSites/NonSSOLearning/openaccess';

        // Check if directory exists, if not, create it
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Define file paths
        $headerFile = '../templates/openaccess-header.php';
        $footerFile = '../templates/openaccess-footer.php';
        $indexFile = $directory . '/' . $courseShort . '.php';

        // Read header and footer
        ob_start();
        if (file_exists($headerFile)) {
            include $headerFile;
        } else {
            echo "<html><head><title>$title</title></head><body>";
        }
        $headerContent = ob_get_clean();

        ob_start();
        if (file_exists($footerFile)) {
            include $footerFile;
        } else {
            echo "</body></html>";
        }
        $footerContent = ob_get_clean();

        // Content for the index.php file (including the access code check)
        $pageContent = "<?php 
        \$code = \$_GET['accesscode'] ?? ''; 
        if (\$code !== '" . addslashes($expectedCode) . "') {
            die('Sorry, you do not have access.');
        } 
        ?>" . PHP_EOL;

        $pageContent .= $headerContent . "
        <h1>$title</h1>
        <p>$description</p>
        <div><a class=\"btn btn-lg btn-secondary\" href=\"$preWork\" target=\"_blank\" rel=\"noopener\">Pre-work Link</a></div>
        <p>$nextOffering</p>
        " . $footerContent;

        // Write the index.php file
        file_put_contents($indexFile, $pageContent);
    }
}

// Cleanup unpublished courses
// $existingFiles = glob($directory . '/*.php');
// foreach ($existingFiles as $file) {
//     $filename = basename($file, '.php');
//     $match = false;
//     foreach ($allCourseIds as $id) {
//         $courseData = getCourse($id, $allCourses);
//         if ($courseData && isset($courseData[3])) {
//             $short = str_replace(' ', '-', strtolower($courseData[3]));
//             if ($short === $filename) {
//                 if (isset($courseData[57]) && (strtolower(trim($courseData[57])) === 'true' || strtolower(trim($courseData[57])) === 'on')) {
//                     $match = true;
//                     break;
//                 }
//             }
//         }
//     }
//     if (!$match) {
//         unlink($file);
//     }
// }
// Redirect to the requested courses RSS feed generation
//header('Location: requested-courses-rss.php');
exit;