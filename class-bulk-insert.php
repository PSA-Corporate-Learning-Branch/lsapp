<?php 
require('inc/lsapp.php');
opcache_reset();
$courseid = (isset($_GET['courseid'])) ? $_GET['courseid'] : 0;
$course = getCourse($courseid);
getHeader();

$allpeople = getPeopleAll($filteractive = true);



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

<form name="" action="class-bulk-create.php" method="POST" enctype="multipart/form-data">
	<input type="hidden" id="CourseCode" name="CourseCode" value="<?= $course[0] ?>">
	<div id="datecontainer">
		<div class="row my-3 p-3 bg-light-subtle border rounded-3 shadow-sm" id="classdate-1">
			<div class="col-md-3">
				<div class="form-check">
					<label for="Dedicated-1" class="form-check-label">Dedicated</label>
					<input class="form-check-input" id="Dedicated-1" type="checkbox" name="Dedicated[]" value="Dedicated">
				</div>
			</div>
			<div class="col-md-3">
				<label for="sd-1" class="form-label sessionlabel">Start Date</label>
				<input class="form-control" id="sd-1" type="date" name="StartDate[]" value="<?= date('Y-m-d') ?>">
			</div>
			<div class="col-md-3">
				<label for="st-1" class="form-label">Start time</label>
				<input class="form-control" id="st-1" type="time" name="StartTime[]" value="<?= $course[30] ?>" step="900">
			</div>
			<div class="col-md-3">
				<label for="et-1" class="form-label">End time</label>
				<input class="form-control" id="et-1" type="time" name="EndTime[]" value="<?= $course[31] ?>" step="900">
			</div>
			<div class="col-md-2">
				<label for="MinEnroll-1" class="form-label">Min</label>
				<input class="form-control" id="MinEnroll-1" type="number" name="MinEnroll[]" value="<?= $course[28] ?>" >
			</div>
			<div class="col-md-2">
				<label for="MaxEnroll-1" class="form-label">Max</label>
				<input class="form-control" id="MaxEnroll-1" type="number" name="MaxEnroll[]" value="<?= $course[29] ?>" >
			</div>
			<div class="col-md-6">
				<label for="WebinarLink-1" class="form-label">Webinar Link</label>
				<input class="form-control WebinarLink" id="WebinarLink-1" type="text" name="WebinarLink[]" value="" required>
			</div>
			<?php if($course[21] == 'Classroom'): ?>
				<div class="col-md-6">
					<label for="VenueCity-1" class="form-label">City</label>
					<select name="VenueCity[]" id="VenueCity-1" class="form-select mb-0" >
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
				<label for="AddFacilitating-1" class="form-label">Facilitating</label>
				<a href="#" class="link-secondary" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="<small>Enter or search facilitator from the dropdown, then select Add.<br>Add facilitators one at a time.</small>">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
  						<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  						<path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
					</svg>
				</a>
				<div class="input-group">
					<input class="form-control" list="people-list" id="AddFacilitating-1" name="AddFacilitating" value="">
					<button id="AddButton-1" type="button" class="btn btn-secondary">Add</button>
				</div>
				<datalist id="people-list">
					<?php foreach($allpeople as $people): ?>
						<option value="<?= $people[0] ?>"><?= $people[2] ?></option>
					<?php endforeach; ?>
				</datalist>
				<input type="hidden" id="Facilitating-1" name="Facilitating[]" value="">
				<div id="BadgeContainer-1"></div>
			</div>
			<div class="col-md-6">
				<label for="RequestNotes-1" class="form-label">Notes</label>
				<textarea class="form-control RequestNotes" id="RequestNotes-1" name="RequestNotes[]" value=""></textarea>
			</div>
		</div>
	</div>
	<div class="my-3 d-flex justify-content-center">
		<button id="clone" class="btn btn-primary" type="button" data-count="1" data-cloneid="classdate-1">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
				<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
				<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
			</svg>
			Add new class
		</button>
	</div>
	<hr class="border">
	<div class="my-3 d-flex justify-content-center">
		<input type="submit" name="submit" class="btn btn-block btn-lg btn-success text-uppercase" value="Submit Service Requests">
	</div>
</form>
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

			<div class="my-1 p-2 bg-light-subtle border rounded-3">
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
	// Add Class
	const button = document.getElementById('clone');

	button.addEventListener('click', (event) => {
		
		let count = button.getAttribute('data-count');
		let existingid = button.getAttribute('data-cloneid');
		let newcount = parseInt(count);
		newcount++;
		let newid = 'classdate-' + newcount;	
		let myDiv = document.getElementById(existingid);
		let datecontainer = document.getElementById('datecontainer');
		let divClone = myDiv.cloneNode(true); // the true is for deep cloning
		divClone.id = newid;
		
		// Update our form element ids
		divClone.querySelectorAll('[id]').forEach(element => {
			// split on the hyphen so we can isolate the base of the id, 
			// then add the hypen and our new id on the end
			element.id = element.id.split('-')[0] + '-' + newcount;
		})

		// Update our form labels using the same approach
		divClone.querySelectorAll('label').forEach(element => {
			console.log(element);
			element.htmlFor = element.htmlFor.split('-')[0] + '-' + newcount;
		})		

		// Clear any copied badges
		divClone.querySelector(`#BadgeContainer-${newcount}`).innerHTML = '';

		datecontainer.appendChild(divClone);
		button.setAttribute('data-cloneid',newid);
		button.setAttribute('data-count',newcount);

		AddFacilitation(newcount);
	});

	// Add Facilitator
	function AddFacilitation(count) {
		const addFacilitatorButton = document.getElementById('AddButton-' + count);
		const addFacilitatorInput = document.getElementById('AddFacilitating-' + count);
		const facilitating = document.getElementById('Facilitating-' + count);
		const facilitatingBadges = document.getElementById('BadgeContainer-' + count);

		let selectedValues = [];

		addFacilitatorButton.addEventListener('click', () => {
			const value = addFacilitatorInput.value.trim();
			if (value && !selectedValues.includes(value)) {
				selectedValues.push(value);
				facilitating.value = selectedValues.join(',');

				const badge = document.createElement('span');
				badge.className = 'badge text-bg-secondary';
				badge.textContent = value;

				const removeBtn = document.createElement('button');
				removeBtn.type = 'button';
				removeBtn.className = 'btn-close btn-close-white ms-2';
				removeBtn.style.fontSize = '0.6rem';

				removeBtn.addEventListener('click', () => {
					selectedValues = selectedValues.filter(item => item !== value);
					facilitating.value = selectedValues.join(',');
					badge.remove();
				})

				badge.appendChild(removeBtn);
				facilitatingBadges.appendChild(badge);

				addFacilitatorInput.value = '';
			}
		});

	}
	
	AddFacilitation(1);

	const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
	const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

</script>

<?php else: ?>
	<?php include('templates/noaccess.php') ?>
<?php endif ?>

<?php require('templates/footer.php') ?>