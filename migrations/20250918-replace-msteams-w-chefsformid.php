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

$valuetoreplace = 'PathTeams';
$replacementvalue = 'CHEFSFormID';

// open our courses file
$coursesfile = fopen($path, 'r');

// grab the headers
$courseheaders = fgetcsv($coursesfile);

$outputheaders = array();
$columnindex = '';
foreach ($courseheaders as $key => $value) {
    if ($value == $valuetoreplace) {
        $value = $replacementvalue;
        $columnindex = $key;
    }
    array_push($outputheaders, $value);
}

if ($columnindex === '') {
    echo "Update value, " . $valuetoreplace . ", not found\n";
    fclose($coursesfile); 
    exit;
}

// open our output file
$outputcourses = fopen('../data/courses-temp.csv','w');
// write our new headers
fputcsv($outputcourses, $outputheaders);

while ($row = fgetcsv($coursesfile)) {
    // check if our previously identified column has an entry
    if(strlen($row[$columnindex]) > 0) {

        // our Form ID pattern
        $pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';
		// check for a pattern match
        $match = preg_match($pattern, $row[$columnindex]); // returns 1 on match
        
        if ($match !== 1) {
            echo "Course ID " . $row[0] . " value removed: " . $row[$columnindex] . "\n";
            $row[$columnindex] = '';
        }
    }
    fputcsv($outputcourses, $row);
}

fclose($coursesfile);
fclose($outputcourses);

// overwrite courses.csv with our updated temp file
rename('../data/courses-temp.csv', '../data/courses.csv');

