<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');

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

// If there is an LSApp course associated, get its details
if (!empty($survey_config) && !empty($survey_config['courseId'])) {
    $course = getCourseDeets($survey_config['courseId']);
}

?>



<?php if(canACcess()): ?>

<?php getHeader() ?>

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

<title>Edit</title>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container mb-5">
    <div class="row justify-content-md-center">
        <div class="col-md-10">
            <h1 class="mb-4">Edit <?= $survey_config['name'] ?? 'Survey' ?></h1>
        </div> <!-- /col -->
    </div> <!-- /row -->

    <div class="row justify-content-md-center">
        <div class="col-md-10">
            <form method="post" action="survey-update-process.php">
            <!-- sync button -->
            <div class="d-flex align-items-center">
                <?php if (isAdmin()): ?>
                    <!-- form must be active and have a secret set -->
                    <?php if ($survey_config['status'] == 'active' && !empty($survey_config['formSecret'])): ?>
                        <a role="button" class="me-auto btn btn-primary m-2" href="./evaluation-sync.php?formId=<?= $form_id ?>">Sync Form</a>
                    <?php else: ?>
                        <a role="button" class="float-start btn btn-primary m-2 disabled" href="#" aria-disabled="true">Sync Form</a>
                        <a href="#" class="link-secondary" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="<small>Form must be Active with a valid Form Secret to sync</small>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                        </svg></a>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="ms-auto">
                    <a role="button" class="btn btn-secondary m-1" href="./index.php">Back</a>
                    <button type="submit" class="btn btn-success m-1 ms-0">Save</button>
                </div>
            </div>
        </div> <!-- /col -->
    </div> <!-- /row -->

    <div class="row justify-content-md-center">
        <div class="col-md-10">

        <!-- Alerts -->
            <div class="container-lg">

                <?php $flash_alerts = AlertManager::getAlertsAll(); ?>
                <?php if (count($flash_alerts) > 0): ?>
                    <?php foreach ($flash_alerts as $falert): ?>
                        <div class="alert alert-<?= $falert['type'] ?>" role="alert">
                        <?= $falert['message'] ?><br>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (count($alerts) > 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <?php foreach($alerts as $alert): ?>
                            <?= $alert ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="form-section">
                <div class="form-section-title">Survey Information</div>

                <label for="FormId" class="form-label">Form Id</label>
                <input type="text" id="FormId" class="form-control" value="<?= $survey_config['formId'] ?? '' ?>" disabled>
                <input type="hidden" name="FormId" id="FormIdValue" value="<?= $survey_config['formId'] ?? '' ?>">

                <!-- Only admins can update api secret -->
                <?php if (isAdmin()): ?>
                    <label for="FormSecret" class="form-label">Update Form Secret</label>
                    <input type="password" name="FormSecret" id="FormSecret" class="form-control">
                    <?php if (isset($survey_config['formSecret'])): ?>
                        <div class="alert alert-info mt-2" role="alert">
                            Form Secret has been set. Please provide a new secret if you wish to update.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <label for="" class="form-label">Assigned Course</label>
                <input type="" name="" id="" class="form-control" value="<?= $course[2] ?? 'None' ?>" disabled>
                <!-- Provide additional info if we have a matching CHEFS Form ID associated with the course -->
                <?php if (!empty($course[46]) && $form_id == $course[46]): ?>
                    <div class="alert alert-info mt-2" role="alert">
                        This course is connected by the CHEFS Form ID field on the <a href="../course.php?courseid=<?= $course[0] ?>">course page</a>.
                    </div>
                <?php endif; ?>

                <label for="SurveyName" class="form-label">Survey Name</label>
                <input type="text" id="SurveyName" class="form-control" value="<?= $survey_config['name'] ?? '' ?>" disabled>

                <label for="SurveyDescription" class="form-label">Survey Description</label>
                <input type="text" id="SurveyDescription" class="form-control" value="<?= $survey_config['description'] ?? '' ?>" disabled>
                
                <?php if (isAdmin()): ?>
                    <label for="Status" class="form-label">Status</label>
                    <select name="Status" id="Status" class="form-select">
                        <?php $statuses = ['pending', 'active', 'inactive']; ?>
                        <?php $config_status = $survey_config['status'] ?? ''; ?>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>" <?= $config_status == $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <label for="Status" class="form-label">Status</label>
                    <input type="text" name="status" id="status" class="form-control" value="<?= ucfirst($survey_config['status']) ?? '' ?>" disabled>
                <?php endif;?>

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

                <span>Questions</span>
                    
                <?php $questions = $survey_config['questions'] ?? ''; ?>
                <?php if (!empty($questions)): ?>
                    <?php
                        // remove course and class code questions
                        $questions_filtered = array_filter($questions, function($q) {
                            return $q !== 'courseCode' && $q !== 'classCode';
                        }, ARRAY_FILTER_USE_KEY);
                    ?>

                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Question</th>
                                <th>Options</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($questions_filtered as $question): ?>
                                <?php 
                                $values = '';
                                if (isset($question['values']) && is_array($question['values'])) {
                                    $values .= '<ul>';
                                    foreach($question['values'] as $option) {
                                        $values .= '<li>' . $option . '</li>';
                                    }
                                    $values .= '</ul>';
                                }
                                ?>
                                <tr>
                                    <td><?= $question['inputType'] ?></td>
                                    <td><?= $question['label'] ?></td>
                                    <td><?= $values ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info mt-2" role="alert">
                        Questions will be populated once the survey has been synchronized with CHEFS.<br>
                        <?php if (isAdmin()): ?>
                        <em>Admins</em>: A survey needs to have a <strong>Form Secret</strong> and be <strong>Active</strong> to be included in the sync. 
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

    <div class="d-flex justify-content-center">
        <button type="submit" class="btn btn-success btn-lg">Save Changes</button>
    </div>

</form>

</div> <!-- /col -->
</div> <!-- /row --> 
</div> <!-- /container -->

<?php require('../templates/javascript.php') ?>

<script>

    // tooltip functionality
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

</script>

<?php endif; ?> <!-- /canACcess() -->

</body>

<?php require('../templates/footer.php') ?>