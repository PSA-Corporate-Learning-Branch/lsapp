<?php
opcache_reset();
require_once 'inc/lsapp.php'; // Ensure this file contains the `getCourseDeets` function.

header('Content-Type: application/json');

// Validate and sanitize input
if (!isset($_GET['courseid']) || empty($_GET['courseid'])) {
    echo json_encode(['error' => 'No course ID provided']);
    exit;
}

$courseID = htmlspecialchars(trim($_GET['courseid']), ENT_QUOTES, 'UTF-8');

// Fetch course details
$courseDetails = getCourseDeets($courseID);

// Ensure valid response
if (!$courseDetails || !is_array($courseDetails)) {
    echo json_encode(['error' => 'Course not found']);
    exit;
}

// Convert the course details into an associative array
$response = [
    'CourseID'               => $courseDetails[0],
    'Status'                 => $courseDetails[1],
    'CourseName'             => $courseDetails[2],
    'CourseShort'            => $courseDetails[3],
    'ItemCode'               => $courseDetails[4],
    'ClassTimes'             => $courseDetails[5],
    'ClassDays'              => $courseDetails[6],
    'ELM'                    => $courseDetails[7],
    'PreWork'                => $courseDetails[8],
    'PostWork'               => $courseDetails[9],
    'CourseOwner'            => $courseDetails[10],
    'MinMax'                 => $courseDetails[11],
    'CourseNotes'            => $courseDetails[12],
    'Requested'              => $courseDetails[13],
    'RequestedBy'            => $courseDetails[14],
    'EffectiveDate'          => $courseDetails[15],
    'CourseDescription'      => $courseDetails[16],
    'CourseAbstract'         => $courseDetails[17],
    'Prerequisites'          => $courseDetails[18],
    'Keywords'               => $courseDetails[19],
    'Categories'             => $courseDetails[20],
    'Method'                 => $courseDetails[21],
    'eLearning'              => $courseDetails[22],
    'WeShip'                 => $courseDetails[23],
    'ProjectNumber'          => $courseDetails[24],
    'Responsibility'         => $courseDetails[25],
    'ServiceLine'            => $courseDetails[26],
    'STOB'                   => $courseDetails[27],
    'MinEnroll'              => $courseDetails[28],
    'MaxEnroll'              => $courseDetails[29],
    'StartTime'              => $courseDetails[30],
    'EndTime'                => $courseDetails[31],
    'Color'                  => $courseDetails[32],
    'Featured'               => $courseDetails[33],
    'Developer'              => $courseDetails[34],
    'EvaluationsLink'        => $courseDetails[35],
    'LearningHubPartner'     => $courseDetails[36],
    'Alchemer'               => $courseDetails[37],
    'Topics'                 => $courseDetails[38],
    'Audience'               => $courseDetails[39],
    'Levels'                 => $courseDetails[40],
    'Reporting'              => $courseDetails[41],
    'PathLAN'                => $courseDetails[42],
    'PathStaging'            => $courseDetails[43],
    'PathLive'               => $courseDetails[44],
    'PathNIK'                => $courseDetails[45],
    'CHEFSFormID'              => $courseDetails[46],
    'isMoodle'               => $courseDetails[47],
    'TaxProcessed'           => $courseDetails[48],
    'TaxProcessedBy'         => $courseDetails[49],
    'ELMCourseID'            => $courseDetails[50],
    'Modified'               => $courseDetails[51],
    'Platform'               => $courseDetails[52],
    'HUBInclude'             => $courseDetails[53],
    'RegistrationLink'       => $courseDetails[54],
    'CourseNameSlug'         => $courseDetails[55],
    'HubExpirationDate'      => $courseDetails[56],
    'OpenAccessOptin'        => $courseDetails[57],
    'HubIncludeSync'         => $courseDetails[58],
    'HubIncludePersist'      => $courseDetails[59],
    'HubPersistMessage'      => $courseDetails[60],
];

// Return JSON response
echo json_encode($response);
