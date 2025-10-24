<?php 
require('inc/lsapp.php');
// Get all the materials
$materials = getMaterialsAll();
// Grab the headers
// $headers = $courses[0];
// Pop the headers off the top
array_shift($materials);
?>
<?php getHeader() ?>
<title>Materials Reorder | LSApp</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<?php if(canAccess()): ?>
<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>
</div>
<div class="col-md-4">
<h1>Materials Reorder</h1>
<p>The courses have materials which need ordering.</p>

<a href="/lsapp/docs/kp-print-req_final.pdf" class="btn btn-success btn-block mb-1">King's Printer Requisition Template</a>

<a href="onenote:///Z:\The%20Learning%20Centre\2.%20Admin,%20Facilities%20&%20Ops\LSA's%20documents\OneNote\Learning%20Centre\Printing.one#Printing%20Instructions%20&section-id={7B99E000-924A-410A-B310-1964F74C90A5}&page-id={387BF838-E92A-4D31-934D-14217EEEBE14}&end"
	class="btn btn-primary btn-block mb-3">
	Detailed instructions (OneNote)
</a>
<ul class="list-group">
<?php 
//0-MaterialID,1-CourseName,2-CourseID,3-MaterialName,4-PerCourse,
// 5-InStock,6-Partial,7-Restock,8-Notes,9-FileName ?>
<?php $courseCheck = array() ?>
<?php foreach($materials as $mat): ?>
	<?php if($mat[7] == 'TRUE' || $mat[7] == 'on'): ?>
	<?php if(!in_array($mat[1],$courseCheck)): ?>
	<?php array_push($courseCheck,$mat[1]) ?>
	<li class="list-group-item">
		<a href="course.php?courseid=<?= h($mat[2]) ?>">
			<?= h($mat[1]) ?>
		</a>
	</li>
	<?php endif ?>
	<?php endif ?>
<?php endforeach ?>
</ul>

</div> <!-- /.col -->


<div class="col-md-8">
<h2>Orders</h2>

<!--OrderID,Status,Created,CreatedBy,Modified,ModifiedBy,CourseID,CourseName,
Cost,DateOrdered,DateArrived,Notes,FilePath,QuotedBy,SigningAuthority-->
<table class="table table-sm">
<tr>
	<th>Status</th>
	<th>Order ID</th>
	<th>PO #</th>
	<th>Course Name</th>
	<th>Ordered</th>
	<th class="text-right">Cost</th>
</tr>
<?php $ordersget = getOrdersAll() ?>
<?php $orders = array_reverse($ordersget) ?>
<?php $runningtotal = 0 ?>
<?php if(count($orders) > 0): ?>
<?php foreach($orders as $order): ?>
<?php 
$ocost = 0;
if($order[8] > 0) $ocost = $order[8];
?>
<?php $runningtotal = $runningtotal + $ocost ?>
<tr>
	<td><span class="badge badge-light"><?= $order[1] ?></span></td>
	<td>
		<a href="materials-order.php?orderid=<?= $order[0] ?>"><?= $order[0] ?></a>
	</td>
	<td><?= $order[15] ?></td>
	<td><a href="course.php?courseid=<?= $order[6] ?>"><?= $order[7] ?></a></td>
	<td><?= $order[9] ?></td>
	<td class="text-right">$<?= $order[8] ?></td>
	
</tr>
<?php endforeach ?>
<?php else: ?>
<tr><td colspan="4"><div class="alert alert-success">There are no orders yet!</div></td></tr>
<?php endif ?>
<tr>
<td colspan="5" class="text-right"><strong>Running Total:</strong></td>
<td><strong>$<?= $runningtotal ?></strong></td>
</table>
</div> <!-- /.col -->


</div> <!-- /.row -->

<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>

<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){
	
	$('.courseups').on('click',function(e){
		e.preventDefault();
		//var coureseid = $(this).find(':selected').data('cid');
		var cid = $(this).attr('href');
		courseid = cid.split('?courseid=');
		console.log(courseid[1]);

		
		$.ajax({type:"GET", 
				url:"data/classes.csv", 
				dataType:"text", 
				success: function(data) {
					$('.classlist').empty();
					//$('.coursename').html(data[6]);
					loadDates(courseid[1],data);
				}
		});
		
		
		
		
	});
	
	function loadDates(courseid,classdates) {

		var datesArray = $.csv.toArrays(classdates);
		//datesArray.sort(function(a,b){return a.getTime() - b.getTime()});
		var deets = [];
		var today = moment().format('YYYY-MM-DD');
		var classcount = 0;
		datesArray.forEach(function(cdate){
			if(cdate[5] == courseid && cdate[8] > today) {
				var courseName = cdate[6];
				let clink = '<li class="list-group-item">';
				clink += '<a href="/lsapp/class.php?classid=' + cdate[0] + '">';
				clink += '<span class="badge badge-secondary float-end">';
				clink += cdate[1];
				clink += '</span>';
				clink += '' + moment(cdate[8]).format('MMM Do YY') + ' | ' + cdate[25];
				clink += '</a></li>';
				$('.classlist').append(clink);
				classcount++;
			}
		});
		$('.classcount').html(classcount);
		

	}
});
</script>
<?php include('templates/footer.php') ?>