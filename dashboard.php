<?php 
require('inc/lsapp.php');
require('inc/Parsedown.php');
$Parsedown = new Parsedown();
?>

<?php if(canAccess()): ?>
<?php

opcache_reset();
$f = fopen('data/functional-map.csv', 'r');
$functionlist = [];
fgetcsv($f);
while ($row = fgetcsv($f)) {
    array_push($functionlist,$row);
}
fclose($f);

$categories = [];
foreach($functionlist as $fun) {
    array_push($categories,$fun[1]);
}
$categories = array_unique($categories);


$fc = fopen('data/classes.csv', 'r');
$c = array();
$deliverymethods = array();
$today = date('Y-m-d');
$upcomingclasses = 0;
$totalenrol = 0;
$webinarcount = 0;
$classroomcount = 0;
$elearningcount = 0;
$blendedcount = 0;

$requestedclasses = [];

while ($row = fgetcsv($fc)) {
        if($row[45] == 'eLearning') $elearningcount++;
        if($row[9] < $today) continue;
        array_push($c,$row);
        $upcomingclasses++;
        $totalenrol = $totalenrol + (int)$row[18];
        if($row[45] == 'Webinar') $webinarcount++;
        if($row[45] == 'Classroom') $classroomcount++;
        if($row[1] == 'Requested') array_push($requestedclasses, $row);
}
fclose($fc);
$headers = $c[0];
array_shift($c);
$tmp = array();
foreach($c as $line) {
	$tmp[] = $line[10];
}
array_multisort($tmp, SORT_ASC, $c);




$dayofweek = date('w');
$tmrw = new DateTime($today);
$dayafter = new DateTime($today);
if($dayofweek == 5) {
	$tmrw->modify('+3 days');
	$dayafter->modify('+4 days');
} elseif($dayofweek == 6) {
	$tmrw->modify('+2 days');
    $dayafter->modify('+3 days');
} else {
    $tmrw->modify('+1 days');
    $dayafter->modify('+2 days');
}
$tomorrow = $tmrw->format('Y-m-d');
$dayaftertomorrow = $dayafter->format('Y-m-d');
$theday = $tmrw->format('l');



$activecourses = [];
$requestedcourses = [];
$activecs = getCourses();
foreach($activecs as $course) {
    if($course[1] == 'Active') {
        array_push($activecourses,$course);
    } elseif($course[1] == 'Requested') {
        array_push($requestedcourses,$course);
    }
}
$totalcourses = count($activecourses);
// For the tips modal
$tips = getTips();

$teams = getTeams();
$directors = getDirectors();

$coursechanges = getPendingCourseChanges();
$deliverymethods = getDeliveryMethods();


$classchanges = getPendingClassChanges();
//echo '<pre>'; print_r($classchanges); exit;
// creqID,ClassID,CourseName,StartDate,City,DateRequested,RequestedBy,Status,CompletedBy,CompletedDate,Request,RequestType,Response
// Create a temp array for the array_multisort below
$tmp = [];
// loop through everything and add the name to
// the temp array
$sortdir = SORT_ASC;
$sortfield = 3;
if(!empty($_GET['sort'])) {
    if($_GET['sort'] == 'daterequested') {
        // $sortdir = SORT_DESC;
        $sortfield = 5;
    }
}

foreach($classchanges as $line) {
	$tmp[] = $line[$sortfield];
}

// Sort the whole kit and kaboodle by name
array_multisort($tmp, $sortdir, $classchanges);


?>
<?php getHeader() ?>

<title>Dashboard</title>

<?php getScripts() ?>


<body class="bg-body-tertiary">
<?php getNavigation() ?>


<div class="container">


<div class="row justify-content-md-center mb-3">

<div class="col-md-6">

<div class="p-2 mb-1 bg-light-subtle border border-secondary-subtle rounded-3">



<div class="mb-2 p-2 rounded-3 fs-5"><a href="/lsapp/courses.php?sort=dateadded"><strong><?= $totalcourses ?></strong> Active Courses</a></div>

<?php if (count($requestedcourses) > 0): ?>
<details class="p-2 my-2 rounded-3 bg-dark-subtle border border-secondary-subtle">
    <summary><strong><?php echo count($requestedcourses) ?></strong> courses requested</summary>
    <?php foreach($requestedcourses as $course): ?>
        <div class="p-2 my-2 bg-light-subtle rounded-3">
            <div><a href="/lsapp/course.php?courseid=<?= $course[0] ?>"><?= $course[2] ?></a></div>
            <?= $Parsedown->text($course[16]) ?>
        </div>
    <?php endforeach ?>
</details>
<?php endif; ?>


</div>
<div class="p-2 mb-1 bg-light-subtle border border-secondary-subtle rounded-3">

<div class="mb-2 p-2 rounded-3 fs-5"><a href="/lsapp/index.php" class=""><strong><?= $upcomingclasses ?></strong> Upcoming Classes</a></div>
<?= $lastsyncmessage ?>
<div class="my-2 p-2 rounded-3">
    <strong><?= $webinarcount ?></strong> Webinars, 
    <strong><?= $classroomcount ?></strong> Classroom,
    <!-- <strong><?= $elearningcount ?></strong> eLearning,
    <strong><?= $blendedcount ?></strong> Blended -->
    <span title="The number of learners currently enrolled in a class in ELM"><strong><?= number_format($totalenrol) ?></strong> Learners Enrolled </span>
</div>

<?php if (count($requestedclasses) > 0): ?>
<details class="p-2 my-2 bg-dark-subtle border border-secondary-subtle rounded-3">
    <summary><strong><?= count($requestedclasses) ?></strong> classes requested</summary>
<table class="table table-sm table-striped">

<tbody class="list">
<?php //$unclaimedclasses = getClasses() ?>
<?php foreach($requestedclasses as $uclass): ?>

<tr>
	<td class="claimed">
	<?php if(!$uclass[44] || $uclass[44] == 'Unassigned'): ?>
	<form method="get" action="class-claim.php" class="float-end claimform">
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
</details>
<?php endif; ?>

<?php if (count($classchanges) > 0): ?>
<details class="my-2 p-2 bg-dark-subtle border border-secondary-subtle rounded-3">
<summary>    
    <strong><?php echo count($classchanges) ?></strong>
    pending class changes
</summary>
<?php foreach($classchanges as $change): ?>
<details class="p-2 my-2 bg-light-subtle rounded-3">
    <summary>
        <?php echo goodDateShort($change[3]) ?><br>
        <?= $change[2] ?>
        <?php if(!empty($change[13])): ?>
        <div><strong>Action on:</strong> 
        <?php if($change[13] == $today): ?>
        <span class="d-inline-block px-2 text-bg-success rounded-2"><?= h($change[13]) ?></span>
        <?php elseif($change[13] < $today): ?>
        <span class="d-inline-block px-2 text-bg-danger rounded-2"><?= h($change[13]) ?></span>
        <?php else: ?>
        <?= h($change[13]) ?>
        <?php endif ?>
        </div>
        <?php endif ?>
    </summary>
    <div class="mt-2 ms-4 p-2 bg-dark-subtle border border-secondary-subtle rounded-3">
        <?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $change[10]) ?>	
        <div><?php echo goodDateShort($change[5]) ?> <a href="person.php?idir=<?= $change[6] ?>"><?= $change[6] ?></a> requests:</div>
        <?php if($change[11]): ?><strong>Request type: </strong> <?= h($change[11]) ?><br><?php endif ?>
        <div class="mb-2 p-2 bg-light-subtle"><?= $n ?></div>
        <div>
            <a class="btn btn-link" href="class.php?classid=<?= $change[1] ?>">View Class</a>
            <?php if(isAdmin()): ?>
            <a href="/lsapp/class-change-process.php?changeid=<?= h($change[0]) ?>&classid=<?= h($change[1]) ?>"
                class="	btn btn-sm btn-success float-end">Mark Complete</a>
            <?php endif ?>
        </div>
	
    </div>
</details>
<?php endforeach ?>
</details>
<?php endif; ?>

<?php 

function isBranch($value) {
    return $value['isBranch'] == 1 && $value['name'] != 'Executive Director';
}
$branchTeams = array_filter($teams, 'isBranch');

?>




</div>


<div class="p-2 mb-1 bg-light-subtle border border-secondary-subtle rounded-3">


<details class="my-2 p-2 rounded-3">
    <summary><strong><?= count($branchTeams) ?></strong> Teams</summary>

    <?php foreach($branchTeams as $teamId => $teamDetails): ?>
    <div class="p-2 mb-1 bg-light-subtle rounded-3">
        <a href="teams-all.php?team=<?= $teamId ?>"><?= $teamDetails['name'] ?></a>
    </div>
    <?php endforeach ?>
</details>

<details class="my-2 p-2 rounded-3">
    <summary><strong><?= count($directors) ?></strong> Leadership Roles</summary>
    <?php foreach($directors as $d): ?>
    <div class="p-2 mb-1 bg-light-subtle rounded-3">
    <?= $d[2] ?> <?= $d[6] ?><br>
    </div>
    <?php endforeach ?>
</details>

<div class="my-2 p-2 rounded-3">
    <a href="people.php"><strong><?= $lsapppeople ?></strong> People</a>
</div>

<!--
<details class="my-2 p-2 rounded-3">
    <summary><strong><?= count($functionlist) ?></strong> Functions</summary>

    <?php foreach($categories as $cat): ?>
    <div class="mt-3"><strong><?= $cat ?></strong></div>
    <?php $active = 'btn-light' ?>
    <?php foreach($functionlist as $fun): ?>
    <?php if($cat == $fun[1]): ?>
    <a class="btn btn-sm btn-light" href="function-map.php?functionid=<?= $fun[0] ?>"><?= $fun[2] ?></a> 
    <?php endif ?>
    <?php endforeach ?>
    <?php endforeach ?>

</details>
-->

</div>




</div>

<div class="col-md-6">




<h2 class="mb-1">Happening Today</h2>
<?php $yestoday = 0 ?>
<?php foreach($c as $row): ?>
<?php if(($row[1] != 'Inactive') && ($row[1] != 'Deleted')): ?>



<?php if($row[8] <= $today && $row[9] >= $today): ?>




    <?php $yestoday = 1 ?>
    <div class="p-2 mb-1 bg-light-subtle border border-secondary-subtle rounded-3">
    <div class="font-weight-bold">
        
        <a href="class.php?classid=<?= $row[0]  ?>">
        <?= $row[6]  ?>
        <?php if(!empty($row[25])): ?>
            - <?= $row[25]  ?>
        <?php endif ?>
        </a>
        <!-- <span class="badge badge-light"><?= $row[1] ?></span> -->
    </div>
    
    <div><?= $row[10]  ?> | Facilitating: 
    <?php $facilitators = explode(' ', $row[14]); ?>
		<?php foreach($facilitators as $facilitator): ?>
		<a href="/lsapp/person.php?idir=<?= $facilitator ?>">
			<?= $facilitator ?>
		</a>
		<?php endforeach ?>
    </div>
</div>
<?php endif // == 'today' ?>
<?php endif // != ' Inactive' ?>
<?php endforeach ?>
<?php if(!$yestoday): ?>
    <div class="p-3 bg-light-subtle border border-secondary-subtle rounded-3">No classes today</div>
<?php endif ?>

<?php $yesnext = 0 ?>
<h2 class="mt-4 mb-1">Happening <?= $theday ?> <span style="font-size: 14px">(<?= $tomorrow ?>)</span></h2>
<?php foreach($c as $trow): ?>

<?php if($trow[1] != 'Inactive' && $trow[1] != 'Deleted'): ?>
<?php //echo $ondeck . ' - ' . $dayaftertomorrow . ' -  ' . $trow[9] . ' - '. $trow[6] ?>
<?php if($trow[8] <= $today && $trow[9] > $today || $trow[8] == $tomorrow): ?>
<?php $yesnext = 1 ?>
<div class="p-2 mb-1 bg-light-subtle border border-secondary-subtle rounded-3">
    <div class="font-weight-bold">
        <a href="class.php?classid=<?= $trow[0]  ?>">
        <?= $trow[6]  ?> 
        <?php if(!empty($trow[25])): ?>
            - <?= $trow[25]  ?>
        <?php endif ?>
        
        </a>
        <!-- <span class="badge badge-light"><?= $trow[1] ?></span> -->
    </div>
    
    <div><?= $trow[10]  ?> | Facilitating: 
    <?php $facilitators = explode(' ', $trow[14]); ?>
		<?php foreach($facilitators as $facilitator): ?>
		<a href="/lsapp/person.php?idir=<?= $facilitator ?>">
			<?= $facilitator ?>
		</a>
		<?php endforeach ?>
    </div>
</div> 
<!-- <a class="badge badge-light" href="/lsapp/index.php">All Upcoming</a> -->

<?php endif // tomorrow ?>
<?php endif // != ' Inactive' ?>
<?php endforeach ?>
<?php if(!$yesnext): ?>
    <div class="p-3 bg-light-subtle border border-secondary-subtle rounded-3">No classes <?= $theday ?></div>
<?php endif ?>


<div class="mt-3">
    <a href="./" class="btn bg-light-subtle">All upcoming classes</a>



</div>





</div>




</div>



<!-- 
<h3 class="mt-3 mb-1">Functional Teams</h3>
<div><a class="showteam" href="team-ajax.php?teamname=Governance">Gove
    rnance & Technology</a></div>
<div><a class="showteam" href="team-ajax.php?teamname=Employees">All Employees</a></div>
<div><a class="showteam" href="team-ajax.php?teamname=Leaders">People Leaders</a></div>
<div><a class="showteam" href="team-ajax.php?teamname=Operations">Operations and Technology</a></div>
</div>
<div class="col-md-4">
<div id="team"></div>
<script>
let teamlinks = document.getElementsByClassName('showteam');
Array.from(teamlinks).forEach(function(element) {
    element.addEventListener('click', (e) => { 
        e.preventDefault();
        let teamurl = e.target.getAttribute('href');
        fetch(teamurl, {
            method: 'GET'
        })
        .then((res) => res.text())
        .then((html) => {
            //window.location = '#foo';
            document.querySelector('#team').innerHTML = html;
        })
        .catch((err) => console.error("error:", err));
    });
});
</script>
 -->


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





<?php else: ?>

<?php require('templates/noaccess.php'); ?>

<?php endif ?>


<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){
	
	
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
<?php include('templates/footer.php') ?>