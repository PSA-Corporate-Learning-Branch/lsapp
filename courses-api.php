<?php 
require('inc/lsapp.php');

// Get the full class list
$courses = getCourses();
// Grab the headers
// $headers = $courses[0];
// Pop the headers off the top
array_shift($courses);
// Create a temp array for the array_multisort below
$tmp = array();
// loop through everything and add the name to
// the temp array
foreach($courses as $line) {
	$tmp[] = $line[2];
}
// Sort the whole kit and kaboodle by name
array_multisort($tmp, SORT_ASC, $courses);

foreach($courses as $course):
	if($course[1] == 'Active'):
		h($course[2]);
	endif;
endforeach;

