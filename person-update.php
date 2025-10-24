<?php 
require('inc/lsapp.php');
$currentuser = LOGGED_IN_IDIR;

if($_POST):

	$fromform = $_POST;
	// PLEASE NOTE: if you update the IDIR, you orhpan any records in classes.csv
	// or courses.csv (or any other data files) that reference the old IDIR.
	// We will write a conversion script at some point, right?
	//
	// If we want to update the IDIR itself we have to do the follow dance
	// where we look at the "old idir" and use it to match against
	// the existing record. "old" here could be "existing"
	$oldidir = $fromform['OldIDIR'];
	// Start making a value that we're going to update the person
	// with regardless that uses the old/existing IDIR.
	$idir = $oldidir;

	if (isset($fromform['NewIDIR'])) {
		$newidir = $fromform['NewIDIR'];

		// if the existing IDIR doesn't match the new IDIR update 
		// value that gets populated into the person being written 
		if ($idir !== $newidir) {
			$idir = $newidir;
		}
	}
	
	if(isAdmin() || $currentuser == $idir) { // note that this means that people can't update their own IDIRs

		$f = fopen('data/people.csv','r');
		$temp_table = fopen('data/people-temp.csv','w');
		// pop the headers off the source file and start the new file with those headers
		$headers = fgetcsv($f);
		fputcsv($temp_table,$headers);
		
		$pronouns = $fromform['Pronouns'] ? $fromform['Pronouns'] : 'Unspecified';
		
		// IDIR,Team,Name,Email,Status,Phone,Title,Super,Director,Pronouns,Colors,iStore,kepler
		$person = Array($idir,
					h($fromform['Team']),
					h($fromform['Name']),
					h($fromform['Email']),
					h($fromform['Status']),
					h($fromform['Phone']),
					h($fromform['Title']),
					h($fromform['Super']),
					h($fromform['Manager']),
					$pronouns,
					h($fromform['Colors']),
					$fromform['iStore'] ?? 0,
					$fromform['Kepler'] ?? 0
			);
		
		while (($data = fgetcsv($f)) !== FALSE){
			// oldidir here should always be the old or existing one
			// even if it immediately gets overwritten with a new value.
			if($data[0] == $oldidir) {
				fputcsv($temp_table,$person);
			} else {
				fputcsv($temp_table,$data);
			}
		}
		
		fclose($f);
		fclose($temp_table);

		rename('data/people-temp.csv','data/people.csv');

		header('Location: person.php?idir=' . $idir);
	} else {
		echo '<p>Sorry, you cannot do that.</p>';
	}
	
else: ?>



<?php $idir = $_GET['idir'] ?>
<?php $p = getPerson($idir) ?>

<?php getHeader() ?>

<title>Edit <?= $p[2] ?></title>

<?php getScripts() ?>
<?php getNavigation() ?>

<?php $teams = getTeams(); ?>

<div class="container mb-3">
<div class="row justify-content-md-center mb-3">
<div class="col-md-6 mb-3">
<div class="float-right">
<form method="post" action="person-delete.php">
	<input type="hidden" name="idir" value="<?= $person[0] ?>">
	<div class="btn-group">
	<input type="submit" value="Delete" class="btn btn-sm btn-danger del">
	</div>
	</form>
</div>
<h1>Edit <?= $p[2] ?></h1>


<form method="post" action="person-update.php" class="mb-3 pb-3" id="PersonUpdate">

<!-- IDIR,Team,Name,Email,Status,Phone,Title -->
<div class="form-group">
	<label for="IDIR">IDIR: </label>
	<input type="text" name="NewIDIR" id="NewIDIR" class="form-control" value="<?= $p[0] ?>">
	<input type="hidden" name="OldIDIR" id="OldIDIR" value="<?= $p[0] ?>">
</div>
<div class="form-group">
	<label for="Team">Team: </label>
	<select name="Team" id="Team" class="form-select">
		<?php foreach($teams as $teamId => $team): ?>
			<?php if($teamId == $p[1]): ?>
				<option selected value=<?= $teamId ?>><?= $team["name"] ?></option>
			<?php else: ?>
				<option value=<?= $teamId ?>><?= $team["name"] ?></option>
			<?php endif ?>
		<?php endforeach ?>
	</select>
	
</div>
<div class="form-group">
	<label for="Name">Name: </label>
	<input type="text" name="Name" id="Name" class="form-control" value="<?= $p[2] ?>">
</div>

<div class="form-group">
	<label for="Email">Email: </label>
	<input type="text" name="Email" id="Email" class="form-control" value="<?= $p[3] ?>">
</div>
<div class="form-group">
	<label for="Status">Status: </label>
	<select name="Status" id="Status" class="form-select">
	<?php $stats = array('Active','Inactive') ?>
	<?php foreach($stats as $stat): ?>
	<?php if($stat == $p[4]): ?>
	<option selected><?= $stat ?></option>
	<?php else: ?>
	<option><?= $stat ?></option>
	<?php endif ?>
	<?php endforeach ?>
	</select>
</div>
<div class="form-group">
	<label for="Phone">Phone: </label>
	<input type="text" name="Phone" id="Phone" class="form-control" value="<?php if(isset($p[5])) echo $p[5] ?>">
</div>
<div class="form-group">
	<label for="Title">Title: </label>
	<input type="text" name="Title" id="Title" class="form-control" value="<?php if(isset($p[6])) echo $p[6] ?>">
</div>
<?php if(isSuper()): ?>
<div class="form-group">
	<label for="Super">Super User: </label>
	<input type="text" name="Super" id="Super" class="form-control" value="<?php if(isset($p[7])) echo $p[7] ?>">
</div>
<div class="form-group">
	<label for="Manager">Leadership Role:</label><span class="text-body-secondary"> (leave blank if not applicable)</span>
	<input type="text" name="Manager" id="Manager" class="form-control" value="<?php if(isset($p[8])) echo $p[8] ?>" placeholder="Eg. Director, Manager">
</div>
<?php endif ?>

<div class="form-group">
	<label for="Pronouns">Pronouns: </label>
	<input type="text" name="Pronouns" id="Pronouns" class="form-control" value="<?php if(isset($p[9])) echo $p[9] ?>">
</div>
<div class="form-group">
	<label for="Colors">Colors: </label>
	<input type="text" name="Colors" id="Colors" class="form-control" value="<?php if(isset($p[10])) echo $p[10] ?>">
</div>
<div class="form-group mb-3">
	<input type="checkbox" name="iStore" id="iStore" class="form-check-input" value="1" <?= $p[11] == 1 ? 'checked' : '' ?>>
	<label for="iStore">iStore Designee: </label>
</div>
<div class="form-group">
	<input type="checkbox" name="Kepler" id="Kepler" class="form-check-input" value="1" <?= $p[12] == 1 ? 'checked' : '' ?>>
	<label for="Kepler" class="form-check-label">Kepler Access: </label>
</div>
<button type="submit" class="btn btn-block btn-primary my-3">Save Person</button>

</form>
	
</div>
</div>


</div>

<?php require('templates/javascript.php') ?>
<?php require('templates/footer.php') ?>

<?php endif ?>

