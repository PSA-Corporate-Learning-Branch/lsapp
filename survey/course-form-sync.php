<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('functions.php');

$config = getConfig();

$courses = getCourses();

// since we're primarily just looking to see if a form id in our course
// exists in our config, we can create an array of config form ids to
// more easily check against
$surveys = array();
foreach ($config as $c) {
    $surveys[] = $c['formId'];
}
// the new surveys to be added to the config
$new_surveys = array();

// track the new form ids we're adding to look for dupes
$new_survey_form_ids = array();

// capture which courses have surveys 
$courses_with_surveys = array();
$course_form_ids = array();

// go through our courses looking for active courses with form ids added
foreach ($courses as $course) {
    if ($course[1] == 'Active' && !empty($course[46])) {
        // if the form id doesn't exist in our config, add in pending status
        if (!in_array($course[46], $surveys) && !in_array($course[46], $new_survey_form_ids)) {
            $new_surveys[] = [
                'formId' => $course[46],
                'status' => 'pending',
                'courseId' => $course[0]
            ];
            // track the new form id we're adding
            $new_survey_form_ids[] = $course[46];
        }
        // add existing courses with surveys to arrays to check for dupes
        if (!in_array($course[46], $course_form_ids)) {
            $courses_with_surveys[] = $course;
            $course_form_ids[] = $course[46];
        }
        // if we've added a form id already, review our courses to find the dupe for alert
        else {
            foreach ($courses_with_surveys as $survey_course) {
                if ($survey_course[46] == $course[46]) {
                    AlertManager::addAlert('warning', "CHEFS Form ID $course[46], for $course[2] already associated with another course: $survey_course[2] ($survey_course[4])");
                }
            }
        }
    }
}

$new_config = array_merge($config, $new_surveys);

$alerts = AlertManager::getAlertsAll();
if (count($alerts) > 0) {
    foreach ($alerts as $alert) {
        echo "$alert[type]: $alert[message]";
    }
}

// save our updated config
updateConfig($new_config);
