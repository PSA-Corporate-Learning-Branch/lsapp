<?php 
require('inc/lsapp.php');
if(canAccess()):
$teamname = $_GET['teamname'] ?? '';
// 0-IDIR,1-Role,2-Name,3-Email,4-Status,5-Phone,6-Title,7-Super,8-Director,
// 9-Pronouns,10-Colors
$peeps = getPeopleAll();
$team = array();
foreach($peeps as $peep) {
	if($peep[1] == $teamname) {
		if($peep[4] == 'Active') {
			array_push($team,$peep);
		}
	} 
}

?>

<style>
.bar {
	border-top-right-radius: 10px; 
	border-bottom-right-radius: 10px;
	box-shadow: 0 0 2px #666;
	height: 10px;
	margin: 0;
	padding: 0;
}

</style>

<?php foreach($team as $peep): ?>

	<div class="p-2 mb-1 bg-light-subtle">
	
		<?php if($peep[8]): ?>
		<span class="badge badge-success">Director</span>
		<?php endif ?>
		<?php if(isAdmin()): ?>
		<div class="float-end"><a href="person-update.php?idir=<?= $peep[0] ?>" class="btn btn-light btn-sm">Edit</a></div>
		<?php endif ?>
		<div class="name"><strong><a href="/lsapp/person.php?idir=<?= $peep[0] ?>"><?= $peep[2] ?></a></strong>
		<?php if($peep[9] != 'Unspecified'): ?>
		(<span class="pronouns"><?= $peep[9] ?></span>)
		<?php endif ?>, 
		<span class="title"><?php if(isset($peep[6])) echo $peep[6] ?></span></div>
		
		
		<div class="email"><?= $peep[3] ?></div>
		<div class="phone"><?php if(isset($peep[5])) echo $peep[5] ?></div>
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
		<div class="mb-0 bg-light-subtle shadow-sm" style="border-radius: 10px; border: 1px solid #F1F1F1; margin: 0; padding: 0; position: relative; width: 100%;">
			<div style="border-right: 2px solid black; height: 100%; position:absolute; width: 50%  "></div>
			<div id="bluebar" class="bar" style="border-top-left-radius: 10px; background-color: DodgerBlue; width: <?= $colors[1] ?>%"></div>
			<div id="greenbar" class="bar" style="background-color: MediumSeaGreen; width: <?= $colors[2] ?>%"></div>
			<div id="yellowbar" class="bar" style="background-color: gold; width: <?= $colors[3] ?>%"></div>
			<div id="redbar" class="bar" style="border-bottom-left-radius: 10px; background-color: Crimson; width: <?= $colors[4] ?>%"></div>
		</div>
		<?php endif ?>
	</div>
<?php endforeach ?>


<?php else: ?>

<?php require('templates/noaccess.php'); ?>

<?php endif ?>
