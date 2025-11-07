<?php 

require('inc/lsapp.php');

$classid = (isset($_GET['classid'])) ? $_GET['classid'] : 0;
$venueid = (isset($_GET['vid'])) ? $_GET['vid'] : 0;
$deets = getClass($classid);

$tbdtest = explode('TBD - ',$deets[25]);
if(isset($tbdtest[1])){
	$city = $tbdtest[1];
} else {
	$city = $deets[25];
}

$vens = getVenuesByCity($city);

$tmp = array();
// loop through everything and add the start date to
// the temp array
foreach($vens as $line) {
	$tmp[] = $line[13];
}
// Sort the whole kit and kaboodle by start date from
// oldest to newest
array_multisort($tmp, SORT_DESC, $vens);

if(!$venueid) {
	$firstven = $vens[0][0];
} else {
	$firstven = $venueid;
}
$venuedeets = getVenue($firstven);

?>




<?php getHeader() ?>
<title>
<?php if($deets[4] == 'Dedicated'): ?>
DEDICATED | 
<?php endif ?>
<?php if($deets[7]): ?>
<?= h($deets[7]) ?> | 
<?php endif ?>
<?php print goodDateLong($deets[8],$deets[9]) ?> | 
<?= h($deets[45]) ?> <?= h($deets[6]) ?>. 
<?= h($deets[24]) ?> in <?= $city ?>. 

<?php if($deets[14]): ?>
<?= h($deets[14]) ?> facilitating. 
<?php else: ?> 
Unknown facilitating. 
<?php endif ?>
</title>
<meta name="description" content="Learning Support Adminstration Application (LSApp)">
<style>
.matnote {
	font-size: 10px;
}
.canceled {
	text-decoration: strikethrough;
}
</style>
<link href="/lsapp/css/summernote-bs4.css" rel="stylesheet">
<?php getScripts() ?>

<?php getNavigation() ?>

<body>
<?php if(canAccess()): ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">

<div class="col-md-12">
<div class="row">
<div class="col-md-7">
<div class=""><a href="venues-dashboard.php">Venues Dashboard</a></div>
<div class="jumbotron py-3 mb-2">
<div class="row">
<div class="col-md-7">
	
	<h2><a href="course.php?courseid=<?= $deets[5] ?>"><?= $deets[6] ?></a></h2>
	<h3><a href="class.php?classid=<?= $deets[0] ?>"><?php print goodDateLong($deets[8],$deets[9]) ?></a></h3>
		<h4>In <a href="city.php?name=<?php echo urlencode($city) ?>"><?= $city ?></a></h4>

</div>
<div class="col-md-5">
	<div><strong>Class request note:</strong></div>
	<?= $deets[32] ?>
</div>
</div>

</div>
<div class="dropdown">
  <a class="btn btn-primary dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    Venues in <?= $city ?>
  </a>

  <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
	<?php $count = 0 ?>
	<?php foreach($vens as $ven): ?>
	<a class="dropdown-item" href="venue-inquire.php?classid=<?= $deets[0] ?>&vid=<?= $ven[0] ?>">
		<?php if($ven[13] > 0): ?>
		<span class="bade badge-light"><?= $ven[13] ?> likes</span>
		<?php else: ?>
		<span class="badge badge-light">0 likes</span>
		<?php endif ?>
		<?= $ven[1] ?>
	</a>
	<?php endforeach ?>
  </div>
</div>
</div>
<div class="col-md-5">

<h3>5 classes in <a href="city.php?name=<?php echo urlencode($city) ?>"><?= $city ?></a></h3>
<div>Starting from two days before this class</div>
<?php 
$classes = getClasses();
// Create a temp array to hold course names for sorting
$tmp = array();
// Loop through the whole classes and add start dates to the temp array
foreach($classes as $line) {
	$tmp[] = $line[8];
}
// Use the temp array to sort all the classes by start date
array_multisort($tmp, SORT_ASC, $classes);
 ?>

<table class="table table-sm">
<tr>
	<th width="140">Class Date</th>
	<th>Course Name</th>
	<th>Venue</th>
	<th>Status</th>
</tr>

<?php $count = 0  ?>
<?php foreach($classes as $class): ?>
<?php	
$tbdtest = explode('TBD - ',$class[25]);
if(isset($tbdtest[1])){
	$othercity = $tbdtest[1];
} else {
	$othercity = $class[25];
}
// We only wish to see classes which have an end date greater than today
$today = date('Y-m-d');

$aweekbefore = twoDaysBefore($deets[8]);

if($class[9] < $aweekbefore) continue;
if($othercity == $city):
$count++;
if($count > 5) continue;
$canceled = '';
if($class[1] == 'Inactive') $canceled = 'cancelled';
?>
<tr class="<?= $canceled ?>">
	<td class="text-right">
		<a href="/lsapp/class.php?classid=<?= $class[0] ?>">
		<?php echo goodDateShort($class[8],$class[9]) ?>
		</a>
	</td>
	<td>
		<a href="course.php?courseid=<?= $class[5] ?>"><?= $class[6] ?></a>
	</td>
	<td>
		<a href="venue.php?vid=<?= $class[23] ?>"><?= $class[24] ?></a>
	</td>
	<td>
		<span class="badge badge-light"><?= $class[1] ?></span>
	</td>
</tr>
<?php endif ?>
<?php endforeach ?>
</table>

</div>
</div>


</div>
<?php $notes = getNotes($classid) ?>
<?php if($notes): ?>
<div class="col-md-2">
<h4>Class Notes</h4>
<ul class="list-group">
<?php foreach($notes as $note): ?>
<li class="list-group-item">
	<small>On <?= h($note[2]) ?> <?= h($note[3]) ?> said:</small><br>
	<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $note[4]) ?>
	<?= $n ?>
	
</li>
<?php endforeach ?>
</ul>
</div>
<?php endif ?>
<div class="col-md-4">
<h4>Attempt to book at:</h4>
<div class="card">
<div class="card-header">

		
	<h2><a href="venue.php?vid=<?= $venuedeets[0] ?>"><?= $venuedeets[1] ?></a></h2>
	<div class="venueaddress">
	<?= h($venuedeets[2]) ?><br>
	<?= h($venuedeets[3]) ?><br>
	<?= h($venuedeets[4]) ?><br>
	<?= h($venuedeets[5]) ?>, <?= h($venuedeets[6]) ?> <?= h($venuedeets[7]) ?>
	</div>
	
</div>
<div class="card-body">

	<div class="alert alert-warning">
	<strong>Cancellation policy:</strong><br>
	<?= $venuedeets[9] ?>
	</div>

	<ul class="list-group">
	<?php $notes = getVenueNotes($venuedeets[0]) ?>
	<?php if($notes): ?>
	<?php foreach($notes as $note): ?>
		<li class="list-group-item">
		<small>On <?= h($note[2]) ?> <?= h($note[3]) ?> said:</small><br>
		<?php $n = preg_replace('/(^|\s)@([\w_\.]+)/', '$1<a href="person.php?idir=$2">@$2</a>', $note[4]) ?>
		<?= $n ?>
		</li>
	<?php endforeach ?>
	<?php endif ?>

	</ul>



</div>
</div>
</div>





<div class="col-md-2">
	<h3>Booking Notes</h3>
	<ul class="list-group">
	<?php $bnotesflip = getBookingNotes($classid) ?>
	<?php $bnotes = array_reverse($bnotesflip) ?>
	<?php if(isset($bnotes)): ?>
	<?php foreach($bnotes as $note): ?>
	<li class="list-group-item">
	<!-- creqID,ClassID,Date,NotedBy,Note-->
		<!--<div class="float-end"><small><a class="" href="#?NoteID=<?= h($note[0]) ?>">delete</a></small></div>-->
		<small>On <?= h($note[2]) ?> <?= h($note[3]) ?> said:</small><br>
		<?= h($note[4]) ?><br>
		
	</li>
	<?php endforeach ?>
	<?php endif ?>
	</ul>
	<hr>
	<form action="note-book-create.php" method="post">
	<input type="hidden" name="ClassID" id="ClassID" value="<?= h($deets[0]) ?>">
	<textarea name="Note" id="Note" class="form-control summernote" required placeholder="Add your booking note here"></textarea>
	<input type="submit" class="btn btn-sm btn-block btn-primary" value="Add Note">
	</form>
	

</div>

<div class="col-md-4">
	<h3>Email Template</h3>
	<div id="summernote">

	<p>Hi <?= $venuedeets[2] ?>,</p>

	<p>We are planning a <strong><?= $deets[6] ?></strong> workshop on 
	<strong><?php print goodDateLong($deets[8],$deets[9]) ?>
	from <?= $deets[10] ?> 
	<?php if($deets[9] > $deets[8]): ?>
	<em>(both days)</em>
	<?php endif ?>
	</strong> 
	in <?= $city ?> 
	and we are hoping you might have the space to accommodate us.</p>

	<p>This will be a <strong><?= $deets[17] ?> day(s) event</strong> and we are expecting a maximum of <strong><?= $deets[12] ?> participants</strong>.</p>

	<p><strong>Below are our requirements and desired room set-up:</strong></p>

	<?php // checklistID,Manuals,Handouts,CourseName,Resources,StandardSupplyKit,AdditionalSupplies,ProjectorType,
	//AdditionalTech,AudioSpeakers,AttendanceRoster,Equipment,RoomSetup,Notes,14-OffCampusShipping,OffCampusNotes,OffCampusEquipment,OffCampusRoomSetup
	?>
	<?php $check = getChecklist($deets[5]) ?>
	<?php if($check): ?>

	<table class="table" width="600">
	<tr>
		<td width="140" style="padding: 20px; text-align:right"><strong>Venue Notes</strong></td>
		<td style="padding: 20px;"><?= $check[15] ?></td>
	</tr>
	<tr>
		<td style="padding: 20px; text-align:right"><strong>Equipment</strong></td>
		<td style="padding: 20px;"><?= $check[16] ?></td>
	</tr>
	<tr>
		<td style="padding: 20px; text-align:right"><strong>Room Set up</strong></td>
		<td style="padding: 20px;"><?= $check[17] ?></td>
	</tr>
	</table>
	<?php else: ?>
	<p><strong>LSApp doesn't have a checklist for this course ...</strong></p>
	<?php endif ?>

	<p>Thanks for your help! If you have any questions, please do not hesitate to ask.</p>
	
	<em>Please note: we are prohibited by policy from engaging catering services.</em>
	
	</div>
	<?php if($venuedeets[8]): ?>
	<a href="mailto:<?= $venuedeets[8] ?>?bcc=learning.centre.admin@gov.bc.ca&subject=Venue Required - '<?= $deets[6] ?>' - <?php print goodDateLong($deets[8],$deets[9]) ?> - <?= $city ?>" 
		class="btn btn-block btn-primary mt-2 email text-uppercase copy"
		data-clipboard-target="#summernote"
		data-classid="<?= $deets[0] ?>">
		Email <?= $venuedeets[8] ?>
	</a>
	<?php else: ?>
	<div class="alert alert-danger mt-3">No email address provided!</div>
	<?php endif ?>
	<div class="alert alert-success mt-3"> <!--while copying the contents of the message to your clipboard.-->
		<strong>Clicking the button above will automatically add a booking note to this class for this venue.</strong>
		<ul class="mt-3">
			<li>Please make any required edits for this message, select the whole thing and copy it to your clipboard.</li>
			<li>Then click the above button, and a new email message will open addressed to the venue (with the admin email BCC'ed), with the subject line populated.</li>
			<li>Simple click into the message body and paste the message. </li>
			<li>Ensure the email is being sent <em>from</em> learning.centre.admin@gov.bc.ca</li>
			<li>Ensure that your signature utilizes the admin phone number rather than your personal one</li>
			<li>After you click send, visit the admin inbox and move the message (<em>remember we BCC'ed the message to the venue here</em>) into the "Venues" Outlook folder</li>
			
		</ul>
	</div>
	
</div>
</div>
<?php else: // if canAccess() ?>
<?php include('templates/noaccess.php') ?>
<?php endif ?>

<?php require('templates/javascript.php') ?>

<script src="js/clipboard.min.js"></script>
<script src="/lsapp/js/summernote-bs4.js"></script>


<script>

$(document).ready(function(){
	
	var clipboard = new Clipboard('.copy');
	
	$('#summernote').summernote();
	
        $('.email').on('click',function(e){
			
			
				
				
                var url = 'note-book-create.php';
                $.ajax({
                        type: "POST",
                        url: url,
                        data: {
                                Note: 'Inquired at <a href="venue.php?vid=<?= $venuedeets[0] ?>"><?= $venuedeets[1] ?></a>',
                                ClassID: '<?= $classid ?>'
                        },
                        success: function(data)
                        {
                                alert('Updated class with booking note')
                        },
                        statusCode:
                        {
                                403: function() {}
                        }
                });
                //e.preventDefault();
        });
});
</script>
<?php require('templates/footer.php') ?>