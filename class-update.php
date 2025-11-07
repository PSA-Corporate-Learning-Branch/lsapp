<?php require('inc/lsapp.php') ?>
<?php if(isAdmin()): ?>
<?php opcache_reset(); ?>
<?php 
if($_POST): 


	$fromform = $_POST;
	$user = LOGGED_IN_IDIR;
	$notadmin = 0;
	$f = fopen('data/classes.csv','r');
	$temp_table = fopen('data/classes-temp.csv','w');
	// pop the headers off the source file and start the new file with those headers
	$headers = fgetcsv($f);
	fputcsv($temp_table,$headers);
	
	if(isset($fromform['Dedicated'])) {
		$ded = 'Dedicated';
	} else {
		$ded = 'ELM';
	}
	if(isset($fromform['AttendanceReturned'])) {
		$attendance = 'Yes';
	} else {
		$attendance = 'Not';
	}
	if(isset($fromform['EvaluationsReturned'])) {
		$evals = 'Yes';
	} else {
		$evals = 'Not';
	}
	if(isset($fromform['EvaluationsSent'])) {
		$evalsent = 'Yes';
	} else {
		$evalsent = 'Not';
	}
	if(isset($fromform['VenueNotified'])) {
		$vnotified = 'Yes';
	} else {
		$vnotified = 'Not';
	}	
	$combinedtimes = h($fromform['StartTime']) . ' - ' . h($fromform['EndTime']);
	$editclass = Array(h($fromform['ClassID']),
					h($fromform['Status']),
					h($fromform['Requested']),
					h($fromform['RequestedBy']),
					$ded,
					h($fromform['CourseID']),
					h($fromform['CourseName']),
					h(trim(strtoupper($fromform['ItemCode']))),
					h($fromform['StartDate']),
					h($fromform['EndDate']),
					$combinedtimes,
					h($fromform['MinEnroll']),
					h($fromform['MaxEnroll']),
					h($fromform['ShipDate']),
					h($fromform['Facilitating']),
					removeOutlookSafeLinks($fromform['WebinarLink']),
					h($fromform['WebinarDate']),
					h($fromform['CourseDays']),
					h($fromform['Enrolled']),
					h($fromform['ReservedSeats']),
					h($fromform['PendingApproval']),
					h($fromform['Waitlisted']),
					h($fromform['Dropped']),
					h($fromform['VenueID']),
					h($fromform['VenueName']),
					h($fromform['VenueCity']),
					h($fromform['VenueAddress']),
					h($fromform['VenuePostalCode']),
					h($fromform['VenueContactName']),
					h($fromform['VenuePhone']),
					h($fromform['VenueEmail']),
					h($fromform['VenueAttention']),
					h($fromform['RequestNotes']),
					h($fromform['Shipper']),
					h($fromform['Boxes']),
					h($fromform['Weight']),
					h($fromform['Courier']),
					h($fromform['TrackingOut']),
					h($fromform['TrackingIn']),
					$attendance,
					$evals,
					$vnotified,
					h($fromform['Modified']),
					h($fromform['ModifiedBy']),
					h($fromform['Assigned']),
					h($fromform['DeliveryMethod']),
					h($fromform['CourseCategory']),
					h($fromform['Region']),
					h($fromform['CheckedBy']),
					h($fromform['ShippingStatus']),
					h($fromform['PickupIn']),
					h($fromform['avAssigned']),
					h($fromform['VenueCost']),
					h($fromform['VenueBEO']),
					h($fromform['StartTime']),
					h($fromform['EndTime']),
					h($fromform['CourseColor']),
					$evalsent,
					h($fromform['EvaluationsLink'])
		);
					
	
// As of 2020-12-01 (added evalssent and evallink)
// 0-ClassID,1-Status,2-RequestedOn,3-RequestedBy,4-Dedicated,5-CourseID,6-CourseName,7-ItemCode,8-ClassDate,9-EndDate,10-ClassTimes,
// 11-MinEnroll,12-MaxEnroll,13-ShipDate,14-Facilitating,
// 15-WebinarLink,16-WebinarDate,17-ClassDays,18-Enrollment,19-ReservedSeats,20-pendingApproval,21-Waitlisted,22-Dropped,
// 23-VenueID,24-Venue,25-City,26-Address,27-ZIPPostal,28-ContactName,29-BusinessPhone,30-email,
// 31-VenueAttention,32-Notes,33- Shipper,34-Boxes,35-Weight,36-Courier,37-TrackingOut,38-TrackingIn,
// 39-Attendance,40-EvaluationsReturned,41-VenueNotified,42-Modified,43-ModifiedBy,44-Assigned,
// 45-DeliveryMethod,46-CourseCategory,47-tblClasses.Region,
// 48-CheckedBy,49-ShippingStatus,50-PickupIn,
// 51-avAssigned,venueCost,venueBEO,StartTime,55-EndTime,56-CourseColor,57-EvaluationsSent,58-EvaluationsLink

	
	$cid = $fromform['ClassID'];
	while (($data = fgetcsv($f)) !== FALSE){
		
		if($data[0] == $cid) {
			fputcsv($temp_table,$editclass);
		} else {
			fputcsv($temp_table,$data);
		}
	}
	fclose($f);
	fclose($temp_table);

	rename('data/classes-temp.csv','data/classes.csv');
	header('Location: /lsapp/class.php?classid=' . $cid);

else:
?>
<?php $cid = (isset($_GET['classid'])) ? $_GET['classid'] : 0 ?>
<?php $deets = getClass($cid) ?>
<?php if(is_array($deets)): ?>
<?php getHeader() ?>
<title>Update Class</title>
<link href="/lsapp/css/summernote-bs4.css" rel="stylesheet">
<?php getScripts() ?>

<body class="">

<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">

<form method="post" action="class-update.php">
<?php $now = date('Y-m-d H:i:s') ?>
<input type="hidden" name="Region" value="<?= h($deets[47]) ?>">
<input type="hidden" name="Requested" value="<?= h($deets[2]) ?>">
<input type="hidden" name="RequestedBy" value="<?= h($deets[3]) ?>">
<input type="hidden" name="CourseDays" id="CourseDays" value="<?= h($deets[17]) ?>">
<input type="hidden" name="CourseCategory" value="<?= h($deets[46]) ?>">
<input type="hidden" name="Modified" id="Modified" value="<?= date('Y-m-dH:i:s') ?>">
<input type="hidden" name="ModifiedBy" id="ModifiedBy" value="<?php echo LOGGED_IN_IDIR ?>">
<input type="hidden" name="avAssigned" id="avAssigned" value="<?= h($deets[51]) ?>">
<input type="hidden" name="ClassID" value="<?= h($deets[0]) ?>">
<input type="hidden" name="cid" value="<?= h($deets[0]) ?>">
<input type="hidden" name="CourseID" id="CourseID" value="<?= h($deets[5]) ?>">
<input type="hidden" name="CourseName" id="CourseName" value="<?= h($deets[6]) ?>">
<input type="hidden" name="CourseColor" id="CourseColor" value="<?= h($deets[56]) ?>">

<div class="">
<div class="">
<button type="submit" class="float-end btn btn-success">Save Class</button>
<a class="float-end btn btn-light mr-3" href="class.php?classid=<?= $deets[0] ?>">View</a>	
<h2 class=""><?= $deets[6] ?></h2>
<small class="">Requested on <?= h($deets[2]) ?> by <a href="/lsapp/person.php?idir=<?= h($deets[3]) ?>"><?= h($deets[3]) ?></a></small>
</div>
<div class="">
	<div class="row">

	<div class="col-3">
	<label>
	<?php if($deets[4] == 'Dedicated'): ?>
		<input type="checkbox" class="" name="Dedicated" id="Dedicated" checked>
	<?php else: ?>
		<input type="checkbox" class="" name="Dedicated" id="Dedicated">
	<?php endif ?>
	Dedicated?
	</label>
	</div>
	</div>

	


	
	<div class="row">
	
		<div class="col-md-2">
			<label>Method
			<select name="DeliveryMethod" id="DeliveryMethod" class="form-select">
			<?php $dms = getDeliveryMethods() ?>
			<?php foreach($dms as $dm): ?>
			<?php if($dm == $deets[45]): ?>
			<option selected><?= $dm ?></option>
			<?php else: ?>
			<option><?= $dm ?></option>
			<?php endif ?>
			<?php endforeach ?>
			</select>
			</label>
		</div>
		<div class="col-md-2">
				<label>ELM Status
				<select name="Status" id="Status" class="form-select">
				<?php $statuses = array('Draft','Requested','Pending','Active','Closed','Inactive','Deleted') ?>
				<?php foreach($statuses as $s): ?>
				<?php if($s == $deets[1]): ?>
				<option selected><?= $s ?></option>
				<?php else: ?>
				<option><?= $s ?></option>
				<?php endif ?>
				<?php endforeach ?>
				</select>
				</label>
		</div>
		<div class="col-md-2">
			<label>Facilitating
			<input type="text" class="form-control Facilitating facnote" name="Facilitating" value="<?= $deets[14] ?>">
			</label>


		</div>
		<div class="col-md-2">
			<label>Assigned
			<select class="form-select Assigned" name="Assigned">
			<option>Unassigned</option>
			<?php getPeople($deets[44]) ?>
			</select>
			</label>
		</div>
		<div class="col-md-2">
				<label>Min
				<input type="text" class="form-control" name="MinEnroll" value="<?= h($deets[11]) ?>">
				</label>
		</div>
		<div class="col-md-2">
				<label>Max
				<input type="text" class="form-control" name="MaxEnroll" value="<?= h($deets[12]) ?>">
				</label>
		</div>
	</div>
	<div class="row">
		<div class="col-2">
			<label for="ItemCode">Item Code</label>
			<input type="text" class="form-control" name="ItemCode" id="ItemCode" value="<?= h($deets[7]) ?>">
		</div>
		<div class="col-md-2">
			<label for="StartDate">Start Date</label>
			<input type="text" class="form-select StartDate" name="StartDate" value="<?= h($deets[8]) ?>">
			
		</div>
		<div class="col-md-2">
			<label for="EndDate">End Date</label>
			<input type="text" class="form-select EndDate" name="EndDate" value="<?= h($deets[9]) ?>">
			
		</div>
		<div class="col-md-2">

				<label for="st">Start time</label>
				<input class="form-select starttime" id="st" type="text" name="StartTime" value="<?= h($deets[54]) ?>" required="required">
		</div>
		<div class="col-md-2">
				<label for="et">End time</label>
				<input class="form-select endtime" id="et" type="text" name="EndTime" value="<?= h($deets[55]) ?>" required="required">
	
		</div>
	</div>


	<div class="row d-none">
		<div class="col-md-1">
		<label>Enrolled 
		<input type="text" class="form-control" name="Enrolled" value="<?= h($deets[18]) ?>">
		</label>
		</div>
		<div class="col-md-1">
		<label>Reserved 
		<input type="text" class="form-control" name="ReservedSeats" value="<?= h($deets[19]) ?>">
		</label>
		</div>
		<div class="col-md-1">
		<label>Pending 
		<input type="text" class="form-control" name="PendingApproval" value="<?= h($deets[20]) ?>">
		</label>
		</div>
		<div class="col-md-1">
		<label>Waitlisted 
		<input type="text" class="form-control" name="Waitlisted" value="<?= h($deets[21]) ?>">
		</div>
		<div class="col-md-1">
		<label>Dropped 
		<input type="text" class="form-control" name="Dropped" value="<?= h($deets[22]) ?>">
		</label>
		</div>
	</div>

<hr>
<div class="row">
<div class="col-md-4">

<?php 
$tbdtest = explode('TBD - ',$deets[25]);

if(isset($tbdtest[1])) {
	$vs = getVenuesByCity($tbdtest[1]);
	$city = $tbdtest[1];
} else {
	$vs = getVenuesByCity($deets[25]);
	$city = $deets[25];
}
//print_r($vs);
?>
<div class="mb-3 p-3 bg-light-subtle rounded-3">
<div class="">
	<h3 class="">Venue</h3>
</div>
<div class="">

<h5>All venues in <?= $city ?></h5>

<select name="venuechoose" id="venuechoose" class="form-select">
<option></option>
<?php foreach($vs as $v): ?>
<option data-vid="<?= $v[0] ?>"><?= $v[1] ?></option>
<?php endforeach ?>
</select>
<hr>

<input type="hidden" class="form-control" name="VenueID" id="VenueID" value="<?= h($deets[23]) ?>">
<input type="text" class="form-control" name="VenueName" id="VenueName" value="<?= h($deets[24]) ?>" placeholder="Venue Name">
<input type="text" class="form-control" name="VenueCity" id="VenueCity" value="<?= h($deets[25]) ?>" placeholder="Venue City">
<input type="text" class="form-control" name="VenueAddress" id="VenueAddress" value="<?= h($deets[26]) ?>" placeholder="Venue Address">
<input type="text" class="form-control" name="VenuePostalCode" id="VenuePostalCode" value="<?= h($deets[27]) ?>" placeholder="Postal Code">
<input type="text" class="form-control" name="VenueContactName" id="VenueContactName" value="<?= h($deets[28]) ?>" placeholder="Contact Name">
<input type="text" class="form-control" name="VenuePhone" id="VenuePhone" value="<?= h($deets[29]) ?>" placeholder="Venue Phone">
<input type="text" class="form-control" name="VenueEmail" id="VenueEmail" value="<?= h($deets[30]) ?>" placeholder="Venue Email">
<div class="form-group">
<label for="VenueAttention">Venue Attention</label>
<input type="text" class="form-control" name="VenueAttention" value="<?= h($deets[31]) ?>">
</div>
<div class="form-group">
<label for="venueCost">Venue Cost</label>
<?= h($deets[51]) ?>
<input type="text" class="form-control" name="VenueCost" value="<?= h($deets[52]) ?>">
<input type="hidden" class="form-control" name="VenueBEO" value="<?= h($deets[53]) ?>">
</div>

</div>
</div>
</div> <!-- /.card -->


<div class="col-md-4">

<div class="mb-3 p-3 bg-light-subtle rounded-3">
<div class="">
<h2 class="">Shipping</h2>
</div>
<div class="">
	<label>
		<?php if($deets[41] == 'Yes'): ?>
		<input type="checkbox" class="" name="VenueNotified" id="EvaluationsReturned" checked>
		<?php else: ?>
		<input type="checkbox" class="" name="VenueNotified" id="EvaluationsReturned">
	<?php endif ?>
	Venue Notified?
	</label>
	<br>
<label for="ShippingStatus">Shipping Status</label>
<select name="ShippingStatus" id="ShippingStatus" class="form-select">
<?php $shippingstatuses = array('To Ship','Shipped','Arrived','Returned','No Ship') ?>
<?php foreach($shippingstatuses as $sstat): ?>
<?php if($sstat == $deets[49]): ?>
<option selected><?= $sstat ?></option>
<?php else: ?>
<option><?= $sstat ?></option>
<?php endif ?>
<?php endforeach ?>
</select>
<div class="row">
<div class="col-md-6">
<label for="ShipDate">Ship Date</label>
<input type="text" class="form-select ShipDate" id="ShipDate" name="ShipDate" placeholder="ShipDate" value="<?= h($deets[13]) ?>">

</div>
<div class="col-md-6">
<label>Shipper
<select class="form-select Shipper" name="Shipper">
<option>Unassigned</option>
<?php getPeople($deets[33]) ?>
</select>
</label>
</div>
</div>
<hr>
<div class="row mb-3">
<div class="col-md-6">
<input type="text" class="form-control" name="Boxes" placeholder="Boxes" value="<?= h($deets[34]) ?>">
</div>
<div class="col-md-6">
<input type="text" class="form-control" name="Weight" placeholder="Weight" value="<?= h($deets[35]) ?>">
</div>
</div>

<label for="CheckedBy">
<select class="form-select mb-3 CheckedBy" name="CheckedBy">
<option>Not Checked</option>
<?php getPeople($deets[48]) ?>
</select>
<select name="Courier" class="form-select mb-3">
<option>Select a courier</option>
<?php $couriers = getCouriers($deets[36]) ?>
<?php foreach($couriers as $courier): ?>
<?php if($courier[1] == $deets[36]): ?>
<option selected><?= $courier[1] ?></option>
<?php else: ?>
<option><?= $courier[1] ?></option>
<?php endif ?>
<?php endforeach ?>
</select>


<div class="row">
<div class="col-md-6">
<input type="text" class="form-control" name="TrackingOut" placeholder="TrackingOut" value="<?= h($deets[37]) ?>">
</div>
<div class="col-md-6">
<input type="text" class="form-control" name="TrackingIn" placeholder="TrackingIn" value="<?= h($deets[38]) ?>">
<label for="PickupIn">Incoming Pickup #</label>
<input type="text" class="form-control" name="PickupIn" id="PickupIn" value="<?= h($deets[50]) ?>">
</div>
</div>

</div>
</div> <!-- .card -->



</div>
<div class="col-md-4">

<div class="mb-3 p-3 bg-light-subtle rounded-3">
<div class="">
	<h3 class="">Misc</h3>
</div>
<div class="">
<div class="row">
<div class="col-6">
<label>Webinar Date
<input type="text" class="form-select WebinarDate" name="WebinarDate" value="<?= h($deets[16]) ?>">
</label>
</div>
<div class="col-6">
<label>Webinar Link
<input type="text" class="form-control" name="WebinarLink" value="<?= h($deets[15]) ?>">
</label>
<!--
<label>Webinar Info
<input type="text" class="form-control" name="WebinarInfo" placeholder="WebinarInfo" value="<?= h($deets[16]) ?>">
</label> -->
</div>

<div class="col-6">
	<label>Evaluations Link
	<input type="text" class="form-control" name="EvaluationsLink" value="<?= h($deets[58]) ?>">
	</label>
</div>
<div class="col-3">
		<label>Evals Sent?
		<?php if($deets[57] == 'Yes' || $deets[57] == 'on'): ?>
		<input type="checkbox" name="EvaluationsSent" id="EvaluationsSent" checked>
		<?php else: ?>
		<input type="checkbox" name="EvaluationsSent" id="EvaluationsSent">
	<?php endif ?>
	</label>
</div>


<div class="col-3">
		<label>Evals Returned?
		<?php if($deets[40] == 'Yes' || $deets[40] == 'on'): ?>
		<input type="checkbox" name="EvaluationsReturned" id="EvaluationsReturned" checked>
		<?php else: ?>
		<input type="checkbox" name="EvaluationsReturned" id="EvaluationsReturned">
	<?php endif ?>
	</label>
</div>
<div class="col-4">
	<label>Roster Returned?
		<?php if($deets[39] == 'Yes' || $deets[39] == 'on'): ?>
		<input type="checkbox" name="AttendanceReturned" id="AttendanceReturned" checked>
		<?php else: ?>
		<input type="checkbox" name="AttendanceReturned" id="AttendanceReturned">
	<?php endif ?>
	</label>
</div>

</div>
<label for="RequestNotes">Submitted Request Notes</label>
<textarea class="form-control summernote" name="RequestNotes" id="RequestNotes" rows="3"><?= h($deets[32]) ?></textarea>
<div class="alert alert-warning mt-1">
	<p>DO NOT update the above notes unless there's a good reason; 
	these are the notes that <a href="/lsapp/person.php?idir=<?= h($deets[3]) ?>"><?= h($deets[3]) ?></a>
	made when they submitted the request.</p>
	<p>If you need to add an admin note, go to the class page and use the notes form there.</p>

</div>
</div>


</form>




</div>
</div>
</div>





<?php else: ?>
<div class="col-md-6">

	<h2>Class Not Found</h2>
	<p>Must be playin' hooky ;)</p>
	<p>Email the Learning Support Admin Team <<a href="mailto:learning.centre.admin@gov.bc.ca">learning.centre.admin@gov.bc.ca</a>> with any questions or concerns.</p>
	<p><img src="img/TrollFace.jpg" width="300px"></p>
	
</div>	
<?php endif ?>

</div>
</div>


<?php require('templates/javascript.php') ?>

<script src="/lsapp/js/summernote-bs4.js"></script>

<script>
$('document').ready(function(){
	
var moment = rome.moment;
var whorag = rome(document.querySelector('.StartDate'), { time: false, dateValidator: function (d) {
	return moment(d).day() !== 6;
} });
var whorag = rome(document.querySelector('.EndDate'), { time: false, dateValidator: function (d) {
	return moment(d).day() !== 6;
} });
var whorag = rome(document.querySelector('.WebinarDate'), { time: false, dateValidator: function (d) {
	return moment(d).day() !== 6;
} });
var whorag = rome(document.querySelector('.ShipDate'), { time: false, dateValidator: function (d) {
	return moment(d).day() !== 6;
} });
var endtime = rome(document.querySelector('.endtime'), { 
			date: false,
			timeValidator: function (d) {
				var m = moment(d);
				var start = m.clone().hour(07).minute(59).second(59);
				var end = m.clone().hour(16).minute(30).second(1);
				return m.isAfter(start) && m.isBefore(end);
			}
	});
var starttime = rome(document.querySelector('.starttime'), { 
			date: false,
			timeValidator: function (d) {
				var m = moment(d);
				var start = m.clone().hour(07).minute(59).second(59);
				var end = m.clone().hour(16).minute(00).second(1);
				return m.isAfter(start) && m.isBefore(end);
			}
	});

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
	


$('#venuechoose').on('change',function(e){
	var vid = $(this).find(':selected').data('vid');
	var venueurl = 'venue.php?vid=' + vid + '&json=1';
	alert('Please ensure that you update the venue in ELM as well');
	$.ajax({
		dataType: "text",
		url: venueurl,
		success:function(data) {
			var v = data.replace(/["]+/g, '');
			var vinfo = v.split(',');
			$('#VenueID').val(vid);
			$('#VenueName').val(vinfo[1]);
			$('#VenueCity').val(vinfo[5]);
			$('#VenueAddress').val(vinfo[4]);
			$('#VenuePostalCode').val(vinfo[7]);
			$('#VenueContactName').val(vinfo[2]);
			$('#VenuePhone').val(vinfo[3]);
			$('#VenueEmail').val(vinfo[8]);
		}
	});
});
	

});

</script>


<?php endif ?>

<?php else: ?>
<?php include('layout/noaccess.php') ?>
<?php endif ?>

<?php require('templates/footer.php') ?>