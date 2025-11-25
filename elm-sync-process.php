<?php
//
// This runs through the output of the GBC_CURRENT_COURSE_INFO ELM query,
// matches the ITEM Code with one in LSApp, and then updates LSApp with the
// current ELM status and attendance numbers for that class.
//
//
require('inc/lsapp.php');
require('inc/ches_client.php');

$elm = fopen('data/elm.csv', 'r');
// Pop the headers row off
$elmheaders = fgetcsv($elm);

$classesbackup = 'data/classes.csv';
$newfile = 'data/backups/classes'.date('Ymd\THis').'.csv';
if (!copy($classesbackup, $newfile)) {
    echo "Failed to backup $newfile...\nPlease inform the Team Lead ASAP";
	exit;
}
$coursesbackup = 'data/courses.csv';
$newcoursefile = 'data/backups/courses'.date('Ymd\THis').'.csv';
if (!copy($coursesbackup, $newcoursefile)) {
    echo "Failed to backup $newcoursefile...\nPlease inform the Team Lead ASAP";
	exit;
}

$lsapp = fopen('data/classes.csv', 'r');
// Pop the headers row off and save them so when we rewrite this file below,
// we use them to start the new file
$lsappheaders = fgetcsv($lsapp);
$lsappclasses = array();
while ($row = fgetcsv($lsapp)) {
	array_push($lsappclasses,$row);
}
fclose($lsapp);
$updatedcount = 0;
$logEntries = [];
$updatedClasses = [];
$timestamp = date('Y-m-d H:i:s');
?>

<?php getHeader() ?>

<title>Upload PUBLIC.GBC_CURRENT_COURSE_INFO</title>

<style>
.upcount {
	font-size: 30px;
	margin: 30px 0;
}
</style>
<?php getScripts() ?>

<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>
</div>
<div class="col-md-6">
<h2>ELM - LSApp Enrolment Number Synchronize</h2>
<div class="alert alert-success">Synchronization Completed</div>
<ul class="list-group">
<?php if(isAdmin()): ?>
<?php 
// loop through each line of the elm file
while ($elmrow = fgetcsv($elm)) {
	// Reset lsappcount after every loop 
	$lsappcount = 0;
	// Loop through each of the LSApp classes and do an 
	// array_search on the ELM item code on each
	foreach($lsappclasses as $lsappclass) {
		// Look through the single class row to see if the ITEM code matches
		if($elmrow[1] == $lsappclass[7]) {
			// If there's a difference between ELM and LSApp, then we 
			// update LSApp accordingly and output the updated class 
			// to the screen. If there's no difference, we just move on
			$newEnrolled = intval($elmrow[8]) + intval($elmrow[16]);
			if($newEnrolled != $lsappclass[18] ||
				$elmrow[9] != $lsappclass[19] ||
				$elmrow[10] != $lsappclass[20] ||
				$elmrow[11] != $lsappclass[21] ||
				$elmrow[12] != $lsappclass[22]) {

				$updatedcount++;

				// Collect changes for logging
				$changes = [];
				if($newEnrolled != $lsappclass[18]) {
					$changes[] = "Enrolled: {$lsappclass[18]} → {$newEnrolled}";
				}
				if($elmrow[9] != $lsappclass[19]) {
					$changes[] = "Reserved: {$lsappclass[19]} → {$elmrow[9]}";
				}
				if($elmrow[10] != $lsappclass[20]) {
					$changes[] = "Pending: {$lsappclass[20]} → {$elmrow[10]}";
				}
				if($elmrow[11] != $lsappclass[21]) {
					$changes[] = "Waitlist: {$lsappclass[21]} → {$elmrow[11]}";
				}
				if($elmrow[12] != $lsappclass[22]) {
					$changes[] = "Dropped: {$lsappclass[22]} → {$elmrow[12]}";
				}

				// Store update details for email
				$updatedClasses[] = [
					'class_id' => $lsappclass[0],
					'course_name' => $lsappclass[6],
					'date' => goodDateLong($lsappclass[8], $lsappclass[9]),
					'item_code' => $lsappclass[7],
					'changes' => $changes,
					'elm_enrolled' => $newEnrolled,
					'lsapp_enrolled' => $lsappclass[18],
					'elm_reserved' => $elmrow[9],
					'lsapp_reserved' => $lsappclass[19],
					'elm_pending' => $elmrow[10],
					'lsapp_pending' => $lsappclass[20],
					'elm_waitlist' => $elmrow[11],
					'lsapp_waitlist' => $lsappclass[21],
					'elm_dropped' => $elmrow[12],
					'lsapp_dropped' => $lsappclass[22]
				];

				$logEntries[] = "Updated {$lsappclass[6]} ({$lsappclass[7]}) - " . goodDateLong($lsappclass[8], $lsappclass[9]) . ": " . implode(', ', $changes);

				echo '<li class="list-group-item">';
				//echo '<div class="upcount float-left">' . $updatedcount . '</div>';
				echo '<a href="class.php?classid=' . $lsappclass[0] . '">';
				echo '<strong>' . $lsappclass[6] . '</strong><br>';
				echo goodDateLong($lsappclass[8],$lsappclass[9]) . '<br>';
				echo $lsappclass[7] . ' UPDATED.';
				echo '</a>';
				echo '<div class="alert alert-warning">';
				echo 'ELM Enrolled/In-Progress: ' . $newEnrolled . ' | ';
				if($newEnrolled != $lsappclass[18]) {
					echo '<strong>LSApp Enrolled: ' . $lsappclass[18] . '</strong><br>';
				} else {
					echo 'LSApp Enrolled: ' . $lsappclass[18] . '<br>';
				}
				echo 'ELM Reserved: ' . $elmrow[9] . ' | ';
				if($elmrow[9] != $lsappclass[19]) {
					echo '<strong>LSApp Reserved: ' . $lsappclass[19] . '</strong><br>';
				} else {
					echo 'LSApp Reserved: ' . $lsappclass[19] . '<br>';
				}
				echo 'ELM Pending: ' . $elmrow[10] . ' | ';
				if($elmrow[10] != $lsappclass[20]) {
					echo '<strong>LSApp Pending: ' . $lsappclass[20] . '</strong><br>';
				} else {
					echo 'LSApp Pending: ' . $lsappclass[20] . '<br>';
				}
				echo 'ELM Waitlist: ' . $elmrow[11] . ' | ';
				if($elmrow[11] != $lsappclass[21]) {
					echo '<strong>LSApp Waitlist: ' . $lsappclass[21] . '</strong><br>';
				} else {
					echo 'LSApp Waitlist: ' . $lsappclass[21] . '<br>';
				}
				echo 'ELM Dropped: ' . $elmrow[12] . ' | ';
				if($elmrow[12] != $lsappclass[22]) {
					echo '<strong>LSApp Dropped: ' . $lsappclass[22] . '</strong><br>';
				} else {
					echo 'LSApp Dropped: ' . $lsappclass[22] . '<br>';
				}
				echo '</div>';
				echo '</li>';

				$lsappclasses[$lsappcount][1] = $elmrow[5]; // status
				$lsappclasses[$lsappcount][18] = intval($elmrow[8]) + intval($elmrow[16]); // enrolled + in-progress
				$lsappclasses[$lsappcount][19] = $elmrow[9]; // Reserved
				$lsappclasses[$lsappcount][20] = $elmrow[10]; // Pending
				$lsappclasses[$lsappcount][21] = $elmrow[11]; // Waitlist
				$lsappclasses[$lsappcount][22] = $elmrow[12]; // Dropped

			}
		} 
		$lsappcount++;
	}
	
}
// Close elm.csv 
fclose($elm);

// Now write lsappclass back to classes.csv in one go
// Open the elm.csv file; note the 'w' as we're opening the file, 
// removing all its existing content, and starting the write at the beginning of the file
$newclasses = fopen('data/classes.csv', 'w');
// Add the headers
fputcsv($newclasses, $lsappheaders);
// Now loop through the $newelm array created above and write each line to the file
foreach ($lsappclasses as $fields) {
	fputcsv($newclasses, $fields);
}
// Close the file
fclose($newclasses);

// Send comprehensive email with sync results
try {
	$chesClient = new CHESClient();

	// Determine subject based on activity
	if ($updatedcount > 0) {
		$subject = "ELM Enrolment Sync - $updatedcount Class" . ($updatedcount > 1 ? 'es' : '') . " Updated";
	} else {
		$subject = "ELM Enrolment Sync - No Changes";
	}

	// Build HTML email body
	$bodyHtml = "<h2>ELM Enrolment Number Synchronization Report</h2>";
	$bodyHtml .= "<p><strong>Sync completed:</strong> $timestamp</p>";

	// Summary section
	$bodyHtml .= "<div style='background-color: #f0f0f0; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
	$bodyHtml .= "<h3>Summary</h3>";
	$bodyHtml .= "<p><strong>Total classes updated:</strong> $updatedcount</p>";
	$bodyHtml .= "</div>";

	// Updated classes section (if any)
	if ($updatedcount > 0) {
		$bodyHtml .= "<h3>📊 Updated Classes</h3>";
		$bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif; width: 100%;'>";
		$bodyHtml .= "<tr style='background-color: #f2f2f2;'>";
		$bodyHtml .= "<th>Course Name</th><th>Date</th><th>Item Code</th><th>Changes</th><th>View Class</th>";
		$bodyHtml .= "</tr>";

		foreach ($updatedClasses as $class) {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$host = $_SERVER['HTTP_HOST'];
			$classUrl = $protocol . $host . "/lsapp/class.php?classid=" . urlencode($class['class_id']);

			$bodyHtml .= "<tr>";
			$bodyHtml .= "<td><strong>" . htmlspecialchars($class['course_name']) . "</strong></td>";
			$bodyHtml .= "<td>" . htmlspecialchars($class['date']) . "</td>";
			$bodyHtml .= "<td>" . htmlspecialchars($class['item_code']) . "</td>";
			$bodyHtml .= "<td><ul style='margin: 0; padding-left: 20px;'>";
			foreach ($class['changes'] as $change) {
				$bodyHtml .= "<li>" . htmlspecialchars($change) . "</li>";
			}
			$bodyHtml .= "</ul></td>";
			$bodyHtml .= "<td><a href='" . htmlspecialchars($classUrl) . "'>View</a></td>";
			$bodyHtml .= "</tr>";

			// Add detailed comparison row
			$bodyHtml .= "<tr style='background-color: #f8f9fa;'>";
			$bodyHtml .= "<td colspan='5' style='font-size: 12px; padding: 5px 8px;'>";
			$bodyHtml .= "<strong>Details:</strong> ";
			$bodyHtml .= "Enrolled: " . ($class['lsapp_enrolled'] != $class['elm_enrolled'] ? "<strong>{$class['lsapp_enrolled']} → {$class['elm_enrolled']}</strong>" : $class['elm_enrolled']) . " | ";
			$bodyHtml .= "Reserved: " . ($class['lsapp_reserved'] != $class['elm_reserved'] ? "<strong>{$class['lsapp_reserved']} → {$class['elm_reserved']}</strong>" : $class['elm_reserved']) . " | ";
			$bodyHtml .= "Pending: " . ($class['lsapp_pending'] != $class['elm_pending'] ? "<strong>{$class['lsapp_pending']} → {$class['elm_pending']}</strong>" : $class['elm_pending']) . " | ";
			$bodyHtml .= "Waitlist: " . ($class['lsapp_waitlist'] != $class['elm_waitlist'] ? "<strong>{$class['lsapp_waitlist']} → {$class['elm_waitlist']}</strong>" : $class['elm_waitlist']) . " | ";
			$bodyHtml .= "Dropped: " . ($class['lsapp_dropped'] != $class['elm_dropped'] ? "<strong>{$class['lsapp_dropped']} → {$class['elm_dropped']}</strong>" : $class['elm_dropped']);
			$bodyHtml .= "</td>";
			$bodyHtml .= "</tr>";
		}

		$bodyHtml .= "</table>";
	} else {
		$bodyHtml .= "<p><em>No class enrolment updates were needed during this sync.</em></p>";
	}

	// Full log section
	$bodyHtml .= "<h3>📋 Complete Sync Log</h3>";
	if (count($logEntries) > 0) {
		$bodyHtml .= "<div style='background-color: #f8f9fa; padding: 15px; font-family: monospace; font-size: 12px; overflow-x: auto; border: 1px solid #dee2e6;'>";
		foreach ($logEntries as $entry) {
			$bodyHtml .= htmlspecialchars($entry) . "<br>";
		}
		$bodyHtml .= "</div>";
	} else {
		$bodyHtml .= "<p><em>No changes detected during this sync.</em></p>";
	}

	// Build plain text email body
	$bodyText = "ELM ENROLMENT NUMBER SYNCHRONIZATION REPORT\n";
	$bodyText .= str_repeat("=", 80) . "\n";
	$bodyText .= "Sync completed: $timestamp\n\n";

	$bodyText .= "SUMMARY\n";
	$bodyText .= str_repeat("-", 80) . "\n";
	$bodyText .= "Total classes updated: $updatedcount\n\n";

	if ($updatedcount > 0) {
		$bodyText .= "UPDATED CLASSES\n";
		$bodyText .= str_repeat("=", 80) . "\n";
		foreach ($updatedClasses as $class) {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$host = $_SERVER['HTTP_HOST'];
			$classUrl = $protocol . $host . "/lsapp/class.php?classid=" . urlencode($class['class_id']);

			$bodyText .= "\nCourse: {$class['course_name']}\n";
			$bodyText .= "Date: {$class['date']}\n";
			$bodyText .= "Item Code: {$class['item_code']}\n";
			$bodyText .= "Changes:\n";
			foreach ($class['changes'] as $change) {
				$bodyText .= "  - $change\n";
			}
			$bodyText .= "View: $classUrl\n";
			$bodyText .= str_repeat("-", 80) . "\n";
		}
	}

	$bodyText .= "\nCOMPLETE SYNC LOG\n";
	$bodyText .= str_repeat("=", 80) . "\n";
	if (count($logEntries) > 0) {
		foreach ($logEntries as $entry) {
			$bodyText .= $entry . "\n";
		}
	} else {
		$bodyText .= "No changes detected during this sync.\n";
	}

	// Send the email
	$emailResult = $chesClient->sendEmail(
		['allan.haggett@gov.bc.ca'],
		$subject,
		$bodyText,
		$bodyHtml,
		'lsapp_syncbot_noreply@gov.bc.ca'
	);

	error_log("ELM enrolment sync notification sent successfully (Transaction ID: {$emailResult['txId']})");

} catch (Exception $e) {
	error_log("CHES Email Exception on ELM enrolment sync: " . $e->getMessage());
}

endif; ?>
</ul>
</div>
<div class="col-md-4">
<h2><span class="badge text-bg-dark"><?= $updatedcount ?></span> Updated.</h2>
</div>
</div>
</div>

<?php require('templates/javascript.php') ?>


<?php require('templates/footer.php') ?>
