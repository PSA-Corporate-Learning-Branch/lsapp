<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('functions.php');

$data_path = '../data/surveys/';
$logs_file = $data_path . 'logs.txt';

$sync_logs = file_exists($logs_file) ? file($logs_file, FILE_IGNORE_NEW_LINES) : [];
$sync_array = [];
foreach ($sync_logs as $log) {
    $breakpoint = strpos($log, ' - ');
    $timestamp = substr($log, 0, $breakpoint);
    $entry = substr($log, $breakpoint);
    $sync_array[] = [$timestamp, $entry];
}
// reverse sort by timestamp
usort($sync_array, function ($a, $b) {
    return $b[0] <=> $a[0];
})

?>


<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Survey Sync</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">
    <div class="col-12">
        <h1 class="mb-5 text-center">Survey Sync</h1>
    </div>
</div> <!-- /row -->

<div class="container-lg">

    <?php $flash_alerts = AlertManager::getAlertsAll(); ?>
    <?php if (count($flash_alerts) > 0): ?>
        <?php foreach ($flash_alerts as $falert): ?>
            <div class="alert alert-<?= $falert['type'] ?>" role="alert">
                <?= $falert['message'] ?><br>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<div class="container-lg justify-content-between bg-light-subtle rounded-top border-secondary-subtle border-start border-top border-end">
    <div class="row">
        <div class="d-flex p-2">
            <div class="ms-3">
                <h2>Recent Sync Logs</h2>
            </div>   
            <div class="ms-auto">
                <a class="btn btn-primary" href="./evaluation-sync.php" role="button">Run Sync</a>
            </div>
        </div>
    </div> <!-- /row -->
</div>

<div class="container-lg p-3 border border-secondary-subtle bg-secondary-subtle rounded-bottom">
    <div class="row">

        <div class="col">
    
            <div class="text-bg-light rounded border border-dark-subtle m-1 p-3">
                <?php foreach($sync_array as $log): ?>
                    <span class="font-monospace"><?= $log[0] . $log[1] ?></span><br>
                <?php endforeach; ?>
            </div>
        
        </div>

    </div>

</div> <!-- /container -->


</div> <!-- /container -->


<?php endif ?> <!-- //canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>
