<?php
require('inc/lsapp.php');

// OrderID,Status,Created,CreatedBy,Modified,ModifiedBy,CourseID,CourseName,
// 8-Cost,DateOrdered,DateArrived,Notes,FilePath
$orderid = h($_GET['orderid']);
$order = getOrder($orderid);
$course = getCourse($order[6]);
?>
<?php getHeader() ?>

<title>Materials Order <?= $order[7] ?> - <?= $orderid ?></title>

<?php getScripts() ?>
<?php getNavigation() ?>
<?php if(canAccess()): ?>
<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">


<div class="float-end">
<!--<a href="materials-order-update.php?orderid=<?= $order[0] ?>" class="btn btn-secondary">Edit</a>-->
<?php if(isAdmin()): ?>
<form method="post" action="materials-order-delete.php">
<input type="hidden" name="OrderID" id="OrderID" value="<?= $order[0] ?>">
<input type="submit" value="Delete" class="btn btn-danger del">
</form>
<?php endif ?>

</div>
<div class="btn-group float-end">
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#instructionsModal">
  Instructions
</button>
<a href="/lsapp/docs/kp-print-req_final.pdf" class="btn btn-success">King's Printer Requisition Template</a>
</div>
<div><a href="materials.php">Materials Dashboard</a></div>
<div><span class="badge badge-dark"><?= $order[1] ?></span></div>
<h1>Print Order <?= $order[0] ?></h1>
<?php //print_r($order) ?>
<h2><a href="course.php?courseid=<?= $order[6] ?>"><?= $order[7] ?></a></h2>
<div class="my-2">

Project Number: <span class="badge badge-dark"><?= $course[24] ?> </span>
Responsibility: <span class="badge badge-dark"><?= $course[25] ?> </span>
Service Line: <span class="badge badge-dark"><?= $course[26] ?> </span>
STOB: <span class="badge badge-dark"><?= $course[27] ?></span>
</div>
<div class="row">

<div class="col-md-6">
  <!--0-OrderID,Status,Created,CreatedBy,Modified,ModifiedBy,CourseID,CourseName,Cost,DateOrdered,DateArrived,Notes,FilePath,QuotedBy,SigningAuthority,PONumber,ConsigneeFile,17-PreviousStatus-->
<div class="card">
<div class="card-body">
<form  enctype="multipart/form-data" method="post" accept-charset="utf-8" action="materials-order-update.php">
<input type="hidden" name="OrderID" id="OrderID" value="<?= $order[0] ?>">
<input type="hidden" name="Created" id="Created" value="<?= $order[2] ?>">
<input type="hidden" name="CreatedBy" id="CreatedBy" value="<?= $order[3] ?>">
<input type="hidden" name="CourseID" id="CourseID" value="<?= $order[6] ?>">
<input type="hidden" name="CourseName" id="CourseName" value="<?= $order[7] ?>">
<input type="hidden" name="PreviousStatus" id="PreviousStatus" value="<?= $order[17] ?>">
<div class="row">
<div class="col-4">
<label for="Status">Status</label>
<select name="Status" id="Status" class="form-control">
<?php $statuses = array('Draft','Requested','Ordered','Received') ?>
<?php foreach($statuses as $stat): ?>
<?php if($stat == $order[1]): ?>
<option selected><?= $stat ?></option>
<?php else: ?>
<option><?= $stat ?></option>
<?php endif ?>
<?php endforeach ?>
</select>
</div>
<div class="col-4">
<label for="Cost">Cost</label>
<?php
$costwarn = '';
if($order[1] == 'Ordered' && !$order[8]) $costwarn = 'bg-danger';
?>
<input type="text" name="Cost" id="Cost" value="<?= $order[8] ?>" class="form-control <?= $costwarn ?>">
</div>
<div class="col-4">
<label for="PONumber">PO #</label>
<input type="text" name="PONumber" id="PONumber" value="<?= $order[15] ?>" class="form-control">
</div>
<div class="col-4">
<label for="DateOrdered">Date Ordered</label>
<input type="text" name="DateOrdered" id="DateOrdered" value="<?= $order[9] ?>" class="form-control">
</div>
<div class="col-4">
<label for="DateArrived">Date Arrived</label>
<input type="text" name="DateArrived" id="DateArrived" value="<?= $order[10] ?>" class="form-control">
</div>
</div>
<label for="Notes">Notes</label>
<textarea name="Notes" id="Note" class="form-control summernote mb-3"><?= $order[11] ?></textarea>
<?php if($order[12]): ?>
<a href="printorders/<?= $order[12] ?>" class="btn btn-success mb-2" target="_blank">Download Requisition</a>
<?php endif ?>
<?php if($order[1] == 'Ordered' && !$order[12]): ?>
<div class="alert alert-danger">
	Please upload the signed requisition PDF!
</div>
<?php endif ?>
<div class="alert alert-success">
	<label for="FilePath">Signed Requisition:</label>
	<input type="file" name="file" class="form-control-file">
	<input type="hidden" name="FilePath" id="FilePath" value="<?= $order[12] ?>" class="form-control">
</div>

<input type="submit" value="Update Order" class="btn btn-primary btn-block">
</form>

</div>
</div>
</div>

<div class="col-md-6">
<?php
$upcount = 0;
$classes = getCourseClasses($course[0]);
$today = date('Y-m-d');
foreach($classes as $class):
if($class[9] < $today) continue;
$upcount++;
endforeach;
?>
<div class="float-end"><span class="badge badge-dark"><?= $upcount ?></span> upcoming classes</div>
<h3>Order Items</h3>
<div class="table-responsive">
<table class="table table-striped">
<tr>
	<th class="text-right" width="340">Course Material</th>
	<th>Quantity</th>
</tr>
<?php $items = getOrderItems($orderid) ?>
<?php foreach($items as $item): ?>
<?php
$qtywarn = '';
if($item[3] == 0) $qtywarn = 'bg-danger text-white'
?>
<tr>
	<td class="text-right">
		<a href="material.php?mid=<?= $item[1] ?>" class="mr-2"><?= $item[2] ?></a> x
		<?php if(isset($item[5]) && isset($item[6])): ?>
		<div><small>Per course: <?= $item[5] ?>, Quantity (at time of order): <?= $item[6] ?></small></div>
		<?php endif ?>
	</td>
	<td>
		<?php if($order[1] == 'Draft'): ?>
		<form method="post" action="materials-order-item-update.php" class="form-inline">
			<input type="hidden" name="OrderID" id="OrderID" value="<?= $item[0] ?>">
			<input type="hidden" name="MaterialID" id="MaterialID" value="<?= $item[1] ?>">
			<input type="hidden" name="MaterialName" id="MaterialName" value="<?= $item[2] ?>">
			<input type="hidden" name="MaterialDetails" id="MaterialDetails" value="<?= $item[4] ?>">
			<input type="hidden" name="PerCourse" id="PerCourse" value="<?= $item[5] ?>">
			<input type="hidden" name="CurrentQTY" id="CurrentQTY" value="<?= $item[6] ?>">
			<input type="text" name="MaterialQTY" id="MaterialQTY" value="<?= $item[3] ?>"
				class="form-control form-control-sm ml-2 <?= $qtywarn ?>" size="3">
			<input type="submit" class="btn btn-primary btn-sm" value="Update">
		</form>
		<?php else: ?>
		<?= $item[3] ?>
		<?php endif ?>
	</td>
</tr>
<?php endforeach ?>
</table>
</div>

<a href="mailto:?CC=<?= $course[10] ?>;<?= $course[34] ?>&subject=Print Quote Request <?= $order[0] ?> - <?= $order[7] ?>&body=Hello,%0DPlease see attached request for a new print order for <?= $order[7] ?>.%0D%0D" class="btn btn-block btn-success">Compose Email</a>

</div>
</div>






</div>
</div>




<h2>Materials Print Details</h2>
<table class="table table-sm">
<tr>
<?php $nummats = count($items) ?>
<?php foreach($items as $item): ?>
<?php $dl = getMaterial($item[1]) ?>
<td valign="top">
	<a href="materials/<?= $dl[9] ?>" target="_blank">Download</a>
</td>
<?php endforeach ?>
</tr>
<tr>
<td colspan="<?= $nummats ?>">
<h2><?= $order[7] ?></h2>
</td>
</tr>
<tr>
<!-- OrderID,MaterialID,MaterialName,MaterialQTY,MaterialDetails -->
<!--MaterialID,CourseName,CourseID,MaterialName,PerCourse,InStock,
Partial,Restock,Notes,FileName,ProjectNumber,Responsibility,ServiceLine,STOB-->
<?php foreach($items as $item): ?>
<td valign="top">
	<strong><?= $item[2] ?> x <?= $item[3] ?></strong>
	<?= $item[4] ?>
</td>
<?php endforeach ?>
</tr>
</table>


</div>
</div> <!-- /.row -->



<div class="modal fade" id="instructionsModal" tabindex="-1" role="dialog" aria-labelledby="instructionsModal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Print Requisition Instructions</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
		<ul class="list-group instructions">
		<li class="list-group-item">Fill out the <a href="/lsapp/docs/kp-print-req_final.pdf">requisition form template</a></li>
		<li class="list-group-item">Email the new requisition and the individual material PDF files to the King's Printer agent</li>
		<li class="list-group-item">When they reply with a quote, fill in the amount in the document and print it</li>
		<li class="list-group-item">Have an Expense Authority sign the requisition quote</li>
		<li class="list-group-item">Scan the signed requisition</li>
		<li class="list-group-item">Send the signed requisition back to the print agent asking that it be put into production</li>
		<li class="list-group-item">Update this page with its status and the dollar amount</li>
		<li class="list-group-item">
			<a href="onenote:///Z:\The%20Learning%20Centre\2.%20Admin,%20Facilities%20&%20Ops\LSA's%20documents\OneNote\Learning%20Centre\Printing.one#Printing%20Instructions%20&section-id={7B99E000-924A-410A-B310-1964F74C90A5}&page-id={387BF838-E92A-4D31-934D-14217EEEBE14}&end"
				class="btn btn-light">
				More detailed instructions
			</a>
		</li>
		</ul>
      </div>

    </div>
  </div>
</div>

<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>

<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){



});
</script>
<?php include('templates/footer.php') ?>
