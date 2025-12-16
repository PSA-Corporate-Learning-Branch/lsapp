<?php require('inc/lsapp.php') ?>
<?php 

if($_POST):

	header('Location: index.php');

else: ?>



<?php getHeader() ?>

<title>Upload PUBLIC.GBC_CURRENT_COURSE_INFO</title>

<?php getScripts() ?>

<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>
</div>
<div class="col-md-6">

<?php if(isAdmin()): ?>




<div class="card ">
<div class="card-header">
	<h3 class="card-title">ELM <-> LSApp Synchronization &amp; Audit</h3>
	<small>Last synchronized on <?php echo date ("F d Y H:i", filemtime('data/elm.csv')) ?> </small>
</div>
<div class="card-body">
	<p>The Learning System produces an export query that contains <em>all</em> of the upcoming classes scheduled 
	with enrollment information for each. This query is called GBC_CURRENT_COURSE_INFO in the ELM Query Viewer</p>
	<p>With this procedure, we are taking that export (as a
	CSV file (comma separated values)) and uploading it into LSApp where we will process it. We look at each class 
	within LSApp and update it with the enrollment data from ELM.</p>
	</p>
</div>
</div>

</div>

<div class="col-md-6">

<div class="card ">


<ul class="list-group list-group-flush ">
<li class="list-group-item  ">
	<a href="https://learning.gov.bc.ca/psc/CHIPSPLM_1/EMPLOYEE/ELM/q/?ICAction=ICQryNameURL=PUBLIC.GBC_CURRENT_COURSE_INFO"
		target="_blank"
		rel="noopener"
		class="btn btn-success btn-block">
		Run GBC_CURRENT_COURSE_INFO Query on ELM
	</a>
	<div class="alert alert-warning mt-1">Make sure the date format is YYYY/MM/DD</div>
</li>
<li class="list-group-item ">Choose the "CSV Text File" link along the top and "Save" the file (will go to your "Downloads" folder)</li>
<li class="list-group-item ">Choose the browse button below, find the file in your downloads folder, then choose the upload button</li>
</ul>
<div class="card-body">
<form enctype="multipart/form-data" method="post" 
									accept-charset="utf-8" 
									class="up" 
									action="elm-sync-controller.php">
	<label>Current Course Stats CSV:<br>
		<input type="file" name="elmfile" class="form-control-file">
	</label>
	
	<input type="submit" class="btn btn-primary btn-block mt-3" value="Upload Current Course Stats CSV file">
</form>
</div>
</div>

</div>
</div>


<?php endif ?>






</div>
</div>
</div>


<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>
	
<?php endif ?>