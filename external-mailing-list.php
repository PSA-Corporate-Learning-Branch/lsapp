<?php 
require('inc/lsapp.php');
opcache_reset();
?>
<?php getHeader() ?>
<title>External Mailing List</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<?php if(canAccess()): ?>
<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-4">

<div class="card mb-3">
<div id="userlist">
<div class="card-header">
<a href="#" class="btn btn-sm btn-success float-end addemail">Add Email</a>
<h2 class="card-title">Mailing List</h2>
<div class="card-subtitle">Mostly external folks who don't have IDIRs</div>
</div>
<div class="card-body">
<form method="post" action="external-list-create.php" id="newform" class="d-none">
<input type="text" class="form-control" name="email" id="emailaddress">
<input type="submit" class="btn btn-success" value="Add new email address">
<hr>
</form>
<input class="search form-control" placeholder="search">
</div>
<ul class="list list-group list-group-flush">
<?php $people = getExternalMailList(); ?>
<?php foreach($people as $p): ?>
<li class="list-group-item">
	<form method="post" action="external-list-delete.php" class="float-end">
		<input type="hidden" name="eid" value="<?= $p[0] ?>">
		<input type="submit" class="btn btn-light btn-sm del" value="x">
	</form> 
	<span class="email"><?= $p[1] ?></span>
</li>
<?php endforeach ?>
</ul>
</div>
</div> <!-- /#userlist -->

</div> <!-- /.col -->
<div class="col-md-4">
<h2>Send the weekly update</h2>
<ul class="list-group">
<li class="list-group-item">
Step 1.
<a class="btn btn-block btn-primary" href="elm-sync-upload.php">Synchronize with ELM</a>
</li>
<li class="list-group-item">
Step 2.
<a class="btn btn-block btn-primary export" href="data/elm.csv">Export Current Course Stats</a>
<div class="download"></div>
</li>
<li class="list-group-item">
Step 3.
<a class="btn btn-block btn-primary" href="mailto:Corporatelearning.admin@gov.bc.ca?BCC=
<?php foreach($people as $p): ?>
<?= $p[1] ?>;
<?php endforeach ?>
&body=Hello,%0D%0D
Please see the attached weekly course stats.%0D%0D
Access LSApp for more up-to-date statistics throughout the week.%0D%0D
Thank you,%0D%0DThe Corporate Learning Branch&subject=Weekly Learning System 
enrollment stats as of <?php echo date('D M dS') ?>">
	Email everyone
</a>
<div class="alert alert-warning mt-1">Don't forget to attach the file from step 2 in your email</div>
</li>
</ul>
</div>
</div> <!-- /.row -->

<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>

<?php require('templates/javascript.php') ?>
<script>
$(document).ready(function(){
	var peopleoptions = {
		valueNames: [ 'email' ]
	};
	var peeps = new List('userlist', peopleoptions);
	
	$('.export').on('click',function(e){
		e.preventDefault();
		
		$('.download').html('<div class="alert alert-success mt-1">Generating Excel file, please standby!</div>');
		
		$.ajax({type:"GET", 
			url:"elm-excel-export.php", 
			dataType:"text", 
			success: function(data) {
				console.log(data);
			}
		});
		
		setTimeout(function(){
			$('.download').html('<a class="btn btn-block btn-success mt-1" href="data/backups/ELM-upcoming-classes-export-asof-<?php echo date('Y-m-d') ?>.xlsx">Download</a>');		
		},3000);
		
	});
	$('.addemail').on('click', function(e){
		e.preventDefault();
		$('#newform').toggleClass('d-none');
		$('#emailaddress').focus();
	});
});
</script>

<?php include('templates/footer.php') ?>