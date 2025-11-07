<?php require('inc/lsapp.php') ?>
<?php $vid = (isset($_GET['vid'])) ? $_GET['vid'] : 0; ?>
<?php $json = (isset($_GET['json'])) ? $_GET['json'] : 0; ?>
<?php $deets = getVenue($vid) ?>
<?php 
if($json):

	header('Content-Type: application/json');
	echo json_encode($deets);

else: ?>
<?php getHeader() ?>
<title><?= $deets[1] ?></title>
<link href="/lsapp/css/summernote-bs4.css" rel="stylesheet">

<style>
.venuevote a:hover { text-decoration: none }
</style>
<?php getScripts() ?>
<body class="bg-light-subtle">
<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">




<?php if(is_array($deets)): ?>
<div class="col-md-4">

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
<div class="float-end w-25 alert alert-success text-center venuevote">
	<span class="badge badge-light"><?= h($deets[13]) ?> Likes</a></span> <br>
	<a href="venue-vote.php?venueid=<?= $deets[0] ?>" style="font-size: 22px; font-weight: 1000">&#128077;</a>
	<a href="venue-vote.php?venueid=<?= $deets[0] ?>&updown=down" style="font-size: 22px; font-weight: 1000">&#128078;</a>
</div>
<?= $deets[4] ?><br>
<?= $deets[5] ?>, <?= $deets[6] ?><br>
<?= $deets[7] ?>

<div class="contact p-3">
<strong><?= $deets[2] ?></strong><br>
<?= $deets[8] ?><br>
<?= $deets[3] ?>
</div>

<div><strong>Cancellation policy:</strong></div>
<div><?= $deets[9] ?></div>
<hr>
<h5 class="mt-3">Rooms at this venue</h5>
<ul class="list-group">
<?php $rooms = getVenueRooms($vid) ?>
<?php foreach($rooms as $room): ?>
<li class="list-group-item">
	<h6><strong><?= $room[2] ?></strong></h6>
	<div>Capacity: <?= $room[3] ?></div>
	<div>Dimensions: <?= $room[4] ?></div>
	<div><?= $room[5] ?></div>
</li>
<?php endforeach ?>
</ul>




</div>
</div>


<?php if(isAdmin()): ?>
<!-- RoomID,VenueID,RoomName,RoomCapacity,RoomDimensions,RoomNotes,Likes-->
<div class="addaroom card my-3">
<div class="card-header">
	<h4 class="card-title">Add a room</h4>
</div>
<div class="card-body">
<div class="alert alert-warning">
	This feature is a work in progress. While you can add new rooms, once added,
	you cannot yet edit or delete them. If you add a room and need to update it,
	please contact <a href="mailto:Allan.Haggett@gov.bc.ca">Allan.Haggett@gov.bc.ca</a>
	for assistance.
</div>
<form method="post" action="venue-room-create.php">
	<input type="hidden" name="VenueID" id="VenueID4room" value="<?= $vid ?>">
	<label for="RoomName">Room Name</label>
	<input type="text" name="RoomName" id="RoomName" class="form-control">
	<label for="RoomName">Room Capacity</label>
	<input type="text" name="RoomCapacity" id="RoomCapacity" class="form-control">
	<label for="RoomName">Room Dimensions</label>
	<input type="text" name="RoomDimensions" id="RoomDimensions" class="form-control">
	<label for="RoomName">Room Notes</label>
	<textarea name="RoomNotes" id="RoomNotes" class="form-control"></textarea>
	<input type="submit" class="btn btn-block btn-success mt-3" value="Add Room">
</form>
</div>
</div>
<?php endif ?>


</div>
<div class="col-md-3">
<div class="card mb-4">
<div class="card-header">
	<h4 class="card-title">
		Notes 
	</h4>
</div>
<div class="card-body">

<form action="venue-note-create.php" method="post">
<input type="hidden" name="VenueID" id="VenueID" value="<?= h($deets[0]) ?>">
<textarea name="Note" id="Note" class="form-control summernote" required></textarea>
<input type="submit" class="btn btn-sm btn-block btn-primary" value="Add Note">
</form>
</div>
<ul class="list-group list-group-flush">
<?php $notes = getVenueNotes($deets[0]) ?>
<?php if($notes): ?>
<?php foreach($notes as $note): ?>
<li class="list-group-item">
	<div class="float-end">
		<form method="post" action="venue-note-delete.php">
		<input type="hidden" name="venueid" value="<?= $deets[0] ?>">
		<input type="hidden" name="noteid" value="<?= $note[0] ?>">
		<input type="submit" value="Delete" class="btn btn-sm btn-danger del">
		</form>
	</div>
	<small>On <?= h($note[2]) ?> <?= h($note[3]) ?> said:</small><br>
	<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $note[4]) ?>
	<?= $n ?>
</li>
<?php endforeach ?>
<?php endif ?>

</ul>

</div><!-- /.card -->
</div>
<div class="col-md-5">

<h2>Upcoming Classes</h2>
<table class="table table-sm table-striped">
<thead>
<tr>
	<th>Date</th>
	<th><a href="#" class="sort" data-sort="name">Course Name</a></th>
	<th><a href="#" class="sort" data-sort="city">City</a></th>
	<th><a href="#" class="sort" data-sort="status">Status</a></th>
</tr>
</thead>
<tbody class="list">
<?php $classes = getVenueClasses($vid) ?>
<?php foreach($classes as $uclass): ?>
<?php 
$status = '';
if($uclass[1] == 'Inactive') $status = 'cancelled';
?>
<tr class="<?= $status ?>">
	<td>
		<a href="class.php?classid=<?= $uclass[0] ?>"><?php echo goodDateShort($uclass[8],$uclass[9]) ?></a><br>
	</td>
	<td class="name"><a href="course.php?courseid=<?= $uclass[5] ?>"><?= $uclass[6] ?></a></td>
	<td class="city"><a href="city.php?name=<?= $uclass[25] ?>"><?= $uclass[25] ?></a></td>
	<td class="status"><span class="badge badge-light"><?= $uclass[1] ?></span></td>
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
<script src="/lsapp/js/summernote-bs4.js"></script>
<script>
	$('.summernote').summernote({
		toolbar: [
			// [groupName, [list of button]]
			['style', ['bold', 'italic']],
			['para', ['ul', 'ol']],
			['color', ['color']],
			['link']
		],
		placeholder: 'Type here',
		hint: {
			<?php $peeps = getPeopleAll() ?>
			mentions: [
			<?php foreach($peeps as $p): ?>
			'<?= $p[0] ?>',
			<?php endforeach ?>
			],
			match: /\B@(\w*)$/,
			search: function (keyword, callback) {
			callback($.grep(this.mentions, function (item) {
				return item.indexOf(keyword) == 0;
			}));
			},
			content: function (item) {
				return '@' + item;
			}
		}
		
	});	
	</script>

<?php require('templates/footer.php') ?>
<?php endif ?>