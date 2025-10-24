<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 

$courses = getCourses();

$formcourses = array();
// foreach ($courses as $course) {
    
// }