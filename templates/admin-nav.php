<?php 
$sections = array('Admin Home' => 'admin.php',
					'ELM Audit' => 'elm-audit.php',
					'ELM Sync' => 'elm-sync-upload.php');
					
$currentpage = $_SERVER['REQUEST_URI'];
$currentpage = explode('/lsapp/',$currentpage);
$noquery = explode('?',$currentpage[1]);
//echo $currentpage[1];
$active = '';
?>
<ul class="nav nav-tabs justify-content-center mb-3">
<?php foreach($sections as $page => $link): ?>
<?php 
if($noquery[0] === $link) { 
	$active = 'active';
} else {
	$active = '';
}
 ?>
<li class="nav-item">
	<a href="<?= $link ?>" class="nav-link <?= $active ?>">
		<?= $page ?>
	</a>
</li>
<?php endforeach ?>
</ul>