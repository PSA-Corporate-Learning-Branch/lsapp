<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 



?>


<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Survey Dashboard</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>










<?php endif ?> <!-- //canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>