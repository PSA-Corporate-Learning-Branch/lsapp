<?php
opcache_reset();
require('inc/lsapp.php');

// Get an array of all classes in the system
$c = getClasses();
// Pop the headers off
array_shift($c);
// Create a temp array to hold course names for sorting
$tmp = array();
// Loop through the whole classes and add start dates to the temp array
foreach($c as $line) {
	$tmp[] = $line[8];
}
// Use the temp array to sort all the classes by start date
array_multisort($tmp, SORT_DESC, $c);

$toship = array();
$shipped = array();
$arrived = array();
$returned = array();

$count = 0;
// Create a today variable for comparison with class dates
$today = date('Y-m-d');
$daysuntil = 2;
$weekbefore = 7;
$tomorrow = date("Y-m-d", strtotime($today . ' + ' . $daysuntil . ' days'));


foreach($c as $row):

	if($row[1] != 'Closed' && $row[1] != 'Inactive'):
		if($row[49] == 'Returned') array_push($returned,$row);
	endif;
	
endforeach; // end looping through classes

$classid = (isset($_GET['classid'])) ? $_GET['classid'] : 0;
if(!$classid) $classid = $returned[0][0];
$deets = getClass($classid);

?>
<?php getHeader() ?>

<title>LSApp | Shipping</title>

<link rel="stylesheet" href="css/rome.min.css">

<?php getScripts() ?>


<body>
<?php getNavigation() ?>


<div class="container-fluid">
<div class="row">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>

<?php
$sections = array('Outgoing' => 'shipping-outgoing.php',
			'Shipped/Arrived' => 'shipping-intransit.php',
			'Returned' => 'shipping-incoming.php');

$current = $_SERVER['REQUEST_URI'];
$fulllink = explode('/lsapp/',$current);
$currentpage = explode('?',$fulllink[1]);
$active = '';
?>
<ul class="nav nav-tabs justify-content-center mb-3">
<?php foreach($sections as $page => $link): ?>
<?php
if($currentpage[0] === $link) {
	$active = 'active';
} else {
	$active = '';
}
 ?>
<li class="nav-item"><a href="<?= $link ?>" class="nav-link <?= $active ?>"><?= $page ?></a></li>
<?php endforeach ?>
</ul>

</div>
<div class="col-md-3">
<ul class="list-group">
<?php foreach($returned as $row): ?>

<?php
if($row[23] == 186 || $row[23] == 188 || $row[23] == 239) {
	$shouldbeshipping = date("Y-m-d", strtotime($row[8] . ' -1 days'));
} else {
	$shouldbeshipping = date("Y-m-d", strtotime($row[8] . ' -7 days'));
}
?>
<?php
// Get first and second IDs so we can use it for the "next" button
if($count == 0) {
	$firstid = $row[0];
} elseif ($count == 1) {
	$secondid = $row[0];
}
?>
<?php if($count < 20): ?>
<?php
$istoday = '';
if($row[0] == $classid) $istoday = 'background: #333; color: #FFF;';
?>
<li class="list-group-item" style="<?= $istoday ?>">

	<div><a href="shipping-incoming.php?classid=<?= $row[0] ?>" style="<?= $istoday ?>">
		<?php echo goodDateShort($row[8],$row[9]) ?>
		<?= $row[6] ?> in <?= $row[25] ?>
	</a>
	<?php if($row[23] == 186 || $row[23] == 188 || $row[23] == 239): ?>
	<span class="badge badge-success">On Campus</span>
	<?php endif ?>
	</div>

</li>
<?php endif ?>
<?php $count++ ?>
<?php endforeach ?>
</ul>
</div>
<div class="col-md-4">


	<div class="card mb-3">
	<div class="card-header">
		<strong>Returning</strong>
		<h2 class="card-title"><a href="course.php?courseid=<?= h($deets[5]) ?>"><?= h($deets[6]) ?></a></h2>
		<h3>
			<a href="class.php?classid=<?= h($deets[0]) ?>">
			<?php print goodDateLong($deets[8]) ?>
			</a>
		</h3>
		<h4><a href="venue.php?vid=<?= h($deets[23]) ?>"><?= h($deets[24]) ?> in <?= h($deets[25]) ?></a></h4>
	</div>
	<div class="card-body">
		</form>
		<form action="note-create.php" method="post" class="addanote mb-3">
			<input type="hidden" name="ClassID" id="ClassID" value="<?= h($deets[0]) ?>">
			<textarea name="Note" id="Note" class="form-control summernote" required></textarea>
			<input type="submit" class="btn btn-sm btn-block btn-primary my-1" value="Add Note">
		</form>	
		
		<?php if($deets[4] == 'Dedicated'): ?>
			<div class="alert alert-primary">
				<strong>This was a dedicated class</strong>. There should be an Excel-based ad hoc attendance
				sheet emailed to the admin inbox. Please check for it.
			</div>
		<?php endif ?>

		<div class="card mb-3">
		<div class="card-body">
		<?php //39-Attendance,40-EvaluationsReturned ?>
		<?php if($deets[39] == 'TRUE' || $deets[39] == 'on' || $deets[39] == 'Yes'): ?>
		<div class="alert alert-success">
			<h4>Attendance returned</h4>
			<div>Scan attendance and ensure that the Branch Operations Expense Authority 
				receives it for billing.</div>
		</div>
		<?php else: ?>
		<form method="post" action="class-process-attendance.php" class="attendancereturned mt-3">
		<input type="hidden" name="classid" id="classid" value="<?= h($deets[0]) ?>">
		<input type="checkbox" class="" name="Attendance" id="Attendance">
		<label for="Attendance"><strong>Attendance returned?</strong></label>
		</form>
		<div class="attendnot alert alert-warning">
			<p>Did the attendance not come back? 
			Record a note stating it didn't come back, 
			then investigate with the facilitator 
				(<a href="person.php?idir=<?= h($deets[14]) ?>"><?= h($deets[14]) ?></a>)
				to see what happened.</p>
		</div>
		<div class="attendreturned alert alert-success d-none">
			<h4>Attendance returned</h4>
			<div>Scan attendance and ensure that the Branch Operations Expense Authority 
				receives it for billing.</div>
		</div>		
		<?php endif ?>
		</div>
		</div>
		<div class="card mb-3">
		<div class="card-body">
		<?php //39-Attendance,40-EvaluationsReturned ?>
		<?php if($deets[40] == 'TRUE' || $deets[40] == 'on' || $deets[40] == 'Yes'): ?>
		<div class="alert alert-success">
			<h4>Evaluations returned</h4>
			<div>Please scan the evaluations onto the LAN:<br>
		Z:\The Learning Centre\3. Evaluations 1735-03\F2F Surveys - Tracking &amp; Results\<?= $deets[6] ?></div>
		</div>
		
		<?php else: ?>
		<form method="post" action="class-process-evaluations.php" class="evaluationsreturned mt-3">
			<input type="hidden" name="classid" id="classid" value="<?= h($deets[0]) ?>">
			<input type="checkbox" class="" name="EvaluationsReturned" id="EvaluationsReturned">
			<label for="EvaluationsReturned"><strong>Evaluations returned?</strong></label>
		</form>
		
		<div class="evalsnot alert alert-warning">
			Did the evaluations not come back? 
			Record a note stating that they didn't come back, 
			then investigate with the facilitator 
			(<a href="person.php?idir=<?= h($deets[14]) ?>"><?= h($deets[14]) ?></a>)
			to see what happened.
		</div>
		
		<div class="evalsreturned alert alert-success d-none">
			<h4>Evaluations returned</h4>
			<div>Please scan the evaluations onto the LAN:<br>
		Z:\The Learning Centre\3. Evaluations 1735-03\F2F Surveys - Tracking &amp; Results\<?= $deets[6] ?></div>
		</div>
		<?php endif ?>
		</div>
		</div>
		<?php $avassigned = getAVassigned($deets[0]) ?>
		<?php if(isset($assignedav)): ?>
		<div class="card mb-3">
		<div class="card-header">
		<h3 class="card-title">Assigned A/V</h3>
		</div>
		<div class="card-body">
		<?php foreach($avassigned as $av): ?>
		<div>
			<a href="/lsapp/class-process-av-assign.php?classid=<?= $deets[0] ?>&avid=<?= $av[0] ?>&action=return"
				title="Click to return the AV"
				class="btn btn-success float-end">
					Return
			</a>
			<h4><a href="/lsapp/av.php?avid=<?= $av[0] ?>"><?= $av[3] ?></a></h4>
			<?= $av[4] ?>
		</div>
		<?php endforeach ?>
		
		</div>
		</div> <!-- /.card -->
		<?php endif ?>
		<div class="card mb-3">
		<div class="card-body">
		
			<form method="get" action="class-process-shipstat.php" class="elmclosed mt-3">
				<input type="hidden" name="classid" id="classid" value="<?= h($deets[0]) ?>">
				<input type="hidden" name="status" id="status" value="Closed">
				<input type="checkbox" class="" name="Close" id="Close" disabled>
				<label for="Close"><strong>Closed in ELM?</strong></label>
				<em>This function is currently broken. It will come back soon!</em>
			</form>
			<div class="closedwarn alert alert-warning">
				Ensure that the class is set to closed in ELM. 
				Checking the box will set the class closed here in LSApp.
			</div>

		
		</div>
		</div> <!-- /.card -->
	</div>
	</div>
</form>


</div>

<div class="col-md-5">

<h4 class="mt-3 mb-0">Materials Inventory <a href="materials.php" class="btn btn-light btn-sm">Manage</a></h4>
<div class="mb-3">Add returned materials back in to inventory.</div>
<div class="table-responsive">
<table class="table table-sm table-striped">
<tr>

	<th>Material Name</th>
	<th class="text-center">Per Course</th>
	<th class="text-center">In Stock</th>
	<th class="text-center">Existing Stock</th>

</tr>
<!-- // 0-MaterialID,1-CourseName,2-MaterialName,3-PerCourse,4-InStock,5-Partial,6-Restock,7-Notes-->
<!-- 0-MaterialID,1-CourseName,2-CourseID,3-MaterialName,4-PerCourse,5-InStock,6-Partial,7-Restock,8-Notes,9-FileName -->
<?php $materials = getMaterials($deets[5]) ?>
<?php foreach($materials as $mat): ?>
<?php
$per = $mat[4];
$in = $mat[5];
$newstock = 0;
$classesworth = 0;
if($in > 0 && $per > 0) {
	$classesworth = floor($in / $per);
}
?>
<tr>
	<td>
	<div><a href="material.php?mid=<?= h($mat[0]) ?>"><?= h($mat[3]) ?></a></div>
	</td>
	<td class="text-center"><?= h($mat[4]) ?></td>
	<td class="text-center">
	<?= h($mat[5]) ?><br>
	<small><?= $classesworth ?> classes worth</small>
	</td>
	<td class="text-center" width="160">
	<form method="post" action="materials-process.php" class="inventoryadjust form-inline">
	<input type="hidden" name="action" id="action" value="class">
		<input type="hidden" name="cid" id="cid" value="<?= $deets[0] ?>">
		<input type="hidden" name="matid" id="matid" value="<?= $mat[0] ?>">
		<input type="number" class="form-control w-50" name="InStock" id="InStock" size="2" value="<?= h($mat[5]) ?>">
		<button class="btn btn-primary">Set</button>
		<?php if($mat[7] == 'TRUE' || $mat[7] == 'on'): ?>
		<input type="checkbox" class="" name="Restock" id="Restock" checked>
		<?php else: ?>
		<input type="checkbox" class="" name="Restock" id="Restock">
		<?php endif ?>
	</form>
	</td>


</tr>
<?php endforeach ?>
</table>
</div> <!-- /responsive -->


</div>
</div>
</div>

<?php require('templates/javascript.php') ?>

<script src="js/clipboard.min.js"></script>

<script>
$(document).ready(function(){



	var clipboard = new Clipboard('.copy');
	$('.copy').on('click',function(e){
		e.preventDefault();
		window.open("https://learning.gov.bc.ca/psp/CHIPSPLM/EMPLOYEE/ELM/c/LM_ADMINISTRATIVE.LM_ROSTER.GBL?PAGE=LM_ROSTER_SRCH", "_blank");
	});

	$('#Attendance').on('change',function(e){

		var form = $(this).parent('form');
		
		var url = form.attr('action');

		$.ajax({
			type: "POST",
			url: url,
			data: form.serialize(),
			success: function(data)
			{
				//form.after('<div class="alert alert-success mt-2">Attendance Updated</div>');
				$('.attendnot').addClass('d-none');
				$('.attendreturned').removeClass('d-none');
			},
			statusCode:
			{
				403: function() {
					form.after('<div class="alert alert-warning mt-2">You must be logged in.</div>');
				}
			}});
		e.preventDefault();

	});
	
	$('#EvaluationsReturned').on('change',function(e){

		var form = $(this).parent('form');
		
		var url = form.attr('action');

		$.ajax({
			type: "POST",
			url: url,
			data: form.serialize(),
			success: function(data)
			{
				//form.after('<div class="alert alert-success mt-2">Attendance Updated</div>');
				$('.evalsnot').addClass('d-none');
				$('.evalsreturned').removeClass('d-none');
			},
			statusCode:
			{
				403: function() {
					form.after('<div class="alert alert-warning mt-2">You must be logged in.</div>');
				}
			}});
		e.preventDefault();

	});
	
	$('.inventoryadjust').on('submit',function(e){

		var form = $(this);
		var url = form.attr('action');

		$.ajax({
			type: "POST",
			url: url,
			data: form.serialize(),
			success: function(data)
			{
				form.after('<div class="alert alert-success p-0">Adjusted</div>');
			},
			statusCode:
			{
				403: function() {
					form.after('<div class="alert alert-warning">You must be logged in.</div>');
				}
			}});
		e.preventDefault();

	});

	$('.addanote').on('submit',function(e){

		var form = $(this);
		var url = form.attr('action');

		$.ajax({
			type: "POST",
			url: url,
			data: form.serialize(),
			success: function(data)
			{
				form.after('<div class="alert alert-success mt-2">Note Added</div>');
			},
			statusCode:
			{
				403: function() {
					form.after('<div class="alert alert-warning">You must be logged in.</div>');
				}
			}});
		e.preventDefault();

	});
	

	$('#Close').on('change',function(e){
		
		e.preventDefault();
		//alert('come on');
		var form = $(this).parent('form');
		var url = form.attr('action');

		$.ajax({
			type: "GET",
			url: url,
			data: form.serialize(),
			success: function()
			{
				form.after('<div class="alert alert-success mt-2">Class Closed!</div>');
			},
			statusCode:
			{
				403: function() {
					form.after('<div class="alert alert-warning">You must be logged in.</div>');
				}
			}});
		

	});
	
	
});
</script>
<?php require('templates/footer.php'); ?>
