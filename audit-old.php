<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>Learning System Audit</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">

<?php if(isAdmin()): ?>

<?php 
$elm = fopen('data/elm.csv', 'r');
// Remove the headers
fgetcsv($elm);
//
// elm.csv header order:
// Course Name,Class,Start Date,Type,Facility,Class Status,
// Min Enroll,Max Enroll,Enrolled,Reserved Seats,Pending Approval,
// Waitlisted,Dropped,Denied,Completed,Not Completed,In Progress,Planned,Waived
//
// lsapp.csv header order:
// OHS,Dedicated,Cancelled,ItemCode,CourseName,ClassDate,ClassDays,EndDate,
// Venue,City,Notes,ShipDate,Status,ShippingID,ClassID,Shipper
//
?>
<div id="classes">
<div class="row justify-content-md-center">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>
</div>
<div class="col-md-6">
<div class="float-end mb-3">
<?php if(isAdmin()): ?>
<a class="btn btn-success"  href="upload.php">Upload</a>
<?php endif  ?>
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#instructionsModal">
  Instructions
</button>
</div>
<div class="modal fade" id="instructionsModal" tabindex="-1" role="dialog" aria-labelledby="instructionsModal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">How the audit tool works</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
		<p>This page loops through each class in ELM (as of <?php echo date ("F d Y H:i", filemtime('data/elm.csv')) ?>) 
		and checks to see if it exists in LSApp.
		</p>
		<ul class="list-group instructions">
			<li class="list-group-item">If the row is yellow, the class date is in ELM, but NOT represented in LSApp;
			this could mean that the Learning Centre does not manage that course; otherwise, 
			there is a class scheduled for a course that LC Ops <em>may not be aware of.</em></li>
			<li class="list-group-item">If a status is not the same between the two systems, it will show both.</li>
			<li class="list-group-item">If a venue name is not the same between the two systems, it will show both.</li>
		</ul>
      </div>
    </div>
  </div>
</div>
<div>

</div>

<h2>As of <?php echo date ("F d Y H:i", filemtime('data/elm.csv')) ?></h2>
		
<input class="search form-control mb-3" placeholder="search">

</div>
</div>
<div class="table-responsive">
<table class="table table-sm table-striped table-hover">
<thead>
<tr>
	<th><a href="#" class="sort" data-sort="status">Status</a></th>
	<!--<th width="100">Match</th>-->
	<th>Item Code</th>
	<th width="120"><a href="#" class="sort" data-sort="startdate">Start Date</a></th>
	<th><a href="#" class="sort" data-sort="course">Course Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
	<th><a href="#" class="sort" data-sort="venue">Venue</a></th>
	<th class="small"><a href="#" class="sort" data-sort="enrolled">Enrolled</a></th>
	<th class="small">Pending</th>
	<th class="small">Waitlist</th>
	<th class="small">Dropped</th>
	<th class="small">Denied</th>
	<th class="small">Reserved</th>
</tr>
</thead>
<tbody class="list">
<?php while ($row = fgetcsv($elm)): ?>

	<?php $check = getClassByItemCode($row[1]) ?>

	
	
	<?php if(isset($check[1])): ?>
		<?php if($row[1] == strtoupper($check[7])): ?>
		<?php if($check[1] == "Inactive"): ?>
		<tr class="cancelled">
		<?php else: ?>
		<tr class="table-success">
		<?php endif ?>
			<td class="status">
			<?php if($check[1] != $row[5]): ?>
				LSApp: <?= h($check[1]) ?><br>
				ELM: <?= h($row[5]) ?>
			<?php else: ?>
				<!--<?= h($check[1]) ?>-->
				<?= h($row[5]) ?>
			<?php endif ?>
			</td>
			<!--<td>Matched</td>-->
			<td class="itemcode">
				<?= h($row[1]) ?>
			</td>
			<td class="startdate">
				<?php 
				$elmdate = date_create_from_format('d/m/Y', $row[2]);
				$lsappdate = date_create_from_format('d/m/Y', $check[5]);
				echo date_format($elmdate, 'Y-m-d');
				?>
				
			</td>
			<td class="course"><?= h($row[0]) ?></td>
			<?php $elmvenue = explode(', BC - ', $row[4]) ?>
			<td class="city"><?= h($elmvenue[0]) ?></td>
			<td class="venue">
				
				<?php 
				if(isset($elmvenue[1])) {
					
					if($elmvenue[1] != $check[24]) {
						//echo '<a title="There is a discrepancy between the Learning System and LSApp">!!!!</a>';
						echo '<strong>' . $check[24] . '</strong><br>';
					}
				}
				?>
				<?php if(isset($elmvenue[1])) echo h($elmvenue[1]) ?>
				
			</td>
			<td class="text-center enrolled" style=""><?= h($row[8]) ?></td>
			<td class="text-center" style=""><?= h($row[10]) ?></td>
			<td class="text-center" style=""><?= h($row[11]) ?></td>
			<td class="text-center" style=""><?= h($row[12]) ?></td>
			<td class="text-center" style=""><?= h($row[13]) ?></td>
			<td class="text-center" style=""><?= h($row[9]) ?></td>
		</tr>
		<?php endif ?>
	<?php else: ?>
		<tr class="table-warning"><!-- table-danger text-dark -->
			<td class="status">
			<?php if($row[5] != "Active"): ?>
				<strong><?= h($row[5]) ?></strong>
			<?php else: ?>
				<?= h($row[5]) ?>
			<?php endif ?>
			</td>
			<!--<td>NO Match</td>-->
			<td class="itemcode"><?= h($row[1]) ?></td>
			<td class="startdate">
			<?php 
				$date = date_create_from_format('d/m/Y', $row[2]);
				echo date_format($date, 'Y-m-d');
				?>
			</td>
			<td class="course"><?= h($row[0]) ?></td>
			<?php $elmvenue = explode(', BC - ', $row[4]) ?>
			<td class="city"><?= h($elmvenue[0]) ?></td>
			<td class="venue"><?php if(isset($elmvenue[1])) echo h($elmvenue[1]) ?></td>
			<td class="text-center enrolled" style=""><?= h($row[8]) ?></div></td>
			<td class="text-center" style="">
				<?= h($row[10]) ?>
			</td>
			<td class="text-center" style=""><?= h($row[11]) ?></td>
			<td class="text-center" style=""><?= h($row[12]) ?></td>
			<td class="text-center" style=""><?= h($row[13]) ?></td>
			<td class="text-center" style=""><?= h($row[9]) ?></td>
		</tr>
	<?php endif ?>
<?php endwhile ?>
<?php fclose($elm) ?>
</tbody>
</table>
</div> <!-- /.table-responsive -->
</div>


<?php else: ?>
<?php include('templates/noaccess-adminonly.php') ?>
<?php endif ?>




</div>
</div>
</div>

<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>