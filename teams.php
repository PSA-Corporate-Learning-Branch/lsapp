<?php require('inc/lsapp.php') ?>

<?php if(canAccess()): ?>
<?php
$teamname = $_GET['teamname'] ?? '';

$namemap = [
	['Governance','Governance, Planning, &amp; Evaluation'],
	['Employees','Corp. Learning - All Employees'],
	['Leaders','Corp. Learning - People Leaders'],
	['Operations','Operations &amp; Technology']
];
$pagetitle = '';
foreach($namemap as $m) {
	if($teamname == $m[0]) {
		$pagetitle = 'LC Team: ' . $m[1];
	}
}
// 0-IDIR,1-Role,2-Name,3-Email,4-Status,5-Phone,6-Title,7-Super,8-Director,
// 9-Pronouns,10-Colors
$peeps = getPeopleAll();
$dm = array();
$team = array();
foreach($peeps as $peep) {
	if($peep[0] == 'leanhill') {
		array_push($dm,$peep);
	}
	if($peep[1] == $teamname) {
		if($peep[4] == 'Active') {
			array_push($team,$peep);
		}
	} 
}

?>
<?php getHeader() ?>

<title><?= $pagetitle ?></title>

<?php getScripts() ?>
<style>
.bar {
	border-top-right-radius: 10px; 
	border-bottom-right-radius: 10px;
	box-shadow: 0 0 2px #666;
	height: 10px;
	margin: 0;
	padding: 0;
}
.active {
	background: #036;
	border-radius: 4px;
	color: #FFF;
}
.active:hover {
	color: #FFF;
}

</style>
<body>
<?php getNavigation() ?>

<div class="container-fluid" id="peoplelist">
<div class="row justify-content-md-center mb-3">
<div class="col-md-12">
	<h1 class="text-center">Learning Centre - Functional Teams</h1>
</div>
<div class="col-md-4">
	<div class="p-2 mb-1 bg-light-subtle text-center rounded-3">
		<span class="badge bg-primary text-white">Executive Director</span>
		<div class="name"><strong><a href="/lsapp/person.php?idir=<?= $dm[0][0] ?>"><?= $dm[0][2] ?></a></strong>, 
		<span class="title"><?php if(isset($dm[0][6])) echo $dm[0][6] ?></span></div>
		<!-- <div class="email"><?= $dm[0][3] ?></div>
		<div class="phone"><?php if(isset($dm[0][5])) echo $dm[0][5] ?></div> -->
		<?php
		$colors = explode('|',$dm[0][10]);
		$prevpercnt = 0;
		$cc = 0;
		// Cool Blue, Earth Green, Sunshine Yellow, Fiery Red
		foreach($colors as $percnt) {
			if($percnt > $prevpercnt) {
				$highest = $percnt;
				if($cc == 1) {
					$type = 'Cool Blue';
				} else if($cc == 2) {
					$type = 'Earth Green';
				} else if($cc == 3) {
					$type = 'Sunshine Yellow';
				} else if($cc == 4) {
					$type = 'Fiery Red';
				}
			}
			$prevpercnt = $percnt;
			$cc++;
		}
		if($colors[0] > 0):
		?>
		<a class="" href="#" data-toggle="modal" data-target="#tips">
		<div class="mb-0 bg-dark-subtle rounded-2" style="margin: 0; padding: 0; position: relative; width: 100%;">
			<div style="border-right: 2px solid black; height: 100%; position:absolute; width: 50%  "></div>
			<div id="bluebar" class="bar" style="border-top-left-radius: 10px; background-color: DodgerBlue; width: <?= $colors[1] ?>%"></div>
			<div id="greenbar" class="bar" style="background-color: MediumSeaGreen; width: <?= $colors[2] ?>%"></div>
			<div id="yellowbar" class="bar" style="background-color: gold; width: <?= $colors[3] ?>%"></div>
			<div id="redbar" class="bar" style="border-bottom-left-radius: 10px; background-color: Crimson; width: <?= $colors[4] ?>%"></div>
		</div>
		</a>
		<?php endif ?>
	</div>
</div>
</div>
<div class="row justify-content-md-center mb-3">
<div class="col-md-3">
<nav class="nav justify-content-end">
<?php foreach($namemap as $m): ?>
<?php
$active = '';
if($m[0] == $teamname) $active = 'active'; 
?>
<a class="nav-link <?= $active ?>" href="?teamname=<?= $m[0] ?>"><?= $m[1] ?></a>
<?php endforeach ?>
</nav>
</div>
<div class="col-md-4">

<?php if($teamname): ?>

<?php foreach($team as $peep): ?>

	<div class="p-2 mb-1 bg-light-subtle rounded-3">
	
		<?php if($peep[8]): ?>
		<span class="badge bg-primary text-white">Director</span>
		<?php endif ?>
		<?php if(isAdmin()): ?>
		<!-- <div class="float-end"><a href="person-update.php?idir=<?= $peep[0] ?>" class="btn btn-light btn-sm">Edit</a></div> -->
		<?php endif ?>
		<div class="name"><strong><a href="/lsapp/person.php?idir=<?= $peep[0] ?>"><?= $peep[2] ?></a></strong>
		<?php if($peep[9] != 'Unspecified'): ?>
		(<span class="pronouns"><?= $peep[9] ?></span>)<?php endif ?>
		<div class="title"><?php if(isset($peep[6])) echo $peep[6] ?></div>
		</div>
		
		<!-- <div class="contact">
			<span class="badge badge-light bg-light-subtle email">
				<a style="color: #000" href="mailto:<?= $peep[3] ?>"><?= $peep[3] ?></a>
			</span>
			<span class="badge badge-light bg-light-subtle phone"><?php if(isset($peep[5])) echo $peep[5] ?></span>
		</div> -->
		<?php
		$colors = explode('|',$peep[10]);
		$prevpercnt = 0;
		$cc = 0;
		// Cool Blue, Earth Green, Sunshine Yellow, Fiery Red
		foreach($colors as $percnt) {
			if($percnt > $prevpercnt) {
				$highest = $percnt;
				if($cc == 1) {
					$type = 'Cool Blue';
				} else if($cc == 2) {
					$type = 'Earth Green';
				} else if($cc == 3) {
					$type = 'Sunshine Yellow';
				} else if($cc == 4) {
					$type = 'Fiery Red';
				}
			}
			$prevpercnt = $percnt;
			$cc++;
		}
		if($colors[0] > 0):
		?>
		<a class="" href="#" data-toggle="modal" data-target="#tips">
		<div class="mb-0 bg-dark-subtle rounded-2" style="margin: 0; padding: 0; position: relative; width: 100%;">
			<div style="border-right: 2px solid black; height: 100%; position:absolute; width: 50%  "></div>
			<div id="bluebar" class="bar" style="border-top-left-radius: 10px; background-color: DodgerBlue; width: <?= $colors[1] ?>%"></div>
			<div id="greenbar" class="bar" style="background-color: MediumSeaGreen; width: <?= $colors[2] ?>%"></div>
			<div id="yellowbar" class="bar" style="background-color: gold; width: <?= $colors[3] ?>%"></div>
			<div id="redbar" class="bar" style="border-bottom-left-radius: 10px; background-color: Crimson; width: <?= $colors[4] ?>%"></div>
		</div>
		</a>
		<?php endif ?>
	</div>
<?php endforeach ?>

</div>
</div>
</div>

<?php else: ?>

	<p>Please choose a team from the menu on the left.</p>

<?php endif ?>


<?php $tips = getTips(); ?>

<div class="modal fade" id="tips" tabindex="-1" aria-labelledby="tipsLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="exampleModalLabel">Tips for communicating in color</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
<span aria-hidden="true">&times;</span>
</button>
</div>
<div class="modal-body text-left">
	<dl>
<?php foreach($tips as $t): ?>
	<?php 
	$bgcolor = '';
	$txtcolor = '#FFF';
	if($t[1] == 'Fiery Red') {
		$bgcolor = 'Crimson';
	} else if($t[1] == 'Sunshine Yellow') { 
		$bgcolor = 'Gold';
		$txtcolor = '#111';
	} else if($t[1] == 'Earth Green') { 
		$bgcolor = 'MediumSeaGreen';
	} else if($t[1] == 'Cool Blue') { 
		$bgcolor = 'DodgerBlue';
	}
	?>
	<dt class="p-3 rounded-3" style="background-color: <?= $bgcolor ?>; color: <?= $txtcolor ?>">
		<?= $t[1] ?>
	</dt>
	<dd class="p-3">
		<?= $t[2] ?>
	</dd>
<?php endforeach ?>
</dl>
</div>
</div>
</div>
</div>
</div>


<?php else: ?>

<?php require('templates/noaccess.php'); ?>

<?php endif ?>


<?php require('templates/javascript.php') ?>


<?php include('templates/footer.php') ?>