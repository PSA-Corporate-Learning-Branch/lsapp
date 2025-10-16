<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>Admin Dashboard</title>
<link href="/lsapp/css/summernote-bs4.css" rel="stylesheet">
<?php getScripts() ?>
<body>

<?php getNavigation() ?>

<?php 
// Get all change request JSON files
$files = glob("course-change/requests/*.json");

// Initialize a counter to count open requests
$changeRequests = 0;

foreach ($files as $file) {
    $changeData = json_decode(file_get_contents($file), true);

	// count not closed requests
    if ($changeData) {
        if ($changeData['progress'] !== 'Closed') {
			$changeRequests++;
		}
    }
}
?>

<?php if(isAdmin()): ?>
<div class="container-fluid">
<div class="row">
<div class="col-md-12">
<?php if(isset($_GET['message'])): ?>
<div class="alert alert-info">
<?= $_GET['message'] ?>
</div>
<?php endif ?>

<?php include('templates/admin-nav.php') ?>

</div>
<!--
<div class="col-md-2">
<h3>Operations Team</h3>
<ul class="list-group">
<?php $team = getPeopleByRole('Operations') ?>
<?php foreach($team as $member): ?>
<li class="list-group-item"><a href="person.php?idir=<?= $member[0] ?>"><?= $member[2] ?></a> <span class="badge badge-light"><?= $member[1] ?></span></li>
<?php endforeach ?>
<?php $super = getPeopleByRole('Super') ?>
<?php foreach($super as $member): ?>
<li class="list-group-item"><a href="person.php?idir=<?= $member[0] ?>"><?= $member[2] ?></a> <span class="badge badge-light"><?= $member[1] ?></span></li>
<?php endforeach ?>
</ul>
</div> 
-->

<div class="col-md-3">

<?php 
$classchanges = getPendingClassChanges();
$pendingcount = 0;
$upcomingcount = 0;
$upcomingdays = 7; // at what point do we bring something from upcoming to pending
$pendingclasschanges = array();
$upcomingclasschanges = array();
foreach ($classchanges as $classchange) {
    // has an action on date
	if (!empty($classchange[13])) {
        // if it needs to be actioned further out than our set # of days
		if (daysFromNow($classchange[13]) > $upcomingdays) {
			$upcomingcount++;
			array_push($upcomingclasschanges, $classchange);
		} else {
			$pendingcount++;
			array_push($pendingclasschanges, $classchange);
		}
    } else {
		$pendingcount++;
		array_push($pendingclasschanges, $classchange);
	}
}
?>

<h3>Pending Class Changes <span class="badge text-bg-dark"><?= $pendingcount ?></span></h3>
<ul class="list-group mb-3">

	<?php foreach($pendingclasschanges as $change): ?>
		<?php
			// if request has an action on / scheduled date
			$action = 'anytime';
			if (!empty($change[13])) {
				$actionondays = daysFromNow($change[13]);
				$actiondate = goodDateShort($change[13]);
				if ($actionondays <= 0) {
					$action = 'now';
				} elseif ($actionondays <= $upcomingdays) {
					$action = 'soon';
				}
			}
		?>

		<li class="list-group-item">
			<a href="class.php?classid=<?= $change[1] ?>"><?php echo goodDateShort($change[3]) ?>
			<?= $change[2] ?> <?= !empty($change[4]) ? 'in ' . $change[4] : '' ?> </a>
			<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $change[10]) ?>
			<?php $calert = 'alert-secondary'; ?>
			<?php if($action == 'soon') $calert = 'alert-warning' ?>
			<?php if($change[11] == 'Cancel' || $action == 'now') $calert = 'alert-danger' ?>
			<div class="alert <?= $calert ?> mb-0 p-2 pl-2">
				<div class="d-flex w-100 justify-content-between">
					<?php if($change[11]): ?>
						<strong><?= h($change[11]) ?></strong><br>
					<?php endif ?>
					<?= isset($actiondate) ? '<small><strong> Action on: ' . $actiondate . '</strong></small>' : ''; ?>
				</div>
				<?= $n ?>
			</div>
			<small class="text-muted">Requested on <?= goodDateShort($change[5]) ?> by <a href="person.php?idir=<?= $change[6] ?>"><?= $change[6] ?></a></small>
		</li>

	<?php endforeach ?>
</ul>

<details>

<summary class="mb-2">
	<h3 style="display: inline;">Upcoming Class Changes <span class="badge text-bg-dark"><?= $upcomingcount ?></span></h3>
</summary>

<ul class="list-group mb-3">
	
	<?php foreach($upcomingclasschanges as $change): ?>

		<li class="list-group-item">
			<a href="class.php?classid=<?= $change[1] ?>"><?php echo goodDateShort($change[3]) ?>
			<?= $change[2] ?> <?= !empty($change[4]) ? 'in ' . $change[4] : '' ?> </a>
			<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $change[10]) ?>
			<?php $calert = 'alert-secondary'; ?>
			<?php if($change[11] == 'Cancel') $calert = 'alert-danger' ?>
			<div class="alert <?= $calert ?> mb-0 p-0 pl-2">
				<small><?php echo goodDateShort($change[5]) ?> <a href="person.php?idir=<?= $change[6] ?>"><?= $change[6] ?></a> requests:</small><br>
				<?php if($change[11]): ?>
					<strong><?= h($change[11]) ?></strong><br>
				<?php endif ?>
				<?= $n ?>
			</div>
		</li>

	<?php endforeach ?>

</ul>
</details>
</div>
<div class="col-md-3">

<h3>Pending Course Changes <span class="badge text-bg-dark"><?= $changeRequests > 0 ? $changeRequests : '' ?></span></h3>
<?php if ($changeRequests > 0): ?>
	<a href="course-change/index.php">View change requests</a>
<?php endif; ?>

</div>
<div class="col-md-6">
<?php $courses = getCourses() ?>
<?php if(sizeof($courses)>0): ?>
<h3>New Course Service Requests</h3>
<ul class="list-group mb-3">
<?php foreach($courses as $c): ?>
<?php if($c[1] == 'Requested'): ?>
<li class="list-group-item"><a href="course.php?courseid=<?= $c[0] ?>"><?= $c[2] ?></a></li>
<?php endif ?>
<?php endforeach ?>
<?php endif ?>


<?php 
// Hi Ben! If you're going to attempt to create a block with just webinars that don't have
// the webinar link in place, it's a bit more complex than I first outlined over email. 
// We may need to have a brief screen-share for me to guide you. 
// I'll leave the actual implementation for you, but will also give you a bit of help here:
//
// We _could_ copy the entire block below starting from $c = getClasses() and alter it
// to show us what we want, but this violates the DRY principle (Don't Repeat Yourself).
// Instead, to get our list of webinars without links (WWL), we should piggyback on the 
// getClasses() function and use the same sorting loop to create our list of WWL.
//
// 1) We want to start by creating a new array, like we do with $reqs; call it $wwl perhaps
//
// 2) Then skip down to the foreach loop and add in the new logic (if(){}) to filter out
// the WWL, array_push($wwl,$line)'ing those classes to the array we created ($wwl).
//
// 3) At this point, you can copy the block from the H3 header on down, paste that right
// after it, and alter it to use the newly created array that contains the WWL (instead
// of foreach($reqs as $uclass) it'd be foreach($wwl as $class) or whatever you name
// your variables.
//
// 4) you can then alter the table itself (e.g. remove the claimed and city columns)
// and you should be pretty much good to go :) 

$c = getClasses();
$reqs = array();

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
foreach($c as $line) {
		if($line[1] == 'Requested') {
			array_push($reqs,$line);
		}
		// add in new WWL logic here...
}
?>
<h3 class="mt-3">New Class Service Requests <span class="badge text-bg-dark"><?= count($reqs) ?></span></h3>
<div id="requestedclasses">
<!--<input class="search form-control mb-3" placeholder="search">-->
<table class="table table-sm table-striped">
<thead>
<tr>
	<th><a href="#" class="sort" data-sort="claimed">Claimed</a></th>
	<th><a href="#" class="sort" data-sort="startdate">Date</a></th>
	<th><a href="#" class="sort" data-sort="coursename">Course Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
</tr>
</thead>
<tbody class="list">
<?php //$unclaimedclasses = getClasses() ?>
<?php foreach($reqs as $uclass): ?>

<tr>
	<td class="claimed">
	<?php if(!$uclass[44] || $uclass[44] == 'Unassigned'): ?>
	<form method="get" action="class-claim.php" class="float-right claimform">
		<input type="hidden" name="cid" id="cid" value="<?= h($uclass[0]) ?>">
		<input type="submit" class="btn btn-sm btn-light ml-3" value="Claim">
	</form>
	<?php else: ?>
	<a href="person.php?idir=<?= $uclass[44] ?>"><?= $uclass[44] ?></a>
	<?php endif ?>
	</td>

	<td>
		<span class="startdate" style="display: none"><?= $uclass[8] ?></span>
		<a href="class.php?classid=<?= $uclass[0] ?>"><?php echo goodDateShort($uclass[8],$uclass[9]) ?></a><br>
	</td>
	<td class="coursename"><a href="course.php?courseid=<?= $uclass[5] ?>"><?= $uclass[6] ?></a></td>
	<td class="city"><a href="city.php?name=<?= $uclass[25] ?>"><?= $uclass[25] ?></a></td>
</tr>

<?php endforeach ?>
</tbody>
</table>
</div>



<!-- BEN: PASTE COPIED BLOCK OVER TOP OF THIS COMMENT -->



















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

	var options = {
		valueNames: [ 'claimed', 
						'startdate', 
						'coursename', 
						'city' 
					]
	};
	var requestedclasses = new List('requestedclasses', options);
	
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
	
	
$('.claimform').on('submit',function(e){

	var form = $(this);
	var url = form.attr('action');

	//form.nextAll('.alert').first().fadeOut().remove();
	
	$.ajax({
		type: "GET",
		url: url,
		data: form.serialize(),
		success: function(data)
		{
			userlink = '<a href="person.php?idir='+data+'">'+data+'</a>';
			console.log(userlink);
			form.after(userlink);
			form.remove();
			//form.closest('tr').fadeOut().remove();
			
		},
		statusCode: 
		{
			403: function() {
				form.after('<div class="alert alert-warning">You must be logged in.</div>');
			}
		}});
	e.preventDefault();

});


	
});
</script>


<?php require('templates/footer.php'); ?>