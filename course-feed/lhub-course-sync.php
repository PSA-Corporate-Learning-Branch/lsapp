<?php
opcache_reset();
require('../inc/lsapp.php');
if(canAccess()):
// Get LSApp courses
$coursespath = $_SERVER['DOCUMENT_ROOT'] . '\lsapp\data\courses.csv';
$rawc = fopen($coursespath, 'r');
fgetcsv($rawc);
$lsappcourses = [];
while ($row = fgetcsv($rawc)) {
	if($row[1] == 'Active') {
		array_push($lsappcourses,$row);
	}
}
fclose($rawc);
// echo '<pre>'; print_r($lsappcourses); exit;
// Get LearningHUB courses
$path = $_SERVER['DOCUMENT_ROOT'] . '\lsapp\course-feed\data\courses.csv';
$hc = fopen($path, 'r');
fgetcsv($hc);
$hubcourses = [];
while ($row = fgetcsv($hc)) {
	array_push($hubcourses,$row);
}
fclose($hc);
// echo '<pre>'; print_r($lsappcourses); exit;
$count = 0;
foreach($hubcourses as $hc) {
	$inlsapp = 0;
	// loop through each lsapp course and check its ITEM-CODE against
	// the one from the learninghub
	foreach($lsappcourses as $lc) {
		if(strtoupper($hc[0]) == strtoupper($lc[4])) {
			$inlsapp = 1;
		}
	}
	// If this course doesn't already exist in LSApp then we
	// go ahead and create it with the info that we have 
	if($inlsapp == 0) {
		$count++;
		$courseid = date('YmdHis') . '-' . $count;
		$now = date('Y-m-d\TH:i:s');
		$coursecat = '';
		$weship = 'No';
		$alchemer = 'No';
		// 0-"Course Code",1-"Course Name",2-"Course Description",3-"Delivery Method",4-Category,5-"Learner Group",
		// 6-parsedduration,7-parsedduration,8-parsedduration,9-"Available Classes",10-"Link to ELM Search",
		// 11-"Course Last Modified",12-"Course Owner Org",13-"Course ID",14-Keywords
		$newcourse = Array($courseid,
						'Active',
						h($hc[1]),
						'',
						h($hc[0]), // ItemCode,
						'',
						'',
						'', // ELM link
						'',
						'',
						'Imported',
						'', // used to be min/max now unused
						'',
						$now,
						'Imported',
						$now,
						h($hc[2]),
						'',
						'',
						h($hc[14]),
						h($hc[4]),
						h($hc[3]),
						'', // elearning flag
						$weship,
						'', // ProjectNumber
						'', // Responsibility
						'', // ServiceLine
						'', // STOB
						'',
						'',
						'',
						'',
						'#F1F1F1',
						1,
						'',
						'',
						h($hc[12]),
						$alchemer,
						h($hc[18]),
						h($hc[17]),
						h($hc[16]),
						'', //h($hc['Reporting']),
						'', // PathLAN
						'', // PathStaging
						'', // PathLive
						'', // PathNIK
						'', // CHEFSFormID
						0, // isMoodle,
						0, // TaxProcessed
						'', // TaxProcessedBy
						h($hc[13]),  // ELMCourseID - field #50
						$now
			);
			
		$fp = fopen($coursespath, 'a+');
		fputcsv($fp, $newcourse);
		fclose($fp);
		// echo $count . ' - ' . $hc[0] . ' - ' . $courseid . '<br>';

	}
}
// header('Location: index.php?message=Success');
header('Location: jsonfeed.php');
?>
<?php else: ?>
<?php include('templates/noaccess.php') ?>
<?php endif ?>
