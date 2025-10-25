<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>The Learning Centre | PSA | Learning Support Application</title>
<?php getScripts() ?>
<link href="/lsapp/css/summernote-bs4.css" rel="stylesheet">
<?php getNavigation() ?>


<?php if(isAdmin()): ?>
<div class="container">
<div class="row justify-content-md-center">
<div class="col-md-4">

<?php $post = getPostLast() ?>
<h2>LSA Journal</h2>
<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $post[3]) ?>
<form method="post" action="blog-create.php" class="mb-3">
	<textarea name="Body" id="Body" class="form-control summernote"><?= $n ?></textarea>
	<small>Posted on <?= $post[1] ?> by <a href="person.php?idir=<?= $post[2] ?>"><?= $post[2] ?></a></small>
	<input type="submit" class="btn btn-success mt-2 btn-block" value="New Journal Post">
</form>

<?php if(isset($_GET['message'])): ?>
<div class="alert alert-info">
<?= $_GET['message'] ?>
</div>
<?php endif ?>
<div class="card">
<div class="card-header">
<h1 class="card-title">
	Admin Tasks
</h1>
</div>
<ul class="list-group list-group-flush">
<?php //echo LOGGED_IN_IDIR ?>
<li class="list-group-item"><a href="venues-dashboard.php" class="">Venues Dashboard</a></li>
<li class="list-group-item"><a href="materials.php" class="">Materials Dashboard</a></li>
<li class="list-group-item"><a href="shipping.php" class="">Shipping Dashboard</a></li>
<li class="list-group-item"><a href="upload.php">Learning System Synchronize</a></li>
<li class="list-group-item"><a href="av-dashboard.php">Audio Visual</a></li>
<!--<li class="list-group-item"><a href="process-attendance-statuses.php">Process ELM Attendance Numbers and Statuses</a></li>-->
<li class="list-group-item"><a href="audit.php">Learning System Audit</a></li>
<li class="list-group-item"><a href="export.php">Export</a></li>
<li class="list-group-item"><a href="kiosk.php">Kiosk</a></li>

<!--  These are all left over from the switch between this and the old Access database
They can probably be deleted soon, but I'm leaving them for the time being

<li class="list-group-item"><a href="classes-requested.php" class="">All Requested Class Dates</a></li>
<li class="list-group-item"><a href="courses-requested.php" class="">All Requested Courses</a></li>

<li class="list-group-item"><a href="process-minmaxes.php">Process Min Maxes</a></li>
<li class="list-group-item"><a href="process-ends.php">Process End Dates</a></li>
<li class="list-group-item"><a href="process-ss.php">Process Shipping Statuses</a></li>
<li class="list-group-item"><a href="process-ded.php">Process Dedicated</a></li>
<li class="list-group-item"><a href="process-cats.php">Kill Cats</a></li>

-->
</ul>
</div>

</div>

<div class="col-md-8">



<?php 
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
array_multisort($tmp, SORT_ASC, $c);
?>
<h3>Pending Class Change Requests</h3>
<ul class="list-group mb-3">
<?php $classchanges = getPendingClassChanges() ?>
<?php foreach($classchanges as $change): ?>


<li class="list-group-item">
	<a href="class.php?classid=<?= $change[1] ?>"><?php echo goodDateShort($change[3]) ?>
	<?= $change[2] ?> in <?= $change[4] ?> </a>
	<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $change[10]) ?>
	<div class="alert alert-secondary mb-0 p-0 pl-2">
		<small><?php echo goodDateShort($change[5]) ?> <a href="person.php?idir=<?= $change[6] ?>"><?= $change[6] ?></a> requests:</small><br>
		<?= $n ?>
	</div>
</li>

<?php endforeach ?>
</ul>
<h3>Pending Course Change Requests</h3>
<ul class="list-group">
<?php $coursechanges = getPendingCourseChanges() ?>
<?php foreach($coursechanges as $change): ?>
<!-- 0-creqID,1-CourseID,2-CourseName,3-DateRequested,4-RequestedBy,5-Status,
6-CompletedBy,7-CompletedDate,8-Request -->
<li class="list-group-item">
<a href="course.php?courseid=<?= $change[1] ?>"><?= $change[2] ?></a>
<div class="alert alert-secondary mb-0 p-0 pl-2">
	<small><a href="person.php?idir=<?= $change[4] ?>"><?= $change[4] ?></a> requests:</small><br>
	<?= $change[8] ?>
</div>
</li>

<?php endforeach ?>
</table>


<h3>Unclaimed Service Requests</h3>
<div id="reqlist">
<!--<input class="search form-control mb-3" placeholder="search">-->
<table class="table table-sm table-striped">
<thead>
<tr>
	<th></th>
	<th>Date</th>
	<th><a href="#" class="sort" data-sort="name">Course Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
</tr>
</thead>
<tbody class="list">
<?php //$unclaimedclasses = getClasses() ?>
<?php foreach($c as $uclass): ?>
<?php if($uclass[1] == 'Requested'): ?>
<?php if(!$uclass[44] || $uclass[44] == 'Unassigned'): ?>
<tr>
	
	<td>
	<?php if(isAdmin()): ?>
	<form method="get" action="class-claim.php" class="float-end claimform">
		<input type="hidden" name="cid" id="cid" value="<?= h($uclass[0]) ?>">
		<input type="submit" class="btn btn-sm btn-light ml-3" value="Claim">
	</form>
	<?php endif ?>
	</td>

	<td>
		<a href="class.php?classid=<?= $uclass[0] ?>"><?php echo goodDateShort($uclass[8],$uclass[9]) ?></a><br>
	</td>
	<td class="name"><a href="course.php?courseid=<?= $uclass[5] ?>"><?= $uclass[6] ?></a></td>
	<td class="city"><a href="city.php?name=<?= $uclass[25] ?>"><?= $uclass[25] ?></a></td>
</tr>
<?php endif ?> 
<?php endif ?> 
<?php endforeach ?>
</tbody>
</table>
<a href="classes-requested.php" class="btn btn-block btn-light mb-3">All Requested Classes</a>













</div>

</div>
</div>
<?php else: // if canAccess() ?>
<?php include('templates/noaccess.php') ?>
<?php endif ?>


<?php require('templates/javascript.php') ?>
<script src="/lsapp/js/summernote-bs4.js"></script>
<script>
$(document).ready(function(){

	$('.search').focus();

	
	$('.summernote').summernote({
		//airMode: true,
		popover: {
			air: [
				['color', ['color']],
				['font', ['bold', 'underline', 'clear']],
				['table']
			]
		},
		toolbar: [
			// [groupName, [list of button]]
			['style'],
			['style', ['bold', 'italic']],
			['para', ['ul', 'ol']],
			['color', ['color']],
			['link'] //,['codeview']
		],
		placeholder: 'Type here',
		hint: {
			<?php $peeps = getPeopleAll() ?>
			mentions: [
			<?php foreach($peeps as $p): ?>
			'<?= $p[0] ?>',
			<?php endforeach ?>
			],
			match: /\B@(\w*)$/,
			search: function (keyword, callback) {
			callback($.grep(this.mentions, function (item) {
				return item.indexOf(keyword) == 0;
			}));
			},
			content: function (item) {
				return '@' + item;
			}
		}
		
	});	
	
	
	
	
	
});
</script>


<?php require('templates/footer.php'); ?>