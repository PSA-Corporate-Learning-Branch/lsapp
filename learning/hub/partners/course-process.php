<?php 
opcache_reset();
require('../../lsapp/inc/lsapp.php'); 

$newcourseid = date('YmdHis');
$now = date('Y-m-d\TH:i:s');
$coursecat = '';
$weship = isset($_POST['WeShip']) ? 'Yes' : 'No';
$alchemer = isset($_POST['Alchemer']) ? 'Yes' : 'No';
$slug = createSlug($_POST['CourseName']);
//0-CourseID,1-Status,2-CourseName,3-CourseShort,4-ItemCode,5-ClassTimes,6-ClassDays,7-ELM,8-PreWork,9-PostWork,10-CourseOwner,
//11-MinMax,12-CourseNotes,13-Requested,14-RequestedBy,15-EffectiveDate,16-CourseDescription,17-CourseAbstract,18-Prerequisites,
//19-Keywords,20-Categories,21-Method,22-elearning,23-WeShip,24-ProjectNumber,25-Responsibility,26-ServiceLine,
//27-STOB,28-MinEnroll,29-MaxEnroll,30-StartTime,31-EndTime,32-Color
//33-Featured,34-Developer,35-EvaluationsLink,36-LearningHubPartner,37-Alchemer,
//38-Topics,39-Audience,40-Levels,41-Reporting
//42-PathLAN,43-PathStaging,44-PathLive,45-PathNIK,46-CHEFSFormID
// 47-isMoodle,48-TaxProcessed,49-TaxProcessedBy,50-ELMCourseID,51-Modified
// 52-Platform, 53-HUBInclude, 54-RegistrationLink, 55-CourseNameSlug, 56-HubExpirationDate
$newcourse = [
    $newcourseid, // 0 - CourseID
    h($_POST['Status']), // 1 - Status
    h($_POST['CourseName']), // 2 - CourseName
    '', // 3 - CourseShort
    '', // 4 - ItemCode
    '', // 5 - ClassTimes
    '', // 6 - ClassDays
    '', // 7 - ELM
    '', // 8 - PreWork
    '', // 9 - PostWork
    h($_POST['CourseOwner']), // 10 - CourseOwner
    '', // 11 - MinMax
    '', // 12 - CourseNotes
    $now, // 13 - Requested
    h($_POST['CourseOwner']), // 14 - RequestedBy
    h($_POST['EffectiveDate']), // 15 - EffectiveDate
    h($_POST['coursedesc']), // 16 - CourseDescription
    '', // 17 - CourseAbstract
    '', // 18 - Prerequisites
    h($_POST['Keywords']), // 19 - Keywords
    $coursecat, // 20 - Categories
    h($_POST['Method']), // 21 - Method
    '', // 22 - elearning
    $weship, // 23 - WeShip
    '', // 24 - ProjectNumber
    '', // 25 - Responsibility
    '', // 26 - ServiceLine
    '', // 27 - STOB
    '', // 28 - MinEnroll
    '', // 29 - MaxEnroll
    '', // 30 - StartTime
    '', // 31 - EndTime
    '#F1F1F1', // 32 - Color
    '', // 33 - Featured
    '', // 34 - Developer
    '', // 35 - EvaluationsLink
    h($_POST['LearningHubPartner']), // 36 - LearningHubPartner
    '', // 37 - Alchemer
    h($_POST['Topic']), // 38 - Topics
    h($_POST['Audience']), // 39 - Audience
    h($_POST['Group']), // 40 - Group
    '', // 41 - Reporting
    '', // 42 - PathLAN
    '', // 43 - PathStaging
    '', // 44 - PathLive
    '', // 45 - PathNIK
    '', // 46 - CHEFSFormID
    0, // 47 - isMoodle
    0, // 48 - TaxProcessed
    '', // 49 - TaxProcessedBy
    '', // 50 - ELMCourseID
    $now, // 51 - Modified
    h($_POST['Platform']), // 52 - Platform
    1, // 53 - HUBInclude
    h($_POST['RegistrationLink']), // 54-RegistrationLink
    $slug, // 55-CourseNameSlug,
    h($_POST['HubExpirationDate']) // 56-HubExpirationDate
];
// Check if this is an update
if (isset($_POST['update']) && $_POST['update'] === 'yes') {
    // echo '<pre>'; echo $_POST['update'] . 'NO!'; exit;
    $courseid = h($_POST['CourseID']);

    $file = '../../lsapp/data/courses.csv';
    $tempFile = '../../lsapp/data/courses_tmp.csv';
    $rows = [];
    $found = false;
    $count = 0;
    // Open original CSV for reading and create a temporary file for writing updates
    if (($handle = fopen($file, 'r')) !== FALSE && ($tempHandle = fopen($tempFile, 'w')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $count++;
            echo $count . '. ' . $data[0] . ' - ' . $courseid . '<br>'; 
            if ($data[0] == $courseid) { // If course ID matches
                // echo $count . '. ' . $data[0] . ' - ' . $courseid . '<br>'; 
                echo 'FOUND';
                continue;
                
                // Update only the form-related fields
                //0-CourseID,1-Status,2-CourseName,3-CourseShort,4-ItemCode,5-ClassTimes,6-ClassDays,7-ELM,8-PreWork,9-PostWork,10-CourseOwner,
                //11-MinMax,12-CourseNotes,13-Requested,14-RequestedBy,15-EffectiveDate,16-CourseDescription,17-CourseAbstract,18-Prerequisites,
                //19-Keywords,20-Categories,21-Method,22-elearning,23-WeShip,24-ProjectNumber,25-Responsibility,26-ServiceLine,
                //27-STOB,28-MinEnroll,29-MaxEnroll,30-StartTime,31-EndTime,32-Color
                //33-Featured,34-Developer,35-EvaluationsLink,36-LearningHubPartner,37-Alchemer,
                //38-Topic,39-Audience,40-Levels,41-Reporting
                //42-PathLAN,43-PathStaging,44-PathLive,45-PathNIK,46-CHEFSFormID
                // 47-isMoodle,48-TaxProcessed,49-TaxProcessedBy,50-ELMCourseID,51-Modified
                // 52-Platform, 53-HUBInclude, 54-RegistrationLink, 55-CourseNameSlug, 56-HubExpirationDate
                $data[1] = h($_POST['Status']);
                $data[2] = h($_POST['CourseName']);
                $data[10] = h($_POST['CourseOwner']);
                $data[16] = h($_POST['coursedesc']);
                $data[19] = h($_POST['Keywords']);
                $data[21] = h($_POST['Method']);
                $data[54] = h($_POST['RegistrationLink']);
                $data[36] = h($_POST['LearningHubPartner']);
                $data[38] = h($_POST['Topic']);
                $data[39] = h($_POST['Audience']);
                $data[52] = h($_POST['Platform']);
                $found = true;
                // print_r($data);
                // exit;
            }
            // Write the row to the temporary file (updated or not)
            fputcsv($tempHandle, $data);
        }
        fclose($handle);
        fclose($tempHandle);
    }

    // Replace the original file with the updated file if the course was found and updated
    if ($found) {
        rename($tempFile, $file);
    } else {
        // Remove temporary file if no matching course was found
        unlink($tempFile);
    }

    
} else {
    // If no 'update' POST variable, create a new course by appending to courses.csv
    $course = array($newcourse);
    $fp = fopen('../../lsapp/data/courses.csv', 'a+');
    foreach ($course as $fields) {
        fputcsv($fp, $fields);
    }
    fclose($fp);

    // Append to course-people.csv
    $peoplefp = fopen('../../lsapp/data/course-people.csv', 'a+');
    $stew = [$courseid, 'steward', $_POST['CourseOwner'], $now];
    fputcsv($peoplefp, $stew);

    // $dev = [$courseid, 'dev', $_POST['Developer'], $now];
    // fputcsv($peoplefp, $dev);

    fclose($peoplefp);

    
}
// Redirect back to form
header('Location: course-form.php');