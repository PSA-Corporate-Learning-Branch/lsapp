<?php 
require('inc/lsapp.php');

// Get the full class list
$courses = getCourses();
// Grab the headers
// $headers = $courses[0];
// Pop the headers off the top
array_shift($courses);
// Create a temp array for the array_multisort below
$tmp = array();
// loop through everything and add the name to
// the temp array
foreach($courses as $line) {
	$tmp[] = $line[2];
}
// Sort the whole kit and kaboodle by name
array_multisort($tmp, SORT_ASC, $courses);

if(!isset($_GET['courseids'])) {
	$cids = '';
	$courseids = array();
	
} else {
	$cids = $_GET['courseids'];
	$courseids = explode(',',$_GET['courseids']);
}

$activeids = array();
?>
<?php getHeader() ?>
<title>Courses</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<?php if(canAccess()): ?>
<div class="container-fluid">
<div class="row mb-3">
<!--
<div class="col-md-3">
<h1>Categories</h1>
<ul class="list-group">
<?php $cats = getCategories() ?>
<?php foreach($cats as $cat): ?>
<li class="list-group-item"><a href="category.php?category=<?php echo urlencode($cat[1]) ?>"><?= $cat[1] ?></a></li>
<?php endforeach ?>
</ul>
</div>
-->
<div class="col-md-4">
<div class="dropdown float-end">
	<button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		Categories
	</button>
	<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
	<?php $cats = getCategories() ?>
	<?php foreach($cats as $cat): ?>
	<a href="category.php?category=<?php echo urlencode($cat[1]) ?>" class="dropdown-item"><?= $cat[1] ?></a>
	<?php endforeach ?>
	</div>
</div>

<h1>Courses</h1>
<?php if(!$cids): ?>
<div class="alert alert-success">
	Please choose an "Add" button beside the course name to add it to the upcoming classes list.
</div>
<?php endif ?>
<div id="courselist">
<input class="search form-control  mb-3" placeholder="search">

<ul class="list-group list">
<?php foreach($courses as $course): ?>
	<?php if($course[1] == 'Active'): ?>
	<?php if(in_array($course[0],$courseids)): ?>
		<?php $cdeet = array($course[0],$course[2]) ?>
		<?php array_push($activeids,$cdeet) ?>
	<?php else: ?>
	<li class="list-group-item">
		<div class="float-end ml-3">
			<?php if($cids): ?>
			<a href="courses-selector.php?courseids=<?= $cids ?>,<?= h($course[0]) ?>" class="btn btn-sm btn-primary">Add</a>  
			<?php else: ?>
			<a href="courses-selector.php?courseids=<?= h($course[0]) ?>" class="btn btn-sm btn-primary">Add</a>  
			<?php endif ?>
		</div>
		<span class="coursename">
			<a href="course.php?courseid=<?= h($course[0]) ?>"><?= h($course[2]) ?></a>
		</span>
	</li>
	<?php endif ?>
	<?php endif ?>
<?php endforeach ?>
</ul>
</div> <!-- /.courselist -->
</div> <!-- /.col -->

<div class="col-md-8">
<?php 
$inactive = 0;
$upcount = 0;
$classes = getCoursesClasses($courseids);
foreach($classes as $class):
$today = date('Y-m-d');
if($class[9] < $today) continue;
if($class[1] == 'Inactive') $inactive++;
$upcount++;
endforeach;
?>

<div id="classlist">
<h3><span class="classcount"><?= ($upcount - $inactive) ?></span>  Upcoming Classes</h3>
<!--<a href="course-classes-export.php?courseid=<?php  //$deets[0] ?>" class="btn btn-block btn-light">Export to Excel</a>-->
<div class="my-3">
<?php foreach($activeids as $aid): ?>
<?php 
$newcourseids = ''; 
foreach($courseids as $id) {
	if($id == $aid[0]) {
		continue;
	} else {
		if(!$newcourseids) {
			$newcourseids = $id;
		} else {
			$newcourseids = $newcourseids . ',' . $id;
		}
	}
}

?>
	<a href="courses-selector.php?courseids=<?= $newcourseids ?>" class="btn btn-sm btn-primary">x</a> <a href="course.php?courseid=<?= $aid[0] ?>"><?= $aid[1] ?></a>
<?php endforeach ?>
</div>
<!-- <input class="search form-control my-2" placeholder="search"> -->
<table class="table table-sm">
<thead>
<tr>
	<th>Item Code</th>
	<th><a href="#" class="sort" data-sort="coursename">Course</a></th>
	<th><a href="#" class="sort" data-sort="classdate">Class Date</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
	<th><a href="#" class="sort" data-sort="status">Status</a></th>
</tr>
</thead>
<tbody class="list">
<?php foreach($classes as $class): ?>
<?php	
// We only wish to see classes which have an end date greater than today
$today = date('Y-m-d');
if($class[9] < $today) continue;
?>
<tr>
	<td><small><?= $class[7] ?></small></td>
	<td class="coursename"><a href="course.php?courseid=<?= $class[5] ?>"><?= $class[6] ?></a></td>
	<td>
		<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
		<?php echo goodDateShort($class[8],$class[9]) ?>
		</a>
		<div class="classdate" style="display:none"><?= $class[8] ?></div>
	</td>
	<td class="city"><a href="city.php?name=<?= $class[25] ?>"><?= $class[25] ?></a></td>
	<td class="status">
		<span class="badge badge-light"><?= $class[1] ?></span>
	</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>




</div>


</div> <!-- /.row -->

<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>

<?php require('templates/javascript.php') ?>
<script>
$(document).ready(function(){

	$('.search').focus();
	
	var courseoptions = {
		valueNames: [ 'coursename' ]
	};
	var courselist = new List('courselist', courseoptions);	
	
	var classoptions = {
		valueNames: [ 'coursename','classdate','city','status' ]
	};
	var classlist = new List('classlist', classoptions);

});
</script>

<?php include('templates/footer.php') ?>