<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');




?>

<style>
    .form-section {
        background-color: var(--bs-light-bg-subtle);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    .form-section-title {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
</style>

<?php if(canACcess()): ?>

<?php getHeader() ?>
<title>Edit</title>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container mb-5">
<div class="row justify-content-md-center">
<div class="col-md-10">

<h1 class="mb-4">Edit {{ Survey Name }}</h1>

<!-- <form> -->

<div class="form-section">
    <div class="form-section-title">Survey Information</div>

    <label for="" class="form-label">Form Id</label>
    <input type="" name="" id="" class="form-control">

    <!-- Should this field be limited to supers/admins? -->
    <label for="" class="form-label">Form Secret</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Assigned Course</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Survey Name</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Survey Description</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Status</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Published Version</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Version Id</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Last Sync</label>
    <input type="" name="" id="" class="form-control">

    <label for="" class="form-label">Questions</label>
    <input type="" name="" id="" class="form-control">

</div>

<!-- </form> -->

</div> <!-- /col -->
</div> <!-- /row --> 
</div> <!-- /container -->

<?php endif; ?> <!-- /canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>