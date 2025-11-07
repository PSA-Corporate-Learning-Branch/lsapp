<?php require('inc/lsapp.php') ?>


<?php getHeader() ?>
<title>Audio Visual</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<?php if(canAccess()): ?>


<div class="container">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">
<?php include('templates/admin-nav.php') ?>
</div>
<div class="col-md-10">
<div class="btn-group float-end">
	<a href="av-create.php" class="btn btn-success">New A/V</a>
	<a href="onenote:///Z:\The%20Learning%20Centre\2.%20Admin,%20Facilities%20&%20Ops\LSA's%20documents\OneNote\Learning%20Centre\AudioVisual.one#section-id={802D9D34-01CC-4387-9C26-CA63AD0190A4}&end" 
		class="btn btn-dark">
		
		A/V OneNote
	</a>
</div>
<h1>Audio Visual</h1>
<div id="venuelist">



<input class="search form-control  mb-3" placeholder="search">


<table class="table table-sm table-striped table-hover">
<thead>
<tr>
	<th><a href="#" class="sort" data-sort="status">Status</a></th>
	<th><a href="#" class="sort" data-sort="classid">Assigned Class</a></th>
	
	<th><a href="#" class="sort" data-sort="code">Code</a></th>
	<th><a href="#" class="sort" data-sort="deets">Description</a></th>
	<th><a href="#" class="sort" data-sort="type">Type</a></th>
	<th><a href="#" class="sort" data-sort="condition">Condition</a></th>
	

</tr>
</thead>
<tbody class="list">
<?php 
$u = fopen('data/audio-visual.csv', 'r');
// Remove the headers
fgetcsv($u);

?>
<!-- AVID,ClassID,Type,AVCode,4-Details,Condition,Status -->
<?php while ($row = fgetcsv($u)): ?>
<?php 
$stat = '';
if($row[6] == 'Missing') $stat = 'table-danger';
if($row[6] == 'Inactive') $stat = 'table-warning';
?>
	<tr class="<?= $stat ?>">
		<td class="status"><span class="badge badge-light"><?= $row[6] ?></span></td>
		<td class="classid"><a href="class.php?classid=<?= $row[1] ?>"><?= $row[1] ?></a></td>
		<td class="code"><a href="av.php?avid=<?= $row[0] ?>"><?= $row[3] ?></a></td>
		<td class="deets"><?= $row[4] ?></td>
		<td class="type"><?= $row[2] ?></td>
		<td class="condition"><?= $row[5] ?></td>
	</tr>
<?php endwhile ?>
<?php fclose($u) ?>
</tbody>
</table>

</div>
</div>
</div>


<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>


<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){

	$('.search').focus();
	
	var options = {
		valueNames: [ 'classid','type','code','deets','condition','status' ]
	};
	var venues = new List('venuelist', options);

});
</script>
<?php include('templates/footer.php') ?>