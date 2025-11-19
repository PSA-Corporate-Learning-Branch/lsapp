<?php 
require('inc/lsapp.php');
opcache_reset();
$courseid = (isset($_GET['courseid'])) ? $_GET['courseid'] : 0;
$course = getCourse($courseid);
getHeader();
?>
<title>Bulk Insert Class Service Requests | LSApp</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<body class="">
<?php if(canAccess()): ?>
<div class="container">
<div class="row justify-content-md-center">
<div class="col-md-8">
<h1 class="card-title">Request New Class Dates</h1>
<h2><a href="/lsapp/course.php?courseid=<?= $course[0] ?>"><?= $course[2] ?></a></h2>
<div>Delivery method: <?= $course[21] ?></div>
<div class="my-3">
	<button id="clone" class="btn btn-primary" data-count="1" data-cloneid="classdate1">
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
			<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
			<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
		</svg>
		Add new class
	</button>
</div>
<form name="" action="class-bulk-create.php" method="POST" enctype="multipart/form-data">
	<input type="hidden" id="CourseCode" name="CourseCode" value="<?= $course[0] ?>">
	<div id="datecontainer">
		<div class="row my-1 p-3 bg-dark-subtle rounded-3" id="classdate1">
			<div class="col-md-3">
				<label for="sd" class="sessionlabel">Start Date</label>
				<input class="form-control StartDate date" id="sd" type="date" name="StartDate[]" value="<?= date('Y-m-d') ?>">
			</div>
			<div class="col-md-2">
				<label for="st">Start time</label>
				<input class="form-control starttime" id="st" type="text" name="StartTime[]" value="<?= $course[30] ?>" >
			</div>
			<div class="col-md-2">
				<label for="et">End time</label>
				<input class="form-control endtime" id="et" type="text" name="EndTime[]" value="<?= $course[31] ?>" >
			</div>
			<div class="col-md-2">
				<label for="MinEnroll">Min</label>
				<input class="form-control" id="MinEnroll" type="text" name="MinEnroll[]" value="<?= $course[28] ?>" >
			</div>
			<div class="col-md-2">
				<label for="MaxEnroll">Max</label>
				<input class="form-control" id="MaxEnroll" type="text" name="MaxEnroll[]" value="<?= $course[29] ?>" >
			</div>
			<div class="col-md-6">
				<label for="WebinarLink">Webinar Link</label>
				<input class="form-control WebinarLink" id="WebinarLink" type="text" name="WebinarLink[]" value="" required>
			</div>
			<?php if($course[21] == 'Classroom'): ?>
				<div class="col-md-6">
					<label for="VenueCity">City</label>
					<select name="VenueCity[]" id="VenueCity" class="form-control mb-0" >
						<option value="">Choose a City</option>
						<!-- <option>Provided</option>-->
						<option data-region="LM">TBD - Other (see notes)</option>
						<option data-region="LM">TBD - Abbotsford</option>
						<option data-region="LM">TBD - Burnaby</option>
						<option data-region="LM">TBD - Burns Lake</option>
						<option data-region="VI">TBD - Campbell River</option>
						<option data-region="SBC">TBD - Castlegar</option>
						<option data-region="SBC">TBD - Chilliwack</option>
						<option data-region="SBC">TBD - Coquitlam</option>
						<option data-region="SBC">TBD - Cranbrook</option>
						<option data-region="NBC">TBD - Dawson Creek</option>
						<option data-region="NBC">TBD - Fort St. John</option>
						<option data-region="SBC">TBD - Kamloops</option>
						<option data-region="SBC">TBD - Kelowna</option>
						<option data-region="LM">TBD - Langley</option>
						<option data-region="NBC">TBD - Mackenzie</option>
						<option data-region="SBC">TBD - Merrit</option>
						<option data-region="VI">TBD - Nanaimo</option>
						<option data-region="SBC">TBD - Nelson</option>
						<option data-region="LM">TBD - New Westminster</option>
						<option data-region="LM">TBD - Penticton</option>
						<option data-region="SBC">TBD - Powell River</option>
						<option data-region="NBC">TBD - Prince George</option>
						<option data-region="NBC">TBD - Quesnel</option>
						<option data-region="NBC">TBD - Smithers</option>
						<option data-region="SBC">TBD - Squamish</option>
						<option data-region="LM">TBD - Surrey</option>
						<option data-region="SBC">TBD - Terrace</option>
						<option data-region="LM">TBD - Vancouver</option>
						<option data-region="SBC">TBD - Vernon</option>
						<option data-region="VI">TBD - Victoria</option>
						<option data-region="NBC">TBD - Williams Lake</option>
						<option data-region="NBC">TBD - Haida Gwaii</option>
					</select>
				</div>
			<?php endif ?>
			<div class="col-md-6">
				<label for="RequestNotes">Notes</label>
				<textarea class="form-control RequestNotes" id="RequestNotes" name="RequestNotes[]" value=""></textarea>
			</div>
		</div>
	</div>
	<input type="submit" name="submit" class="btn btn-block btn-lg btn-success text-uppercase my-3" value="Submit Service Requests">
</form>
<!-- <div><a href="https://gww.bcpublicservice.gov.bc.ca/lsapp/class-request.php?courseid=<?= $course[0] ?>">Feeling nostalgic? 1-at-a-time requests over here&hellip;</a></div> -->
</div>

<div class="col-md-4">
	<?php 
	$inactive = 0;
	$upcount = 0;
	$classes = getCourseClasses($course[0]);
	foreach($classes as $class):
		$today = date('Y-m-d');
		if($class[9] < $today) continue;
		if($class[1] == 'Inactive') $inactive++;
		$upcount++;
	endforeach;
	?>

	<div id="upcoming-classes">
		<h3><span class="classcount"><?= ($upcount - $inactive) ?></span>  Upcoming Classes</h3>

		<?php foreach($classes as $class): ?>
			<?php
			// We only wish to see classes which have an end date greater than today
			$today = date('Y-m-d');
			if($class[9] < $today) continue;
			?>

			<div class="my-1 p-2 bg-light-subtle rounded-3">
				<?php if($class[1] == 'Inactive'): ?>
					<span class="badge text-bg-warning bg-warning ">CANCELLED</span>
				<?php else: ?>
					<span class="badge text-bg-light"><?= $class[1] ?></span>
				<?php endif ?>
				<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
				<?php echo goodDateShort($class[8],$class[9]) ?>
				</a>
				<span class="classdate" style="display:none"><?= $class[8] ?></span>
				<?php if($class[4] == 'Dedicated'): ?>
					<span class="badge text-bg-light">Dedicated</span>
				<?php endif ?>
				<span><small><?= $class[7] ?></small></span>
			</div>

		<?php endforeach ?>

	</div> <!-- /upcoming-classes -->

</div> <!-- /col -->
</div>


<?php require('templates/javascript.php') ?>

<script>
	const button = document.getElementById('clone');

	button.addEventListener('click', (event) => {
		
		let count = button.getAttribute('data-count');
		let existingid = button.getAttribute('data-cloneid');
		let newcount = parseInt(count);
		newcount++;
		let newid = 'classdate' + newcount;	
		let myDiv = document.getElementById(existingid);
		let datecontainer = document.getElementById('datecontainer');
		let divClone = myDiv.cloneNode(true); // the true is for deep cloning
		divClone.id = newid;
		datecontainer.appendChild(divClone);
		button.setAttribute('data-cloneid',newid);
		button.setAttribute('data-count',newcount);

	});
</script>

<?php else: ?>
	<?php include('templates/noaccess.php') ?>
<?php endif ?>

<?php require('templates/footer.php') ?>