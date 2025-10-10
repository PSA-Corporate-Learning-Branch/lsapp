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

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">

    <div class="col-12">
        <h1 class="mb-5 text-center">Survey Dashboard</h1>
    </div>


    



</div> <!-- /row -->
</div> <!-- /container -->


<?php endif ?> <!-- //canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>