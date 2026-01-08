<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');

$config = getConfig();

$courses = getCourses();

// since we're primarily just looking to see if a form id in our course
// exists in our config, we can create an array of config form ids to
// more easily check against
$surveys = array();
foreach ($config as $c) {
    $surveys[] = $c['formId'];
}

// go through our courses looking for active courses with form ids added
foreach ($courses as $course) {
    if ($course[1] == 'Active' && !empty($course[46])) {
        // if the form id doesn't exist in our config, add in pending status
        if (!in_array($course[46], $surveys)) {
            $config[] = [
                'formId' => $course[46],
                'status' => 'pending',
                'courseId' => $course[0]
            ];
        }
        
    }
}

// save our updated config
updateConfig($config);
