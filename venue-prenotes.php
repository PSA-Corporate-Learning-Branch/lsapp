<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>Venue</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<?php $vid = (isset($_GET['vid'])) ? $_GET['vid'] : 0; ?>
<?php $deets = getVenue($vid) ?>


<?php if(is_array($deets)): ?>
<div class="col-md-6">

<div class="card">
<div class="card-header">
<div class="float-end">
			<?php if(isSuper()): ?>
			<form method="post" action="venue-delete.php">
			<input type="hidden" name="VenueID" value="<?= $deets[0] ?>">
			<input type="submit" value="Delete" class="btn btn-sm btn-danger del">
			</form>
			<?php endif ?>
<a href="venue-update.php?vid=<?= $vid ?>" class="btn btn-secondary">Edit</a></div>
<h1 class="card-title"><?= $deets[1] ?></h1>
Region: <a href="region.php?name=<?php echo urlencode($deets[12]) ?>"><?= $deets[12] ?></a>
</div>
<div class="card-body">
<div class="float-end w-25 alert alert-success text-right">
	<span class="badge badge-light"><?= h($deets[13]) ?> Likes</a></span> 
	<a href="venue-vote.php?venueid=<?= $deets[0] ?>" style="font-size: 22px; font-weight: 1000">&#8679;</a>
	<a href="venue-vote.php?venueid=<?= $deets[0] ?>&updown=down" style="font-size: 22px; font-weight: 1000">&#8681;</a>
</div>
<?= $deets[4] ?><br>
<?= $deets[5] ?>, <?= $deets[6] ?><br>
<?= $deets[7] ?>

<div class="contact p-3">
<strong><?= $deets[2] ?></strong><br>
<?= $deets[8] ?><br>
<?= $deets[3] ?>
</div>

<div class="note p-3"><?= $deets[9] ?></div>
</div>
</div>

</div>
<div class="col-md-6">

<h2>Upcoming Classes</h2>
<table class="table table-sm table-striped">
<thead>
<tr>
	<th>Date</th>
	<th><a href="#" class="sort" data-sort="name">Course Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
</tr>
</thead>
<tbody class="list">
<?php $classes = getVenueClasses($vid) ?>
<?php foreach($classes as $uclass): ?>
<tr>
	<td>
		<a href="class.php?classid=<?= $uclass[0] ?>"><?php echo goodDateShort($uclass[8],$uclass[9]) ?></a><br>
	</td>
	<td class="name"><a href="course.php?courseid=<?= $uclass[5] ?>"><?= $uclass[6] ?></a></td>
	<td class="city"><a href="city.php?name=<?= $uclass[25] ?>"><?= $uclass[25] ?></a></td>
</tr>
<?php endforeach ?>
</tbody>
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