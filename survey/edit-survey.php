<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');

// Include encryption helper for decrypting API passwords
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

$alerts = array();

// get form id
$form_id = '';
if (isset($_GET['formId'])) {
    $form_id = $_GET['formId'];
}

// get config
$config = getConfig();
$survey_config = '';


// If no form id provided
if (empty($form_id)) {
    array_push($alerts, 'No form ID provided.');
} 
else {
    foreach ($config as $survey) {
        if ($survey['formId'] == $form_id) {
            $survey_config = $survey;
        }
    }
    // if form id not in config
    if (empty($survey_config)) {
        array_push($alerts, 'Survey not found.');
    }
}


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

<!-- Alerts -->
<?php if (count($alerts) > 0): ?>
    <div class="alert alert-warning" role="alert">
        <?php foreach($alerts as $alert): ?>
            <p><?= $alert ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<!-- <form> -->

<div class="form-section">
    <div class="form-section-title">Survey Information</div>

    <label for="FormId" class="form-label">Form Id</label>
    <input type="text" name="FormId" id="FormId" class="form-control" value="<?= $survey_config['formId'] ?? '' ?>">

    <!-- Should this field be limited to supers/admins? -->
    <label for="FormSecret" class="form-label">Form Secret</label>
    <input type="password" name="FormSecret" id="FormSecret" class="form-control" value="<?= $survey_config['formSecret'] ?? '' ?>">

    <label for="" class="form-label">Assigned Course</label>
    <input type="" name="" id="" class="form-control">

    <label for="SurveyName" class="form-label">Survey Name</label>
    <input type="text" name="SurveyName" id="SurveyName" class="form-control" value="<?= $survey_config['name'] ?? '' ?>" disabled>

    <label for="SurveyDescription" class="form-label">Survey Description</label>
    <input type="text" name="SurveyDescription" id="SurveyDescription" class="form-control" value="<?= $survey_config['description'] ?? '' ?>" disabled>

    <label for="Status" class="form-label">Status</label>
    <select name="Status" id="Status" class="form-select">
        <?php $statuses = ['pending', 'active', 'inactive']; ?>
        <?php $config_status = $survey_config['status'] ?? ''; ?>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= $status ?>" <?= $config_status == $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="PublishedVersion" class="form-label">Published Version</label>
    <input type="number" name="PublishedVersion" id="PublishedVersion" class="form-control" value="<?= $survey_config['publishedVersion'] ?? '' ?>" disabled>

    <label for="PublishedVersionId" class="form-label">Version Id</label>
    <input type="text" name="PublishedVersionId" id="PublishedVersionId" class="form-control" value="<?= $survey_config['publishedVersionId'] ?? '' ?>" disabled>
    

    <?php 
        if (isset($survey_config['lastResponsesUpdated'])) {
            $last_sync_formatted = date('Y-m-d H:i', $survey_config['lastResponsesUpdated']);
        }
    ?>
    <label for="LastSync" class="form-label">Last Sync</label>
    <input type="text" name="LastSync" id="LastSync" class="form-control" value="<?= $last_sync_formatted ?? 'Never' ?>" disabled>

    <p>Questions</p>
        
            <?php 
                $questions = $survey_config['questions'] ?? ''; 
                //print_r($questions); 
            ?>
    <table class="table table-hover">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Question</th>
                    <th>Response Options</th>
                </tr>
            </thead>
            <tbody>

            </tbody>
    </table>

</div>

<!-- </form> -->

</div> <!-- /col -->
</div> <!-- /row --> 
</div> <!-- /container -->

<?php endif; ?> <!-- /canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>