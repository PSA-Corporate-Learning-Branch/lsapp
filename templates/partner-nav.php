<?php 

$sections = array('Partner Admin Dashboard' => '/lsapp/partners/dashboard.php',
					'Partner List' => '/lsapp/partners/',
					'Add New Partner' => '/lsapp/partners/form.php',
					'Development Partners' => '/lsapp/partners-development/');
					
$currentpage = $_SERVER['REQUEST_URI'];

$active = '';
?>
<ul class="nav nav-tabs justify-content-center mb-3">
<?php foreach($sections as $page => $link): ?>
<?php 
if($currentpage === $link) { 
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
