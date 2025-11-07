<?php require('inc/lsapp.php') ?>
<?php if(canAccess()): ?>
<?php 
$region = (isset($_GET['name'])) ? $_GET['name'] : 0;

$rdeets = getRegion($region);
$regions = getRegions();
?>
<?php getHeader() ?>

<title><?= h($region) ?></title>

<?php getScripts() ?>
<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-10">

<div class="dropdown float-end">
	<button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		Regions
	</button>
	<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
	<?php foreach($regions as $r): ?>
	<a href="region.php?name=<?= $r[1] ?>" class="dropdown-item"><?= $r[2] ?></a> 
	<?php endforeach ?>
	</div>
</div>
<div>Upcoming Classes in </div>
<h1><?= $rdeets[0][2] ?></h1>

<?php $classes = getClasses() ?>
<table class="table table-sm">
<tr>
	<th>Status</th>
	<th width="140" class="text-right">Class Date</th>
	<th>Course Name</th>
	<th>Venue</th>
	<th>City</th>
	
</tr>
<?php foreach($classes as $class): ?>
<?php	
// We only wish to see classes which have an end date greater than today
$today = date('Y-m-d');
if($class[9] < $today) continue;
if($class[47] == $region):
?>
<tr>
	<td>
		<span class="badge badge-light"><?= $class[1] ?></span>
	</td>
	<td class="text-right">
		<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
		<?php echo goodDateShort($class[8],$class[9]) ?>
		</a>
	</td>
	<td>
		<a href="course.php?courseid=<?= $class[5] ?>"><?= $class[6] ?></a>
	</td>
	<td>
		<a href="venue.php?vid=<?= $class[23] ?>"><?= $class[24] ?></a>
	</td>
	<td>
		<a href="city.php?name=<?= $class[25] ?>"><?= $class[25] ?></a>
	</td>
</tr>
<?php endif ?>
<?php endforeach ?>
</table>






</div>
</div>





</div>
</div>
</div>



<?php require('templates/javascript.php') ?>

<?php require('templates/footer.php') ?>

<?php else: ?>


<?php require('templates/noaccess.php') ?>

<?php endif ?>