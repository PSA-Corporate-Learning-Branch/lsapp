<?php
// Let's get started
require('inc/lsapp.php');
// Get the full class list
$c = getClasses();
// Pop the headers off the top
array_shift($c);
// Create a temp array for the array_multisort below
$tmp = array();
// loop through everything and add the start date to
// the temp array
foreach($c as $line) {
	$tmp[] = $line[8];
}
// Sort the whole kit and kaboodle by start date from
// oldest to newest
array_multisort($tmp, SORT_DESC, $c);

//
// Now let's run through the whole thing and process it, removing
// classes with dates older than "today" and any requested classes
//
$count = 0;
$inactive = 0;
$upclasses = array();
$today = date('Y-m-d');
foreach($c as $row) {
	//
	// We only wish to see classes which have an end date greater than today
	//
	if($row[9] > $today) continue;
	if($row[1] == 'Deleted') continue;
	//
	// We want to continue to show inactive classes, but we also want an accurate
	// count of classes that are upcoming; we count the inactives and subtract them
	// from the total
	//
	if($row[1] == 'Inactive') $inactive++;
	//
	// If the status is Requested, we skip the line entirely
	//
	//if($row[1] == 'Requested') continue;
	//
	// Add the class to the array that we'll loop through below
	//
	array_push($upclasses,$row);
	$count++;
}
?>
<?php getHeader() ?>

<title>Past Classes</title>

<?php getScripts() ?>
<?php getNavigation() ?>
<?php if(canAccess()): ?>
<div class="container-fluid">
<div class="row">
<div class="col-md-12">
<div id="upcoming-classes">


	<h1 class="text-center mb-0">
		<span class="classcount"><?= ($count - $inactive) ?></span>
		Past Classes
	</h1>
	<div class="text-center"><small>Going back to <strong>February 2021</strong> <small>Data going back to Jan 2019 available on request</small> | <a href="classes-past-export.php">Export</a></small></div>
	<!--<div class="text-center"><small>Enrollment numbers as of <?php echo date ("F d Y H:i", filemtime('data/elm.csv')) ?></small></div>-->
	<input class="search form-control my-2 mx-auto w-50" placeholder="search">
	<div class="w-50 row mt-2 mx-auto">

		<!-- <div class="alert alert-primary col-4 p-0 text-center">Shipped</div> -->
		<!-- <div class="alert alert-success col-4 p-0 text-center">Arrived</div> -->
		<!--<div class="alert alert-warning col-4 p-0 text-center"><a href="#" class="sort asc" data-sort="status">Requested</a></div>-->
		<!--<div class="alert alert-danger col-4 p-0 text-center">Alert!</div>-->
	</div>
	<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
				<th scope="col" width="5">ELM</th>
				<th scope="col" width="5">Shipping</th>
				<th scope="col" width="100" class="text-right">Item Code</th>
                <th scope="col" width="138" class="text-right"><a href="#" class="sort" data-sort="startdate">Class Date</a></th>
                <th scope="col" width="300"><a href="#" class="sort" data-sort="course">Course</a></th>
                <th scope="col" width="250"><a href="#" class="sort" data-sort="venue">Venue</a></th>
                <th scope="col" width="130"><a href="#" class="sort" data-sort="city">City</th>
                <th scope="col" width="100"><a href="#" class="sort" data-sort="facilitator">Facilitator</th>
                <!--<th scope="col" width="50"><a href="#" class="sort" data-sort="region">Region</th>-->
				<th scope="col" width="50"><a href="#" class="sort" data-sort="enrolled">Enrolled</a></th>
				<th scope="col" width="50"><a href="#" class="sort" data-sort="attendance">Attendance</a></th>
				<th scope="col" width="50"><a href="#" class="sort" data-sort="survey">Survey</a></th>
            </tr>
        </thead>
	<tbody class="list">

	<?php foreach($upclasses as $row): ?>
	<?php
	$statrow = '';
	$issueflag = '';
	if(!$row[7] && $row[4] != 'Dedicated' && $row[1] != 'Requested' && $row[1] != 'Inactive') {
		$issueflag = '<span class="badge bg-danger text-white">???</span>';
	}
	//if($row[1] == 'Pending') {
	//	$statrow = 'table-primary';
	//} else
	if($row[1] == 'Inactive') {
		$statrow = 'cancelled';
	}
	if($row[1] == 'Requested') {
		$statrow = 'table-warning';
	}
	if($row[49] == 'Shipped') {
		$statrow = 'table-primary';
	} elseif($row[49] == 'Arrived') {
		$statrow = 'table-success';
	}
	?>
	<tr class="<?= $statrow ?>">
		<!-- ELM -->
		<td class="status">
			<div style="display:block" title="ELM status">
			<?= h($row[1]) ?>
			</div>
		</td>
		<!-- Shipping -->
		<td class="shippingstatus">
			<div style="display:block" title="current shipping status">
			<?= h($row[49]) ?>
			</div>
		</td>
		<!-- Item Code -->
		<td class="text-right itemcode">
			<?= $issueflag ?>
			<small><?= h($row[7]) ?></small>
			<?php if($row[4] == 'Dedicated'): ?>
				<span class="badge bg-light-subtle text-primary-emphasis">Dedicated</span>
			<?php endif ?>
		</td>
		<!-- Class Date -->
		<td class="text-right">
			<a href="class.php?classid=<?= h($row[0]) ?>">
				<?php print goodDateShort($row[8],$row[9]) ?>
			</a>
			<div class="startdate" style="display: none"><?= h($row[8]) ?></div>
		</td>
		<!-- Course -->
		<td class="course"><a href="course.php?courseid=<?= h($row[5]) ?>"><?= h($row[6]) ?></a></td>
		<!-- Venue -->
		<td class="venue"><a href="venue.php?vid=<?= h($row[23]) ?>"><?= h($row[24]) ?></a>
			<div style="display: none">
				<?= h($row[28]) ?><br>
				<?= h($row[29]) ?><br>
				<?= h($row[30]) ?><br>
				<?= h($row[26]) ?><br>
				<?= h($row[25]) ?><br>
				<?= h($row[27]) ?>
			</div>
		</td>
		<!-- City -->
		<td class="city">
            <a href="city.php?name=<?= h($row[25]) ?>"><?= h($row[25]) ?></a>
            <?php if(!$row[25] && $row[1] != 'Inactive'): ?>
				<span class="badge bg-light-subtle text-primary-emphasis"><?= h($row[45]) ?></span>
			<?php endif ?>
        </td>
		<!-- Facilitator -->
        <td class="facilitator">
	      <?php $facilitators = explode(' ', $row[14]); ?>
		  <?php foreach($facilitators as $facilitator): ?>
		    <a href="person.php?idir=<?= h($row[14]) ?>">
			  <?= h($facilitator) ?>
			</a>
		  <?php endforeach ?>
		</td>
		<!-- Enrolled -->
		<td class="enrolled">
			<?php if($row['18'] < $row[11] && $row[1] != 'Inactive'): ?>
				<span class="badge bg-danger text-white" title="Enrollment is currently below the set minimum"><?= h($row[18]) ?></span>
			<?php elseif($row[1] == 'Inactive'): ?>
			<?php else: ?>
				<span class="badge bg-light-subtle text-primary-emphasis"><?= h($row[18]) ?></span>
			<?php endif ?>
		</td>
		<!-- Attendance -->
		<td class="attendance">
			<?php if($row[1] != 'Inactive'): ?>	
				<?php if($row[39] != 'Yes'): ?>
					<span class="badge bg-danger text-white">No</a>
				<?php else: ?>
					<span class="badge bg-success text-white">Yes</a>
				<?php endif ?>
			<?php endif ?>
		</td>
		<!-- Survey -->
		<td class="survey">
			<!-- Don't show the badge if the class is inactive or ends before we started using CHEFS -->
			<?php if($row[1] != 'Inactive' && $row[9] > '2025-10-06'): ?>	
				<?php if($row[40] != 'Yes'): ?>
					<span class="badge bg-danger text-white">No</a>
				<?php else: ?>
					<span class="badge bg-success text-white">Yes</a>
				<?php endif ?>
			<?php endif ?>
		</td>
	</tr>
<?php endforeach ?>
</table>
</div>
</div>
</div>
</div>

<?php else: ?>
<?php include('templates/noaccess.php') ?>
<?php endif ?>

<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){

	$('.search').focus();

	var upcomingoptions = {
		valueNames: [ 'status', 'shippingstatus', 'startdate', 'course', 'facilitator', 'region', 'venue', 'city', 'itemcode', 'enrolled', 'attendance', 'survey' ]
	};
	var upcomingClasses = new List('upcoming-classes', upcomingoptions);
	upcomingClasses.on('searchComplete', function(){
		//console.log(upcomingClasses.update().matchingItems.length);
		$('.classcount').html(upcomingClasses.update().matchingItems.length);
	});
});
</script>

<?php require('templates/footer.php'); ?>
