<?php require('inc/lsapp.php') ?>
<?php getHeader() ?>
<title>Platforms</title>
<?php getScripts() ?>
<?php getNavigation() ?>

<?php if(canAccess()): ?>

<div class="container">
<div class="row justify-content-md-center mb-3">

<div class="col-md-8">
<div class="btn-group float-end">
<?php if(isAdmin()): ?>
<a href="platform-create.php" class="btn btn-primary">New Platform</a>
<?php endif ?>
</div>
<h1>Learning Platforms</h1>
<p>Browse the various learning platforms available for BC Public Service courses and training.</p>

<div id="platformlist">

<input class="search form-control mb-3" placeholder="Search platforms...">

<?php 
$jsonContent = file_get_contents('data/platforms.json');
$platforms = json_decode($jsonContent, true);

if ($platforms === null) {
    echo '<div class="alert alert-danger" role="alert">Failed to load platform data. Please try again later.</div>';
} else {
?>

<ul class="list-group list">
<?php foreach ($platforms as $platform): ?>
    <li class="list-group-item">
        <h5 class="mb-1 name">
            <a href="/lsapp/platform.php?id=<?= urlencode($platform['id']) ?>">
                <?= htmlspecialchars($platform['name']) ?>
            </a>
        </h5>
        <p class="mb-0 text-muted description"><?= htmlspecialchars($platform['description']) ?></p>
    </li>
<?php endforeach ?>
</ul>

<?php } ?>

</div>
</div>
</div>
</div>

<?php else: ?>

<?php require('templates/noaccess.php') ?>

<?php endif ?>

<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){

    $('.search').focus();
    
    var options = {
        valueNames: [ 'name', 'description' ]
    };
    var platforms = new List('platformlist', options);

});
</script>
<?php include('templates/footer.php') ?>