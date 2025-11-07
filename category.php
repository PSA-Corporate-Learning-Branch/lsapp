<?php require('inc/lsapp.php'); opcache_reset(); ?>
<?php if(canACcess()): ?>
<?php $category = (isset($_GET['category'])) ? $_GET['category'] : 0 ?>
<?php getHeader() ?>

<title><?= h($category) ?></title>

<?php getScripts() ?>
<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-4">
<a href="courses.php">All Courses</a>
<div class="dropdown float-end">
	<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		Categories
	</button>
	<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
	<?php $cats = getCategories() ?>
	<?php foreach($cats as $cat): ?>
	<a href="category.php?category=<?php echo urlencode($cat[1]) ?>" class="dropdown-item"><?= $cat[1] ?></a>
	<?php endforeach ?>
	</div>
</div>
<h1><?= h($category) ?></h1>

<ul class="list-group mb-3">
<?php $courses = getCoursesByCategory($category) ?>
<?php foreach($courses as $c): ?>
<?php if($c[1] != 'Inactive'): ?>
<li class="list-group-item"><a href="course.php?courseid=<?= $c[0] ?>"><?= $c[2] ?></a></li>
<?php endif ?>
<?php endforeach ?>
</ul>
</div>
<div class="col-md-8">
<?php 
$upcount = 0;
$classes = getClasses();
$tmp = array();
// loop through everything and add the start date to
// the temp array
foreach($classes as $line) {
	$tmp[] = $line[8];
}
// Sort the whole kit and kaboodle by start date from
// oldest to newest
array_multisort($tmp, SORT_ASC, $classes);

foreach($classes as $class):
	$today = date('Y-m-d');
	if($class[9] < $today) continue;
	$cats = explode(',', $class[46]);
	if(in_array($category,$cats)) {
		$upcount++;
	}
endforeach;
?>
<div id="upcoming-classes">
<h3><span class="classcount"><?= $upcount ?></span>  Upcoming Classes</h3>

<input class="search form-control my-2" placeholder="search">
<a href="category-classes-export.php?category=<?php echo urlencode($category) ?>" class="btn btn-block btn-light mb-2">Export to Excel</a>
<table class="table table-sm">
<thead>
<tr>
	<th>Item Code</th>
	<th><a href="#" class="sort" data-sort="classdate">Class Date</a></th>
	<th><a href="#" class="sort" data-sort="coursename">Course</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
</tr>
</thead>
<tbody class="list">
<?php $stat = '' ?>
<?php foreach($classes as $class): ?>
<?php	
// We only wish to see classes which have an end date greater than today
$today = date('Y-m-d');
if($class[9] < $today) continue;
if($class[1] == 'Inactive') continue;
$cats = explode(',', $class[46]);
if(in_array($category,$cats)):
if($class[1] == 'Requested') {
	$stat = 'table-warning';
} else {
	$stat = '';
}
?>
<tr class="<?= $stat ?>">
	<td><small><?= h($class[7]) ?></td>
	<td class="classdate">
		<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
		<?php echo goodDateShort($class[8],$class[9]) ?>
		</a>
		<span style="display:none" class="realstart"><?= $class[8] ?></span>
		<span style="display:none" class="realend"><?= $class[9] ?></span>
	</td>
	<td class="coursename"><a href="course.php?courseid=<?= $class[5] ?>"><?= $class[6] ?></a></td>
	<td class="city"><a href="city.php?name=<?php echo urlencode($class[25]) ?>"><?= $class[25] ?></a></td>
	<!--<td>
		<span class="badge badge-light"><?= $class[1] ?></span>
	</td>-->
</tr>
<?php endif ?>
<?php endforeach ?>
</tbody>
</table>


</div>
</div>





</div>
</div>
</div>



<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){
	$('.search').focus();
	
	var upcomingoptions = {
		valueNames: [ 'classdate', 
						'realstart',
						'realend',
						'coursename',
						'city'
					]
	};
	var upcomingClasses = new List('upcoming-classes', upcomingoptions);
	upcomingClasses.on('searchComplete', function(){
		//console.log(upcomingClasses.update().matchingItems.length);
		$('.classcount').html(upcomingClasses.update().matchingItems.length);
	});
});
</script>
<?php require('templates/footer.php') ?>

<?php else: ?>


<?php require('templates/noaccess.php') ?>

<?php endif ?>