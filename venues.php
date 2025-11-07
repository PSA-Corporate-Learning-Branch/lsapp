<?php require('inc/lsapp.php') ?>


<?php getHeader() ?>
<title>Venues</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<?php if(canAccess()): ?>


<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-8">
<div class="btn-group float-end">
<a href="venue-request.php" class="btn btn-primary float-end">New Venue</a>
<?php if(isAdmin()): ?>
<a href="venues-dashboard.php" class="btn btn-primary float-end">Venues Dashboard</a>
<?php endif ?>
</div>
<h1>Venues</h1>
<div id="venuelist">



<input class="search form-control  mb-3" placeholder="search">
<?php 
$u = fopen('data/venues.csv', 'r');
// Remove the headers
fgetcsv($u);

?>

<table class="table table-sm table-striped table-hover">
<thead>
<tr>
	<th></th>
	<th><a href="#" class="sort" data-sort="name">Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
	<th><a href="#" class="sort" data-sort="region">Region</a></th>
</tr>
</thead>
<tbody class="list">
<!-- VenueID,VenueName,ContactName,BusinessPhone,Address,City,StateProvince,ZIPPostal,email,Notes,Active,Union,Region-->
<?php while ($row = fgetcsv($u)): ?>
<?php //if($row[10] == 'TRUE'): ?>
	<tr>
		<td>

		</td>
		<td class="name"><a href="/lsapp/venue.php?vid=<?= $row[0] ?>"><?= $row[1] ?></a></td>
		<td class="city">
			<a href="city.php?name=<?= $row[5] ?>"><?= $row[5] ?></a>
			<div style="display: none" class="addy"><?= $row[4] ?></div>
		</td>
		<td class="region"><a href="region.php?name=<?= $row[12] ?>"><?= $row[12] ?></a></td>
	</tr>
<?php //endif ?>
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
		valueNames: [ 'name','city','region', 'addy' ]
	};
	var venues = new List('venuelist', options);

});
</script>
<?php include('templates/footer.php') ?>