<?php
date_default_timezone_set('America/Los_Angeles');

// back up courses file before we begin processing
$coursesbackup = '../data/courses.csv';
$newcoursefile = '../data/backups/courses'.date('Ymd\THis').'.csv';
if (!copy($coursesbackup, $newcoursefile)) {
    echo "Failed to backup $newcoursefile...\n";
	exit;
}

// build our filepath from the lsapp.php function
$path = '../data/courses.csv';

// open our courses file
$coursesfile = fopen($path, 'r');

// grab the headers
$courseheaders = fgetcsv($coursesfile);

// add the new column to the headers
$outputheaders = array_merge($courseheaders, ['ModifiedBy']);

echo "Adding new column: ModifiedBy\n";

// open our output file
$outputcourses = fopen('../data/courses-temp.csv','w');
// write our new headers
fputcsv($outputcourses, $outputheaders);

// process each row and add empty value for the new column
while ($row = fgetcsv($coursesfile)) {
    // add one empty column to each row
    $row = array_merge($row, ['']);
    fputcsv($outputcourses, $row);
}

fclose($coursesfile);
fclose($outputcourses);

// overwrite courses.csv with our updated temp file
rename('../data/courses-temp.csv', '../data/courses.csv');

echo "Migration completed successfully. New column 'ModifiedBy' added to courses.csv\n";
