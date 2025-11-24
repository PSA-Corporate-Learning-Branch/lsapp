<?php 

opcache_reset();

require('inc/lsapp.php');
if(canAccess()):

$currentuser = LOGGED_IN_IDIR;
$now = date('YmdHis');
$ccode = $_POST['CourseCode'];
$course = getCourse($ccode);
$dates = $_POST['StartDate'];
$count = 0;
$allclasses = [];
foreach($dates as $date) {
	
	$classid = date('YmdHis') . '-' . $count;
	$combinedtimes = h($_POST['StartTime'][$count]) . ' - ' . h($_POST['EndTime'][$count]);	
	$status = 'Requested';
	
	// EndDate calculation
		// Opted to default to same as StartDate while we determine future state incorporating sessions
		// multi-day consecutive offerings are less common, so this change should result in less frequent changes needed to the end date
		// while still letting the ClassDays field to be used as it has been
	$coursedays = 1;
	$enddate = $date;

	$shipdate = '';
	if($course[23] == 'Yes') {
		$doweship = 'To Ship';
		$shipdate = date("Y-m-d", strtotime($date . ' - ' . 7 . ' days'));
	} else {
		$doweship = 'No Ship';
	}

	$facilitatorsclean = '';
	$fac = $_POST['Facilitating'][$count] ?? '';
	if(!empty($fac)) { 
		$fa = strip_tags(trim($fac));
		$facilitatorsclean = str_replace(',',' ',$fa);
	}

	$newclass = Array($classid,
				$status,
				$now,
				$currentuser,
				$_POST['Dedicated'][$count] ?? 'ELM', // Dedicated ( ELM || Dedicated )
				$course[0],
				$course[2],
				'', // ITEM code
				$date, // StartDate
				$enddate, // EndDate
				$combinedtimes,
				$_POST['MinEnroll'][$count], //$course[28], //MinEnroll
				$_POST['MaxEnroll'][$count], //$course[29], //MaxEnroll
				$shipdate,
				$facilitatorsclean,
				h($_POST['WebinarLink'][$count]),
				$date,
				$coursedays,
				'0', // Enrolled
				'0', // ReservedSeats
				'0', // PendingApproval
				'0', // Waitlisted
				'0', // Dropped
				'', // VenueID
				'', // VenueName
				h($_POST['VenueCity'][$count] ?? ''),
				'', // VenueAddress
				'', // VenuePostalCode
				'', // VenueContactName
				'', // VenuePhone
				'', // VenueEmail
				'', // VenueAttention
				$_POST['RequestNotes'][$count], // h($_POST['RequestNotes']), $_POST['RequestNotes'];
				'', // Shipper
				'', // Boxes
				'', // Weight
				'', // Courier
				'', // TrackingOut
				'', // TrackingIn
				'', // AttendanceReturned
				'', // EvaluationsReturned
				'', // VenueNotified
				$now,
				$currentuser,
				'', // Assigned
				$course[21],
				$course[20], //h($_POST['CourseCategory']),
				'', // Region
				'', // CheckedBy
				$doweship, // ShippingStatus
				'', // PickupIn
				'', // avAssigned
				0, // venueCost
				'', // venueBEO
				h($_POST['StartTime'][$count]), // StartTime
				h($_POST['EndTime'][$count]), // EndTime
				$course[32], // CourseColor,
				'', // EvaluationsSent
				'' // EvaluationsLink
	);

	array_push($allclasses,$newclass);

	$count ++;
}

$fp = fopen('data/classes.csv', 'a+');
foreach ($allclasses as $fields) {
    fputcsv($fp, $fields);
}
fclose($fp);
//header('Location: /lsapp/course.php?courseid=' . $course[0]);
header('Location: /lsapp/person.php?idir=' . LOGGED_IN_IDIR);

else:
	include('templates/noaccess.php');
endif;
