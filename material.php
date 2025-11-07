<?php require('inc/lsapp.php') ?>

<?php getHeader() ?>

<title>Material</title>

<?php getScripts() ?>

<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<?php $mid = (isset($_GET['mid'])) ? $_GET['mid'] : 0 ?>
<?php $deets = getMaterial($mid) ?>
<?php if(is_array($deets)): ?>

<div class="col-md-6">

<?php //print_r($deets) MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,Partial,Restock,Notes,FileName ?>

<div class="card mb-3">
<div class="card-header">
<div class="float-end">
	<a href="material-update.php?mid=<?= $deets[0] ?>" class="btn btn-secondary btn-sm">Edit</a>
	<?php if(isSuper()): ?>
	<form method="post" action="material-delete.php">
	<input type="hidden" name="CourseID" value="<?= $deets[2] ?>">
	<input type="hidden" name="MaterialID" value="<?= $deets[0] ?>">
	<input type="submit" value="Delete" class="btn btn-sm btn-danger del">
	</form>
	<?php endif ?>
</div>
<div><a href="course.php?courseid=<?= $deets[2] ?>"><?= $deets[1] ?></a></div>
<h1 class="card-title"><?= $deets[3] ?></h1>


</div>
<div class="card-body">
<?php if($deets[7] == 'on'): ?>
<div class="alert alert-danger">RESTOCK</div>
<?php endif ?>


<div class="row">
<div class="col-12">
<?php 
$upcount = 0;
$classes = getCourseClasses($deets[2]);
$today = date('Y-m-d');
foreach($classes as $class):
if($class[9] < $today) continue;
$upcount++;
endforeach;
?>
<?php
$per = $deets[4];
$in = $deets[5];
$newstock = 0;
$classesworth = 0;
if($in > 0 && $per > 0) {
	$classesworth = floor($in / $per);
	$newstock = ($in - $per);
	if($newstock < 1) $newstock = 0;
} 
?>
<div class="row">
<div class="col-6">
<h4><span class="badge badge-dark"><?= $deets[4] ?></span> Per class</h4>
<h4><span class="badge badge-dark"><?= $upcount ?></span> Upcoming classes</h4>
<h4><span class="badge badge-dark"><?= $deets[5] ?></span> In stock</h4>

<?php if($classesworth < count($classes)): ?>
<h4><span class="badge badge-danger"><?= $classesworth ?></span> Classes worth</h4>
<?php else: ?>
<h4><span class="badge badge-dark"><?= $classesworth ?></span> Classes worth</h4>
<?php endif ?>
</div>

</div>


</div>
<div class="col-12">
<hr>
<h4>Printing Instructions</h4>
<?= $deets[8] ?>
<h4>Notes</h4>
<?= $deets[14] ?>


<?php if($deets[9]): ?>
<div class=""><a href="materials/<?= $deets[9] ?>" class="btn btn-dark btn-block mb-3" target="_blank">Download <?= $deets[3] ?></a></div>
<?php else: ?>
<div class="alert alert-danger">No File Uploaded Yet</div>
<?php endif ?>

</div>
</div> <!-- /.row -->

</div> <!-- /.card-body -->
<div class="card-footer">
<p>Created on <?= h($deets[10]) ?> by <?= h($deets[11]) ?></p>
</div>
</div>
</div>



<div class="col-md-4">


<?php $materials = getMaterials($deets[2]) ?>
<?php if(isset($materials)): ?>
<h3><div><a href="course.php?courseid=<?= $deets[2] ?>"><?= $deets[1] ?></a> materials</h3>
<form method="post" action="materials-order-create.php">
<input type="hidden" name="CourseID" id="CourseID" value="<?= $deets[2] ?>">
<input type="hidden" name="CourseName" id="CourseName" value="<?= $deets[1] ?>">
<table class="table table-sm">
<tr>

	<th>MaterialName</th>
	<th class="text-center">PerCourse</th>
	<th class="text-center">InStock</th>
</tr>
<!-- MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,Partial,Restock,Notes,FileName -->

<?php foreach($materials as $mat): ?>
<?php if($mat[0] == $deets[0]): ?>
<tr class="table-primary">
<?php else: ?>
<tr>
<?php endif ?>
	<td><a href="material.php?mid=<?= $mat[0] ?>"><?= $mat[3] ?></a></td>
	<td class="text-center"><?= $mat[4] ?></td>
	<td class="text-center"><?= $mat[5] ?></td>
</tr>
<?php endforeach?>
<?php endif ?>
</table>
</form>


<h3><span class="badge badge-light"><?= $upcount ?></span> Upcoming classes</h3>


<table class="table table-sm">
<?php foreach($classes as $class): ?>
<?php	
// We only wish to see classes which have an end date greater than today
$today = date('Y-m-d');
if($class[9] < $today) continue;
?>
<tr>
	<td>
		<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
		<?php echo goodDateShort($class[8],$class[9]) ?>
		</a>
	</td>
	<td><a href="city.php?name=<?= $class[25] ?>"><?= $class[25] ?></a></td>
	<td>
		<span class="badge badge-light"><?= $class[1] ?></span>
	</td>
</tr>
<?php endforeach ?>
</table>




</div>





<?php else: ?>
<div class="col-md-6">
	<h2>Venue Not Found</h2>
	<p>Email the Learning Support Admin Team <<a href="mailto:learning.centre.admin@gov.bc.ca">learning.centre.admin@gov.bc.ca</a>> with any questions or concerns.</p>
	<p><img src="img/TrollFace.jpg" width="300px"></p>
</div>	
<?php endif ?>

</div>
</div>

<?php require('templates/javascript.php') ?>

<?php require('templates/footer.php') ?>