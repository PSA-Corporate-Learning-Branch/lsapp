<?php
opcache_reset();
require('../inc/lsapp.php');

if (($handle = fopen("../data/courses.csv", "r")) !== false) {
    $csvs = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (!empty(array_filter($row))) {  // Skip empty rows
            $csvs[] = $row;
        }
    }
    fclose($handle);

    // Ensure the file is not empty before processing
    if (!empty($csvs)) {
        // Map column names to CSV values
        $datas = [];
        $column_names = $csvs[0];
        foreach ($csvs as $key => $csv) {
            if ($key === 0) continue;  // Skip header row
            foreach ($column_names as $column_key => $column_name) {
                $datas[$key - 1][$column_name] = $csv[$column_key] ?? '';
            }
        }
    } else {
        echo "CSV file is empty or not properly formatted.";
        exit;
    }
} else {
    echo "Unable to open CSV file.";
    exit;
}

$json = [
    "version" => "https://jsonfeed.org/version/1",
    "title" => "BC Gov Corporate Learning Courses",
    "home_page_url" => "https://learningcentre.gww.gov.bc.ca/learninghub/",
    "feed_url" => "https://learn.bcpublicservice.gov.bc.ca/learning-hub/bcps-corporate-learning-courses.json",
    "items" => []
];

foreach ($datas as $course) {

    $description = $course['CourseDescription'] ?? '';
    $description = str_replace('&nbsp;', ' ', $description);
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    $desc = iconv(mb_detect_encoding($description, mb_detect_order(), true), "UTF-8", $description);
    $createdDate = date("Y-m-d\TH:i:s", strtotime(str_replace('  ', ' ', $course['Requested'] ?? '')));
    $modifiedDate = date("Y-m-d\TH:i:s", strtotime(str_replace('  ', ' ', $course['Modified'] ?? '')));

    $devpartners = getDevPartnersByCourseID($course['CourseID']);

    // If there are development partners, append them to the description
    if (!empty($devpartners)) {
        $partnerText = "\n\nThis course has been developed in partnership with:\n";
        foreach ($devpartners as $partner) {
            $partnerName = $partner[3] ?? '';  // name is in column 3
            $partnerURL = $partner[5] ?? '';   // URL is in column 5

            if (!empty($partnerName)) {
                if (!empty($partnerURL)) {
                    $partnerText .= "- [" . $partnerName . "](" . $partnerURL . ")\n";
                } else {
                    $partnerText .= "- " . $partnerName . "\n";
                }
            }
        }
        $desc .= $partnerText;
    }

    if($course['Platform'] !== 'PSA Learning System' || $course['HubIncludeSync'] == 'no') {
        $registrationurl = $course['RegistrationLink'];
    } else {
        $registrationurl = "https://learning.gov.bc.ca/psc/CHIPSPLM/EMPLOYEE/ELM/c/LM_OD_EMPLOYEE_FL.LM_CRS_DTL_FL.GBL?Page=LM_CRS_DTL_FL&Action=U&ForceSearch=Y&LM_CI_ID=" . $course['ELMCourseID'];
    }

    if ($course['Status'] == 'Active' && !empty($course['LearningHubPartner'])  && strtolower($course['HUBInclude']) === 'yes') {
        // Determine persistent status
        $persistent = 'no';
        if (isset($course['HubIncludePersist']) && strtolower($course['HubIncludePersist']) === 'yes') {
            $persistent = 'yes';
        }
        
        // Convert partner ID to name for the feed
        $partnerName = getPartnerNameById($course['LearningHubPartner']);
        
        $courseItem = [
            "id" => !empty($course['ItemCode']) ? $course['ItemCode'] : ($course['CourseID'] ?? ''),
            "title" => $course['CourseName'] ?? '',
            "summary" => $desc,
            "content_text" => $course['CourseName'] ?? '',
            "delivery_method" => $course['Method'] ?? '',
            "_course_id" => $course['CourseID'] ?? '',
            "_keywords" => $course['Keywords'] ?? '',
            "_audience" => $course['Audience'] ?? '',
            "_topic" => $course['Topics'] ?? '',
            "_slug" => $course['CourseNameSlug'] ?? '',
            "_learning_partner" => $partnerName,
            "_platform" => $course['Platform'] ?? '',
            "_persistent" => $persistent,
            "author" => $partnerName,
            "date_published" => $createdDate,
            "date_modified" => $modifiedDate,
            "tags" => rtrim(trim($course['Category'] ?? ''), ','),
            "url" => $registrationurl
        ];
        
        // Add persist message if course is persistent
        if ($persistent === 'yes' && !empty($course['HubPersistMessage'])) {
            $courseItem['_persist_message'] = $course['HubPersistMessage'];
        }
        
        // Add persist state if course is persistent
        if ($persistent === 'yes' && isset($course['HubIncludePersistState'])) {
            $courseItem['_persist_state'] = $course['HubIncludePersistState'];
        }
        
        $json['items'][] = $courseItem;
    }
}

$jsonOutput = json_encode($json, JSON_PRETTY_PRINT);
$jsonFilename = 'data/bcps-corporate-learning-courses.json';
file_put_contents($jsonFilename, $jsonOutput);

// $newfile = 'E:/WebSites/NonSSOLearning/learning-hub/bcps-corporate-learning-courses.json';
// We used to be able to just copy the file to the NIK public folder, but something changed
// with the AppPool identity and it no longer has access to that folder. So now we copy it to
// a folder we have access to and then we have a separate script run from the NIK side that copies
// it to the public folder. This is all such a hilarious hack but it is what it is. Open a PR
// if you have a better idea :)

$newfile = 'E:/WebSites/lsapp-data/bcps-corporate-learning-courses.json';
if (!copy($jsonFilename, $newfile)) {
    echo 'Failed to copy ' . $jsonFilename . '... contact Allan';
    exit;
}


header('Location: index.php?message=Success');
exit;
