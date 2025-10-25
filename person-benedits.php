<?php require('inc/lsapp.php') ?>
<?php opcache_reset() ?>
<?php $currentuser = LOGGED_IN_IDIR; ?>
<?php $idir = (isset($_GET['idir'])) ? $_GET['idir'] : 0; ?>
<?php $person = getPerson($idir) ?>
<?php $requests = getUserRequested($idir) ?>
<?php $assignments = getAdminAssigned($idir) ?>
<?php $allassignments = getAdminAssignedCount($idir) ?>
<?php $changes = getUserChanges($idir) ?>
<?php $facilitating = getUserFacilitating($idir) ?>
<?php $owned = getCoursesOwned($idir) ?>
<?php $tips = getTips() ?>
<?php 
$functions = getUserFunctions($idir); 
$categories = [];
foreach($functions as $fun) {
    array_push($categories,$fun[1]);
}
$categories = array_unique($categories);
//print_r($categories); exit;
?>






<?php getHeader() ?>
<title><?= $person[2] ?> | LSApp</title>
<?php getScripts() ?>
<style>
h1, h2 { 
	/* border-bottom: 1px solid #FFF; */
	color: #036; 
	text-shadow: 2px 2px 0 #FFF;
	
}
.bar {
	border-top-right-radius: 10px; 
	border-bottom-right-radius: 10px;
	box-shadow: 0 0 2px #666;
	height: 10px;
	margin: 0;
	padding: 0;
}

</style>
<?php getNavigation() ?>

<div class="container">
<div class="row mb-3 justify-content-md-center">

<?php if(sizeof($person)>0): ?>
<div class="col-md-6">







<div class="card shadow-lg mt-5 mb-3">
<div class="card-header">


<div class="float-end">
	<?php if(isAdmin()): ?>
		<a href="person-update.php?idir=<?= $person[0] ?>" class="btn btn-link float-end">Edit</a>
	<?php endif ?>
</div>
<!--IDIR,Role,Name,Email,Status,Phone,Title-->
<!--
<?php if($person[4] == 'Active'): ?>
<span class="badge badge-success"><?= $person[4] ?></span>
<?php else: ?>
<span class="badge badge-warning"><?= $person[4] ?></span>
<?php endif ?>
-->
<h1 class="card-title">
	<?= $person[2] ?> 
	<?php if($person[9] != 'Unspecified'): ?>
	<span style="font-size: 14px" class="text-lowercase"><?= $person[9] ?></span>
	<?php endif ?>
</h1>
<?php if(isset($person[6])): ?>
<h2 class="card-subtitle"><?= $person[6] ?> on the
<?php endif ?>
<?php if(isset($person[1])): ?>
<?php 
$team = '';
if($person[1] == 'Governance') $team = 'Governance, Planning, &amp; Evaluation';
if($person[1] == 'Employees') $team = 'Corp. Learning - All Employees';
if($person[1] == 'Leaders') $team = 'Corp. Learning - People Leaders';
if($person[1] == 'Operations') $team = 'Operations &amp; Technology';
?>
<a class="" href="/lsapp/teams.php?teamname=<?= $person[1] ?>"><?= $team ?> Team</a>
<?php endif ?>
</h2>
</div>
<div class="card-body">
<div class="mb-4">
<a href="mailto:<?= $person[3] ?>" class="d-inline-block px-3 py-1 mr-1 bg-light-subtle shadow-sm rounded-3">Email<!--<?= $person[3] ?>--></a> 
<?php if(isset($person[5])): ?>
<a href="tel:<?= $person[5] ?>" class="d-inline-block px-3 py-1 bg-light-subtle shadow-sm rounded-3"><?= $person[5] ?></a>
<?php endif ?>
<a href="https://teams.microsoft.com/l/chat/0/0?users=<?= $person[3] ?>" target="_blank" class="d-inline-block px-3 py-1 bg-light-subtle shadow-sm rounded-3">MS Teams</a>
</div>



<!-- <div>Conscious Persona <a href="#" style="background-color: #f2f2f2; border-radius: 50%; display: inline-block; font-size: 12px; height: 15px; text-align: center; width: 15px;">?</a></div> -->
<?php
$colors = explode('|',$person[10]);
// first value is not a color; is a binary flag 0 or 1 to enable
// the sharing of these values or not
$prevpercnt = 0;
$cc = 0; // setting to zero with count++ last means skipping 1st value
// Cool Blue, Earth Green, Sunshine Yellow, Fiery Red
foreach($colors as $percnt) {
	
	if($percnt > $prevpercnt) {
		
		if($cc == 1) {
			$type = 'Cool Blue';
		} elseif($cc == 2) {
			$type = 'Earth Green';
		} elseif($cc == 3) {
			$type = 'Sunshine Yellow';
		} elseif($cc == 4) {
			$type = 'Fiery Red';
		}
	}
	//echo 'c' . $percnt . '-' . $prevpercnt;
	$prevpercnt = $percnt;
	$cc++;
}
if($colors[0] > 0):
?>

<a class="" href="#" data-toggle="modal" data-target="#tips">
<div class="mb-0 bg-light-subtle shadow-sm" style="border-radius: 10px; border: 1px solid #F1F1F1; margin: 0; padding: 0; position: relative; width: 100%;">
	<div style="border-right: 2px solid black; height: 100%; position:absolute; width: 50%  "></div>
	<div id="bluebar" class="bar" style="border-top-left-radius: 10px; background-color: DodgerBlue; width: <?= $colors[1] ?>%"></div>
	<div id="greenbar" class="bar" style="background-color: MediumSeaGreen; width: <?= $colors[2] ?>%"></div>
	<div id="yellowbar" class="bar" style="background-color: gold; width: <?= $colors[3] ?>%"></div>
	<div id="redbar" class="bar" style="border-bottom-left-radius: 10px; background-color: Crimson; width: <?= $colors[4] ?>%"></div>
</div>
</a>

	<?php if($idir == $currentuser): ?>

		<details class="rounded-3">

			<summary class="ml-3 text-left" style="color: #003366; font-size:12px;">Adjust graph</summary>
			<!-- Insights Discovery Profile Graph -->
			
			<form id="optin" class="m-3 p-3 bg-light-subtle shadow rounded-3 text-left" method="post" action="person-update.php">

			<div>
				<input type="range" id="blue" name="blue" class="colorslider" 
					min="0" max="100" value="<?= $colors[1] ?>">
				<span style="background-color: DodgerBlue; border-radius: 5px; box-shadow: 0 0 2px #333; color: #FFF; display: inline-block; padding: 0 10px;">
					
				<span id="blueval"></span>
				<label for="blue" style="margin: 0;">Blue</label>
					
				</span>
			</div>
			<div>
				<input type="range" id="green" name="green" class="colorslider" 
					min="0" max="100" value="<?= $colors[2] ?>">
				<span style="background-color: MediumSeaGreen; border-radius: 5px; box-shadow: 0 0 2px #333; color: #FFF; display: inline-block; padding: 0 10px;">
				<span id="greenval"></span>	
				<label for="green" style="margin: 0;">Green</label>
					
				</span>
			</div>
			<div>
				<input type="range" id="yellow" name="yellow" class="colorslider" 
					min="0" max="100" value="<?= $colors[3] ?>">
				<span style="background-color: gold; border-radius: 5px; box-shadow: 0 0 2px #333; color: #111; display: inline-block; padding: 0 10px;">
				<span id="yellowval"></span>	
				<label for="yellow" style="margin: 0;">Yellow</label>
					
				</span>
			</div>
			<div>
				<input type="range" id="red" name="red" class="colorslider" 
					min="0" max="100" value="<?= $colors[4] ?>">
				<span style="background-color: Crimson; border-radius: 5px; box-shadow: 0 0 2px #333; color: #FFF; display: inline-block; padding: 0 10px;">
					<span id="redval"></span>	
					<label for="red" style="margin: 0;">Red</label>
				</span>
			</div>
			<div class="p-3 bg-light-subtle rounded-3 shadow-sm my-2">
				<?php if($colors[0] > 0) $checked = 'checked' ?? '' ?>
				<input type="checkbox" id="optinout" name="optinout" <?= $checked ?> value="<?= $colors[0] ?>">
				<label for="optinout" style="margin:0">Share your graph?</label>
			</div>
			<!-- IDIR,Role,Name,Email,Status,Phone,Title,Super,Manager,Pronouns,Colors -->
			<input type="hidden" name="IDIR" id="IDIR" value="<?= $person[0] ?>">
			<input type="hidden" name="Role" id="role" value="<?= $person[1] ?>">
			<input type="hidden" name="Name" id="Name" value="<?= $person[2] ?>">
			<input type="hidden" name="Email" id="Email" value="<?= $person[3] ?>">
			<input type="hidden" name="Status" id="Status" value="<?= $person[4] ?>">
			<input type="hidden" name="Phone" id="Phone" value="<?= $person[5] ?>">
			<input type="hidden" name="Title" id="Title" value="<?= $person[6] ?>">
			<input type="hidden" name="Super" id="Super" value="<?= $person[7] ?>">
			<input type="hidden" name="Manager" id="Manager" value="<?= $person[8] ?>">
			<input type="hidden" name="Pronouns" id="Pronouns" value="<?= $person[9] ?>">
			<input type="hidden" name="Colors" id="Colors" value="<?= $person[10] ?>">
			<button class="btn btn-primary">Save Graph</button>
			</form>
		</details>
		<script>
			const formsub = document.getElementById('optin')
			formsub.addEventListener('submit', (e) => {
				e.preventDefault()
				formsub.submit(function(event){ 
					event.preventDefault() 
					return false
				})
			});

			const optvalue = document.querySelector("#optinout")

			optvalue.addEventListener("input", (event) => {
				if(optvalue.value == 0) {
					inorout = 0
					optvalue.value = 1
				} else {
					inorout = 1
					optvalue.value = 0
				}
				//console.log(inorout)
				updateInput()
			})

			const bvalue = document.querySelector("#blueval")
			const binput = document.querySelector("#blue")
			bvalue.textContent = binput.value + '%'
			binput.addEventListener("input", (event) => {
				bvalue.textContent = event.target.value + '%'
				let bbar = document.querySelector("#bluebar")
				bbar.style.width = event.target.value + '%'
				updateInput()
			})

			const gvalue = document.querySelector("#greenval")
			const ginput = document.querySelector("#green")
			gvalue.textContent = ginput.value + '%'
			ginput.addEventListener("input", (event) => {
				gvalue.textContent = event.target.value + '%'
				let gbar = document.querySelector("#greenbar")
				gbar.style.width = event.target.value + '%'
				updateInput()
			})

			const yvalue = document.querySelector("#yellowval")
			const yinput = document.querySelector("#yellow")
			yvalue.textContent = yinput.value + '%'
			yinput.addEventListener("input", (event) => {
				yvalue.textContent = event.target.value + '%'
				let ybar = document.querySelector("#yellowbar")
				ybar.style.width = event.target.value + '%'
				updateInput()
			})

			const rvalue = document.querySelector("#redval")
			const rinput = document.querySelector("#red")
			rvalue.textContent = rinput.value + '%'
			rinput.addEventListener("input", (event) => {
				rvalue.textContent = event.target.value + '%'
				let rbar = document.querySelector("#redbar")
				rbar.style.width = event.target.value + '%'
				updateInput()
			})
			function updateInput() {
				let optvalue = document.querySelector("#optinout")
				let cs = document.getElementsByClassName('colorslider')
				let data = optvalue.value + '|' + cs[0].value + '|' + cs[1].value + '|' + cs[2].value + '|' + cs[3].value
				let submitted = document.querySelector("#Colors")
				submitted.value = data
			}
		</script>




	<?php endif ?>
		<!-- <div class="mt-3 p-3 bg-light-subtle text-left rounded-3">
		<?php foreach($tips as $t): ?>
			<?php if($t[1] == $type): ?>
				<div><small>Tips for communicating with <?= $type ?>:</small></div>
				<div><?= $t[2] ?> <a class="badge badge-light" href="#" data-toggle="modal" data-target="#tips">More tips</a></div>
			<?php endif ?>
		<?php endforeach ?>
		</div> -->
	<?php else: ?>
		<?php if($idir == $currentuser): ?>
			<form id="optin" class="" method="post" action="person-update.php">
			<!-- IDIR,Role,Name,Email,Status,Phone,Title,Super,Manager,Pronouns,Colors -->
			<input type="hidden" name="IDIR" id="IDIR" value="<?= $person[0] ?>">
			<input type="hidden" name="Role" id="role" value="<?= $person[1] ?>">
			<input type="hidden" name="Name" id="Name" value="<?= $person[2] ?>">
			<input type="hidden" name="Email" id="Email" value="<?= $person[3] ?>">
			<input type="hidden" name="Status" id="Status" value="<?= $person[4] ?>">
			<input type="hidden" name="Phone" id="Phone" value="<?= $person[5] ?>">
			<input type="hidden" name="Title" id="Title" value="<?= $person[6] ?>">
			<input type="hidden" name="Super" id="Super" value="<?= $person[7] ?>">
			<input type="hidden" name="Manager" id="Manager" value="<?= $person[8] ?>">
			<input type="hidden" name="Pronouns" id="Pronouns" value="<?= $person[9] ?>">
			<input type="hidden" name="Colors" id="Colors" value="<?= $person[10] ?>">
			<div>
				<?php if($colors[0] > 0) $checked = 'checked' ?? '' ?>
				<input type="checkbox" id="optinout" name="optinout" <?= $checked ?> value="<?= $colors[0] ?>">
				<label for="optinout">Share your graph?</label>
			</div>
			<!-- <button class="btn btn-primary">Save</button> -->
			</form>
			<script>
			let optvalue = document.querySelector("#optinout")
			optvalue.addEventListener("input", (event) => {
				updateInput()
				document.getElementById('optin').submit()
			})
			function updateInput() {
				let optvalue = document.querySelector("#optinout")
				let submitted = document.querySelector("#Colors")
				let newcode = '';
				if(optvalue.value == 1) {
					let newcode = submitted.value.replace('0|','1|');
					submitted.value = newcode
				} else {
					let newcode = submitted.value.replace('0|','1|');
					submitted.value = newcode
				}
			}
			</script>


<?php endif ?>
<?php endif; // end of opt in check ?>

<!-- I doubt anyone was actually using this feature so I'm commenting out until 
someone tells to me enable it again
<div class="dropdown">
  <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    Audits
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
    <a class="dropdown-item" href="change-requests-by.php?idir=<?= $person[0] ?>">All completed class change requests</a>
	<a class="dropdown-item" href="classes-processed-by.php.php?idir=<?= $person[0] ?>">All processed service requests</a>
	<a class="dropdown-item" href="classes-requested-by.php?idir=<?= $person[0] ?>">All requested classes</a>
  </div>
</div>
-->







<?php if(sizeof($categories)>0): ?>

<h3 class="mt-4 mb-2">Functions</h3>
<?php foreach($categories as $cat): ?>

<div><strong><?= $cat ?></strong></div>
<?php $active = 'btn-light' ?>
<?php foreach($functions as $fun): ?>
<?php if($cat == $fun[1]): ?>
<a class="btn btn-sm btn-light" href="function-map.php?functionid=<?= $fun[0] ?>"><?= $fun[2] ?></a> 
<?php endif ?>
<?php endforeach ?>

<?php endforeach ?>

<?php endif ?>


</div> <!-- /.card-body -->
		</div> <!-- /.card -->


<h2 class="mt-5">Courses Owned</h2>
<p>Assigned as the owner of these courses.</p>
<ul class="list-group mb-3">
<?php foreach($owned as $course): ?>
<li class="list-group-item">
	<?php if($course[1] == 'Active'): ?>
	<a href="class-request.php?courseid=<?= $course[0] ?>" class="float-end btn btn-light ml-3">New Date</a>
	<?php endif ?>
	<a href="course.php?courseid=<?= $course[0] ?>"><?= $course[2] ?></a>
	<?php if($course[1] == 'Inactive'): ?>
	<div><span class="badge badge-dark">Inactive</span></div>
	<?php endif ?>
</li>
<?php endforeach ?>
</ul>





</div>




<?php if(sizeof($owned)>0 || sizeof($facilitating)>0 || sizeof($requests)>0 || sizeof($changes)>0 || sizeof($assignments)>0): ?>

<div class="col-md-6">



<?php if(sizeof($facilitating)>0): ?>

<a href="subscribe.php" class="float-end">Subscribe</a>
<h2 class="mt-5">Facilitating</h2>
<p>Assigned as the facilitator for these upcoming classes.</p>
<table class="table table-sm table-striped">
<tr>
	<th>Class Date</th>
	<th>Course</th>
	<th>City</th>
	<th>Status</th>
	<th>Enrolled</th>
</tr>
<?php $today = date('Y-m-d') ?>
<?php foreach($facilitating as $at): ?>
<?php if($at[8] > $today): ?>
<?php if($at[1] != 'Inactive'): ?>
<tr>
	<td width="120">
		<a href="/lsapp/class.php?classid=<?= h($at[0]) ?>">
			<?php echo goodDateShort($at[8],$at[9]) ?> 
		</a>
	</td>
	<td>
		<a href="course.php?courseid=<?= h($at[5]) ?>"><?= h($at[6]) ?></a>
	</td>
	<td>
		<a href="city.php?name=<?php echo urlencode($at[25]) ?>"><?= h($at[25]) ?></a>
	</td>	
	<td>
		<span class="badge badge-light"><?= h($at[1]) ?></span>
	</td>
	<td class="text-center">
		<span class="badge badge-secondary"><?= h($at[18]) ?></span>
	</td>
</tr>
<?php endif ?>
<?php endif ?>
<?php endforeach ?>
</table>


<?php endif ?>



<?php if(sizeof($requests)>0): ?>




<h2 class="mt-5">Classes Requested</h2>
<p>Classes requested for a course, but not yet entered in the Learning System</p>
<table class="table table-sm table-striped">
<?php foreach($requests as $rq): ?>
<tr>
	
	<td width="120">
		<a href="/lsapp/class.php?classid=<?= h($rq[0]) ?>">
			<?php echo goodDateShort($rq[8],$rq[9]) ?> 
		</a>
	</td>
	<td>
		<a href="course.php?courseid=<?= h($rq[5]) ?>"><?= h($rq[6]) ?></a>
	</td>
	<td>
		<a href="city.php?name=<?= h($rq[25]) ?>"><?= h($rq[25]) ?></a>
	</td>
</tr>
<?php endforeach ?>
</table>


<?php endif ?>

<?php if(sizeof($changes)>0): ?>


<h2 class="mt-5">Class Change Requests</h2>
<p>Change requests for classes that have not been addressed yet.</p>
<!-- //creqID,ClassID,CourseName,StartDate,City,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request -->
<table class="table table-sm table-striped">
<?php foreach($changes as $change): ?>
<div>
	
	<div>
		<a href="/lsapp/class.php?classid=<?= h($change[1]) ?>">
			<?php echo goodDateShort(h($change[3])) ?>
		<?= h($change[2]) ?>
		<?= h($change[4]) ?></a>
		<div class="alert alert-secondary">
		<?= h($change[10]) ?>
		</div>
	</div>
</div>
<?php endforeach ?>
</table>


<?php endif ?>


<?php if(sizeof($assignments) > 0): ?>


<?php $allassigned = count($assignments) ?>
<h2 class="mt-5">Assigned Service Requests<span class="badge badge-light"><?= $allassigned ?></span></h2>
<p>Classes assigned and <em>not yet active.</em></p>
<?php $ccount = 0 ?>
<div id="requested">
<table class="table table-sm table-striped">
<thead>
<tr>
	<th><a href="#" class="sort" data-sort="date">Date</th>
	<th><a href="#" class="sort" data-sort="course">Course</a></th>
</tr>
</thead>
<tbody class="list">
<?php foreach($assignments as $ass): ?>
<?php $ccount++ ?>
<?php if($ass[1] == 'Requested'): ?>
<?php $coursedeets = getCourse($ass[5]) ?>
<tr>
	<td class="date">
		<a href="/lsapp/class.php?classid=<?= h($ass[0]) ?>">
			<?php echo goodDateShort($ass[8],$ass[9]) ?> 
		</a>
	</td>
	<td class="course">
		<a href="course.php?courseid=<?= h($ass[5]) ?>">
			<?= h($ass[6]) ?> 
		</a>
	</td>

</tr>
<?php endif ?>
<?php endforeach ?>
</tbody>
</table>

</div>

<?php endif ?>


<?php if(sizeof($owned)>0): ?>


<?php endif ?>



</div>

<?php endif ?>

</div>





<div class="modal fade" id="tips" tabindex="-1" aria-labelledby="tipsLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="exampleModalLabel">Tips for communicating in color</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body text-left">
	<dl>
<?php foreach($tips as $t): ?>
	<?php 
	$bgcolor = '';
	$txtcolor = '#FFF';
	if($t[1] == 'Fiery Red') {
		$bgcolor = 'Crimson';
	} else if($t[1] == 'Sunshine Yellow') { 
		$bgcolor = 'Gold';
		$txtcolor = '#111';
	} else if($t[1] == 'Earth Green') { 
		$bgcolor = 'MediumSeaGreen';
	} else if($t[1] == 'Cool Blue') { 
		$bgcolor = 'DodgerBlue';
	}
	?>
	<dt class="p-3 rounded-3" style="background-color: <?= $bgcolor ?>; color: <?= $txtcolor ?>">
		<?= $t[1] ?>
	</dt>
	<dd class="p-3">
		<?= $t[2] ?>
	</dd>
<?php endforeach ?>
</dl>
</div>
</div>
</div>
</div>

<?php $tips = getTips(); ?>
<div class="modal fade" id="tips" tabindex="-1" aria-labelledby="tipsLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="exampleModalLabel">Tips for communicating in color</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body text-left">
	<dl>
<?php foreach($tips as $t): ?>
	<?php 
	$bgcolor = '';
	$txtcolor = '#FFF';
	if($t[1] == 'Fiery Red') {
		$bgcolor = 'Crimson';
	} else if($t[1] == 'Sunshine Yellow') { 
		$bgcolor = 'Gold';
		$txtcolor = '#111';
	} else if($t[1] == 'Earth Green') { 
		$bgcolor = 'MediumSeaGreen';
	} else if($t[1] == 'Cool Blue') { 
		$bgcolor = 'DodgerBlue';
	}
	?>
	<dt class="p-3 rounded-3" style="background-color: <?= $bgcolor ?>; color: <?= $txtcolor ?>">
		<?= $t[1] ?>
	</dt>
	<dd class="p-3">
		<?= $t[2] ?>
	</dd>
<?php endforeach ?>
</dl>
</div>
</div>
</div>
</div>

<?php if(isSuper()): ?>
<!--
		<form method="post" action="people-controller.php" class="persondel">
			<input type="hidden" name="idir" id="idir" value="<?= $idir ?>">
			<input type="hidden" name="action" id="action" value="delete">
			<input type="submit" class="btn btn-danger btn-sm" value="Delete User">
		</form>
		-->
<?php endif ?>


<?php else: ?>
<div class="col-md-6">
	<h2>Person Not Found</h2>
	<p>Must be playin' hooky ;)</p>
	<p>Email the Learning Support Admin Team <<a href="mailto:learning.centre.admin@gov.bc.ca">learning.centre.admin@gov.bc.ca</a>> with any questions or concerns.</p>
	<p><img src="img/TrollFace.jpg" width="300px"></p>
</div>	
<?php endif ?>

</div>


<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){
	var options = {
		valueNames: ['startdate', 'course']
	};
	var requested = new List('requested', options);
});
</script>

<?php require('templates/footer.php') ?>