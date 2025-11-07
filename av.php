<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>Audio Visual Thing</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<?php $avid = (isset($_GET['avid'])) ? $_GET['avid'] : 0; ?>
<?php $deets = getAV($avid) ?>
<?php if(is_array($deets)): ?>
<div class="col-md-6">
<a href="av-dashboard.php">AV Inventory List</a>
<div class="card">
<div class="card-header">
<div class="float-end">
<?php if(isSuper()): ?>
<form method="post" action="av-delete.php">
<input type="hidden" name="avid" value="<?= $deets[0] ?>">
<input type="submit" value="Delete" class="btn btn-sm btn-danger del">
</form>
<?php endif ?>
<a href="av-update.php?avid=<?= $avid ?>" class="btn btn-secondary">Edit</a>
</div>
<h1 class="card-title"><?= $deets[3] ?></h1>
</div>
<div class="card-body">
<div>Type: <?= $deets[2] ?></div>
<div><?= $deets[4] ?></div>

<div>Condition: <?= $deets[5] ?></div>
<div>Assigned to: <a href="class.php?classid=<?= $deets[1] ?>"><?= $deets[1] ?></a></div>
<?php //print_r($deets); ?>


</div>
</div> <!--/.card-->

</div>

<?php else: ?>
<div class="col-md-6">
	<h2>AV Not Found</h2>
	<p>Email the Learning Support Admin Team <<a href="mailto:learning.centre.admin@gov.bc.ca">learning.centre.admin@gov.bc.ca</a>> with any questions or concerns.</p>
	<p><img src="img/TrollFace.jpg" width="300px"></p>
</div>	
<?php endif ?>

</div>
</div>

<?php require('templates/javascript.php') ?>


<?php require('templates/footer.php') ?>