<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>People</title>
<?php getScripts() ?>
<?php getNavigation() ?>
<?php $teams = getTeams(); ?>
<?php if(canAccess()): ?>
<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-12">

<?php if(isAdmin()): ?>
<button type="button" class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#addnewperson">
  Add New Person
</button>
<?php endif ?>
<h1>People</h1>
<div id="userlist">
<p>If they're not on this list, they can't access anything. <a href="teams-all.php">Team view</a>.</p>
<input class="search form-control  mb-3" placeholder="search">
<div class="table-responsive">
<table class="table table-sm table-striped table-hover">
<thead>
<tr>
	<?php if(isAdmin()): ?><th></th><?php endif ?>
	<th>Name</th>
	<th>Title</th>
	<th>Email</th>
	<th>Phone</th>
	<!-- <th>Status</th> -->
	<th>IDIR</th>
	<th>Team</th>
</tr>
</thead>
<tbody class="list">
<?php $peeps = getPeopleAll() ?>
<?php foreach($peeps as $peep): ?>
<?php if($peep[4] == 'Active'): ?>
	<tr>
		<?php if(isAdmin()): ?>
		<td><a href="person-update.php?idir=<?= $peep[0] ?>" class="btn btn-secondary btn-sm">Edit</a></td>
		<?php endif ?>
		<td class="name"><a href="/lsapp/person.php?idir=<?= $peep[0] ?>"><?= $peep[2] ?></a></td>
		<td class="title"><?php if(isset($peep[6])) echo $peep[6] ?></td>
		<td class="email"><?= $peep[3] ?></td>
		<td class="phone"><?php if(isset($peep[5])) echo $peep[5] ?></td>
		<!--<td class="status">
		<?php if($peep[4] == 'Active'): ?>
		<span class="badge bg-primary text-white"><?= $peep[4] ?></span>
		<?php else: ?>
		<span class="badge bg-light-subtle "><?= $peep[4] ?></span>
		<?php endif ?>
		</td>-->
		<td class="idir"><span class="badge text-light-emphasis bg-light-subtle" id="<?= $peep[0] ?>"><?= $peep[0] ?></span></td>
		<?php if(array_key_exists($peep[1], $teams)): ?>
			<td class="role"><?= $teams[$peep[1]]['name'] ?></td>
		<?php endif; ?>
	</tr>
<?php endif ?>
<?php endforeach ?>
</tbody>
</table>
</div>
</div>
</div>

<?php if(isAdmin()): ?>
<div class="modal fade" id="addnewperson" tabindex="-1" role="dialog" aria-labelledby="addnewperson" aria-hidden="true">
	<div class="modal-dialog" role="document">
	<div class="modal-content">
	<div class="modal-header">
	<h5 class="modal-title" id="exampleModalLabel">Add New Person</h5>
	<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
	<!--<span aria-hidden="true">&times;</span>-->
	</button>
	</div>
	<div class="modal-body">

	  
		<form method="post"	class="newuser col" action="people-controller.php">
			<input type="hidden" name="action" value="add">
			<select name="role" id="role" class="form-select mb-2" autocomplete="off" required>
				<option selected value="" disabled>Choose a Team</option>
				<?php foreach($teams as $teamId => $team): ?>
					<option value=<?= $teamId ?>><?= $team["name"] ?></option>
				<?php endforeach ?>
			</select>
			<input type="text" name="idir" id="idir" class="form-control  mb-2" placeholder="IDIR" required>
			<input type="text" name="name" id="name" class="form-control  mb-2" placeholder="Name" required>
			<input type="text" name="title" id="title" class="form-control  mb-2" placeholder="Title" required>
			<input type="text" name="phone" id="phone" class="form-control  mb-2" placeholder="Phone" required>
			<input type="text" name="email" id="email" class="form-control  mb-2" placeholder="full.name@gov.bc.ca" required>
			<?php if(isSuper()): ?>
			<input type="hidden" name="Super" id="Super" class="form-control  mb-2" placeholder="Super user? 0 for no, 1 for yes" required>
			<input type="hidden" name="Manager" id="Manager" class="form-control  mb-2" placeholder="Manager? 0 for no, 1 for yes" required>
			<?php endif ?>
			<input type="text" name="Pronouns" id="Pronouns" class="form-control  mb-2" placeholder="Personal pronouns e.g. She/Hers/They">
			<input type="submit" class="btn btn-primary" value="Add Person">

		</form>
	  
	  
      </div>

	</div>
	</div>
</div>

<?php endif ?>

</div>
</div>


<?php else: ?>

<?php require('templates/noaccess.php'); ?>

<?php endif ?>


<?php require('templates/javascript.php') ?>
<script>
$(document).ready(function(){

	$('.search').focus();
	
	var peopleoptions = {
		valueNames: [ 'idir','name','role' ]
	};
	var peeps = new List('userlist', peopleoptions);

});

// IDIR validation
function checkIdir(value) {
	const userIdir = document.getElementById(value);
	return userIdir;
}
const idirInput = document.getElementById('idir');
idirInput.addEventListener('input', () => {
	if (checkIdir(idirInput.value)) {
		idirInput.setCustomValidity('IDIR already exists.');
	} else {
		idirInput.setCustomValidity('');
	}
})

</script>

<?php include('templates/footer.php') ?>