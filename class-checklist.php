<?php require('inc/lsapp.php') ?>
<?php $classid = (isset($_GET['classid'])) ? $_GET['classid'] : 0; ?>
<?php $deets = getClass($classid) ?>
<?php getHeader() ?>

<title>
<?php if($deets[4] == 'Dedicated'): ?>
DEDICATED | 
<?php endif ?>
<?php if($deets[7]): ?>
<?= h($deets[7]) ?> | 
<?php endif ?>
<?php print goodDateLong($deets[8],$deets[9]) ?> | 
<?= h($deets[45]) ?> <?= h($deets[6]) ?>. 
<?php $tbdtest = explode('TBD - ',$deets[25]) ?> 
<?php if(isset($tbdtest[1])): ?>
<?= h($deets[25]) ?> 
<?php else: ?>
<?= h($deets[24]) ?> in <?= h($deets[25]) ?>. 
<?php endif ?>
<?php if($deets[14]): ?>
<?= h($deets[14]) ?> facilitating. 
<?php else: ?> 
Unknown facilitating. 
<?php endif ?>
</title>
<style>
@media print { 

    .container,
	.row,
	.col-md-12,
	.col-md-8,
	.col-md-4 { 
		display: block;
		width: auto; 
	}
    hr { page-break-after: always; }
}
#checklist ul {
  list-style: none;
}
#checklist ul li {
	font-size: 22px;
}
#checklist ul li:before {
  content: "\2610";
  font-size: 30px;
}
</style>
<?php getScripts() ?>

<body>

<?php getNavigation() ?>
<?php if(canAccess()): ?>

<div class="container" id="checklist">
<div class="row justify-content-md-center mb-3">


<?php if(isset($deets[0])): ?>


<div class="col-md-12">

	<div class="float-end">
		<?php if($deets[44]): ?>
		Assigned to <?= $deets[44] ?>
		<?php else: ?>
		Not Assigned
		<?php endif ?>
	</div>
	<?php if($deets[4] == 'Dedicated'): ?>
	<span class="badge badge-dark" style="background-color: #333">
		DEDICATED
	</span>
	<?php endif ?>
	<?php if($deets[7]): ?>
	<span class="badge badge-dark" style="background-color: #333">
		<?= $deets[7] ?>
	</span>
	<?php endif ?>
	<?php if($deets[4] == 'Dedicated' && $deets[7]): ?>
	<span class="badge badge-danger" title="A dedicated class should not have an item number">
		???
	</span>
	<?php endif ?>
	
	<span style="text-transform: uppercase"><?= $deets[1] ?></span>
<div>

<?php if($deets[14]): ?>
<?= $deets[14] ?> facilitating
<?php else: ?>
Unknown facilitating
<?php endif ?>

a <?= strtolower($deets[45]) ?> 
</div>
<div class="row">
<div class="col-md-8">
<h1 class="mb-0"><?= $deets[6] ?></h1>

<?php $tbdtest = explode('TBD - ',$deets[25]) ?>
<?php if(isset($tbdtest[1])): ?>
	<?= $deets[25] ?>
<?php else: ?>
	<?= $deets[24] ?> in <?= $deets[25] ?>
<?php endif ?>

<h3>
<?php print goodDateLong($deets[8],$deets[9]) ?>
 <small><?= $deets[10] ?></small>
</h3>

Enrolled <span class="badge badge-dark"><?= $deets[18] ?></span> 
Min/Max <span class="badge badge-dark"><?= $deets[11] ?>/<?= $deets[12] ?></span>
Waitlisted <span class="badge badge-dark">2</span> 
<!--<div>
Created on <?= $deets[2] ?> by <?= $deets[3] ?><br>
Last modified on <?= $deets[42] ?> by <?= $deets[43] ?>
</div>-->
</div>
<div class="col-md-4">
<h3 class="card-title">
	Notes 
</h3>
<div>
<small><?= $deets[3] ?> said on submission:</small><br>
<?= $deets[32] ?>
</div>
<?php $notes = getNotes($classid) ?>
<?php if(isset($notes)): ?>
<?php foreach($notes as $note): ?>
<div>
<!-- creqID,ClassID,Date,NotedBy,Note-->
	<small>On <?= $note[2] ?> <?= $note[3] ?> said:</small><br>
	<?= $note[4] ?>
</div>
<?php endforeach ?>
<?php endif ?>

</div>
</div>

<h1 class="my-3">Outgoing Checklist</h1>
<ul class="list-group">
<li class="list-group-item">Print class rosters through ELM
<li class="list-group-item">Print checklist from app
<li class="list-group-item">Pack boxes
<li class="list-group-item">Adjust inventory in app
<li class="list-group-item">Assign any AV items in app
<li class="list-group-item">Weigh boxes
<li class="list-group-item">Create incoming waybills (If Purolator, schedule a pickup)
<li class="list-group-item">Create outgoing waybills
<li class="list-group-item">Add resulting track/waybill to app
<li class="list-group-item">Print incoming/outgoing box labels
<li class="list-group-item">Include labels, waybills, rosters, and instructions in the box
<li class="list-group-item">Change status to "Shipped"
<li class="list-group-item">Send venue notification email
<?php if($deets[23] == 186 || $deets[23] == 188 || $deets[23] == 239): ?>
<li class="list-group-item">Supply copy of rosters to front-desk security
<?php endif ?>

</ul>




<hr style="page-break-after: always !important">



<div class="col-md-12">

	<div class="float-end">
		<?php if($deets[44]): ?>
		Assigned to <?= $deets[44] ?>
		<?php else: ?>
		Not Assigned
		<?php endif ?>
	</div>
	<?php if($deets[4] == 'Dedicated'): ?>
	<span class="badge badge-dark" style="background-color: #333">
		DEDICATED
	</span>
	<?php endif ?>
	<?php if($deets[7]): ?>
	<span class="badge badge-dark" style="background-color: #333">
		<?= $deets[7] ?>
	</span>
	<?php endif ?>
	<?php if($deets[4] == 'Dedicated' && $deets[7]): ?>
	<span class="badge badge-danger" title="A dedicated class should not have an item number">
		???
	</span>
	<?php endif ?>
	
	<span style="text-transform: uppercase"><?= $deets[1] ?></span>
<div>

<?php if($deets[14]): ?>
<?= $deets[14] ?> facilitating
<?php else: ?>
Unknown facilitating
<?php endif ?>

a <?= strtolower($deets[45]) ?> 
</div>
<div class="row">
<div class="col-md-8">
<h1 class="mb-0"><?= $deets[6] ?></h1>

<?php $tbdtest = explode('TBD - ',$deets[25]) ?>
<?php if(isset($tbdtest[1])): ?>
	<?= $deets[25] ?>
<?php else: ?>
	<?= $deets[24] ?> in <?= $deets[25] ?>
<?php endif ?>

<h3>
<?php print goodDateLong($deets[8],$deets[9]) ?>
 <small><?= $deets[10] ?></small>
</h3>
		
Min/Max <span class="badge badge-dark"><?= $deets[11] ?>/<?= $deets[12] ?></span>
<?php if($deets[4] == 'Dedicated'): ?>
<div class="alert alert-warning mt-3">
<h3>Please ensure to gather employee IDs at sign in</h3>
<div><em>Download the Ad Hoc attendance spreadsheet on LSApp</em></div>
</div>
<?php else: ?>
Enrolled <span class="badge badge-dark"><?= $deets[18] ?></span> 
<?php endif ?>
<!--<div>
Created on <?= $deets[2] ?> by <?= $deets[3] ?><br>
Last modified on <?= $deets[42] ?> by <?= $deets[43] ?>
</div>-->
<?php $maxplustwo = $deets[18] + 2 ?>
<div>Max Participants +2: <span class="badge badge-dark"><?= $maxplustwo ?></span></div>
</div>
<div class="col-md-4">
<h3 class="card-title">
	Notes 
</h3>
<div>
<small><?= $deets[3] ?> said on submission:</small><br>
<?= $deets[32] ?>
</div>
<?php $notes = getNotes($classid) ?>
<?php if(isset($notes)): ?>
<?php foreach($notes as $note): ?>
<div>
<!-- creqID,ClassID,Date,NotedBy,Note-->
	<small>On <?= $note[2] ?> <?= $note[3] ?> said:</small><br>
	<?= $note[4] ?>
</div>
<?php endforeach ?>
<?php endif ?>

</div>
</div>

<?php $checks = getChecklist($deets[5]) ?>
<!-- 
0-checklistID, 1-Manuals,2-Handouts,3-CourseName,4-Resources,5-StandardSupplyKit,6-AdditionalSupplies,7-ProjectorType,8-AdditionalTech,9-AudioSpeakers,
10-AttendanceRoster,11-Equipment,12-RoomSetup,13-Notes,14-OffCampusShipping,15-OffCampusNotes,16-OffCampusEquipment,17-OffCampusRoomSetup

-->
	<?php if(isAdmin()): ?>
	<div class="float-end">
		<a href="checklist-update.php?courseid=<?= $deets[5] ?>" class="btn btn-success">Edit</a>
	</div>
	<?php endif ?>
<h2 class="my-3">Outgoing Checklist</h2>
<table class="table table-sm" id="coursechecklist">
<tbody>
<?php if($checks[1]): ?>
<tr><td class="font-weight-bold text-right">Manuals</td><td><?= $checks[1] ?></td></tr>
<?php endif ?>
<?php if($checks[2]): ?>
<tr><td class="font-weight-bold text-right">Handouts</td><td><?= $checks[2] ?></td></tr>
<?php endif ?>
<?php if($checks[4]): ?>
<tr><td class="font-weight-bold text-right">Resources</td><td><?= $checks[4] ?></td></tr>
<?php endif ?>
<?php if($checks[5]): ?>
<tr><td class="font-weight-bold text-right">StandardSupplyKit</td><td><?= $checks[5] ?></td></tr>
<?php endif ?>
<?php if($checks[6]): ?>
<tr><td class="font-weight-bold text-right">AdditionalSupplies</td><td><?= $checks[6] ?></td></tr>
<?php endif ?>
<?php
if($checks[7] != 'on') {
	$proreq = 'Not required';
} else {
	$proreq = '<ul><li>Required</li></ul>';
}
?>
<tr><td class="font-weight-bold text-right">Projector Required</td><td><?= $proreq ?></td></tr>

<?php if($checks[8]): ?>
<tr><td class="font-weight-bold text-right">AdditionalTech</td><td><?= $checks[8] ?></td></tr>
<?php endif ?>
<tr><td class="font-weight-bold text-right">AttendanceRoster</td><td><ul><li>Included</li></ul></td></tr>
<?php if($deets[23] == 186 || $deets[23] == 239 || $deets[23] == 188): ?> 
<tr><td class="font-weight-bold text-right">RoomSetup</td><td><?= $checks[12] ?></td></tr>
<tr><td class="font-weight-bold text-right">Notes</td><td><?= $checks[13] ?></td></tr>
<tr><td class="font-weight-bold text-right">Equipment</td><td><?= $checks[11] ?></td></tr>
<?php else: ?>
<tr><td class="font-weight-bold text-right">RoomSetup</td><td><?= $checks[17] ?></td></tr>
<!--<tr><td class="font-weight-bold text-right">OffCampusShipping</td><td><?= $checks[14] ?></td></tr>-->
<tr><td class="font-weight-bold text-right">Notes</td><td><?= $checks[15] ?></td></tr>
<tr><td class="font-weight-bold text-right">Equipment</td><td><?= $checks[16] ?></td></tr>
<?php endif ?>
</tbody>
</table>







<hr style="page-break-after: always !important">



	<div class="float-end">
		<?php if($deets[44]): ?>
		Assigned to <?= $deets[44] ?>
		<?php else: ?>
		Not Assigned
		<?php endif ?>
	</div>
	<?php if($deets[4] == 'Dedicated'): ?>
	<span class="badge badge-dark" style="background-color: #333">
		DEDICATED
	</span>
	<?php endif ?>
	<?php if($deets[7]): ?>
	<span class="badge badge-dark" style="background-color: #333">
		<?= $deets[7] ?>
	</span>
	<?php endif ?>
	<?php if($deets[4] == 'Dedicated' && $deets[7]): ?>
	<span class="badge badge-danger" title="A dedicated class should not have an item number">
		???
	</span>
	<?php endif ?>
	
	<span style="text-transform: uppercase"><?= $deets[1] ?></span>
<div>

<?php if($deets[14]): ?>
<?= $deets[14] ?> facilitating
<?php else: ?>
Unknown facilitating
<?php endif ?>

a <?= strtolower($deets[45]) ?> 
</div>
<div class="row">
<div class="col-md-8">
<h1 class="mb-0"><?= $deets[6] ?></h1>

<?php $tbdtest = explode('TBD - ',$deets[25]) ?>
<?php if(isset($tbdtest[1])): ?>
	<?= $deets[25] ?>
<?php else: ?>
	<?= $deets[24] ?> in <?= $deets[25] ?>
<?php endif ?>

<h3>
<?php print goodDateLong($deets[8],$deets[9]) ?>
 <small><?= $deets[10] ?></small>
</h3>
		
Enrolled <span class="badge badge-dark"><?= $deets[18] ?></span> 
Min/Max <span class="badge badge-dark"><?= $deets[11] ?>/<?= $deets[12] ?></span>
Waitlisted <span class="badge badge-dark">2</span> 
<!--<div>
Created on <?= $deets[2] ?> by <?= $deets[3] ?><br>
Last modified on <?= $deets[42] ?> by <?= $deets[43] ?>
</div>-->
</div>
<div class="col-md-4">
<h3 class="card-title">
	Notes 
</h3>
<div>
<small><?= $deets[3] ?> said on submission:</small><br>
<?= $deets[32] ?>
</div>
<?php $notes = getNotes($classid) ?>
<?php if(isset($notes)): ?>
<?php foreach($notes as $note): ?>
<div>
<!-- creqID,ClassID,Date,NotedBy,Note-->
	<small>On <?= $note[2] ?> <?= $note[3] ?> said:</small><br>
	<?= $note[4] ?>
</div>
<?php endforeach ?>
<?php endif ?>
</div>
</div>



<h1 class="my-3">Incoming Checklist</h1>
<ul>
<li>Update status in LSApp</li>
<li>Unpack box
<ul>
	<li>Count returned materials and adjust inventory</li>
	<li>Attendance returned?
	<ul>
		<li>No 
		<ul>
			<li>record a note stating it didn't come back
			<li>Investigate with facilitator (CC program manager if repeatedly missing)
			<li>Inform Nancy
		</ul>
		</li>
		<li>Yes
		<ul>
			<li>Check the attendance returned box in LSApp
			<li>If it's an external course, prioritize processing
			<li>Make sure Nancy receives it for billing
		</ul>
		</li>
	</ul>
	</li>
	<li>Evaluations?
	<ul>
		<li>No
		<ul>
			<li>Record a note
			<li>Investigate with facilitator
		</ul>
		</li>
		<li>Yes
		<ul>
			<li>Put together with attendance for processing
		</ul>
	</ul>
	</li>
	<li>Return/remove AudioVisual
	<ul>
		<li>Test projector thoroughly
		<li>Note any faults or issues
	</ul>
	</li>
</ul>
</li>
<li>Process attendance
<li>Close class in Learning System
<li>Scan and save evaluations to the LAN (Z:\The Learning Centre\3. Evaluations (-05)\F2F Surveys - Tracking & Results)
<li>Pat yourself on the back
</ul>

</div>
</div>
</div>

<?php else: ?>
<div class="col-md-6">
	<h2>Class Not Found</h2>
	<p>Must be playin' hooky ;)</p>
	<p>Email the Learning Support Admin Team <<a href="mailto:learning.centre.admin@gov.bc.ca">learning.centre.admin@gov.bc.ca</a>> with any questions or concerns.</p>
	<p><img src="img/TrollFace.jpg" width="300px"></p>
</div>	
<?php endif ?>

</div>
</div>
<?php else: // canAccess() ?>
<p>Contact Learning Operations learning.centre.admin@gov.bc.ca for access.</p>
<?php endif ?>


<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>