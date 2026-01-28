<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path); 
require('eval-functions.php');

// Get our survey config
$config = getConfig();

// Get all the courses
$courses = getCourses();

// Build an array of surveys that are associated with courses
$config_courses = array();
foreach ($config as $c) {
    if (isset($c['courseId']) && !empty($c['courseId'])) {
        $config_courses[] = $c['courseId'];
    }
}

// Get the course info we want about our survey courses
$course_map = array();
foreach ($courses as $course) {
    if (in_array($course[0], $config_courses)) {
        $course_map[$course[0]] = ['name' => $course[2], 'code' => $course[4], 'status' => $course[1]];
    }
}

?>


<?php if(canACcess()): ?>

<?php getHeader() ?>
    <title>Surveys</title>
    <script src="/lsapp/js/list.min.js"></script>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">

    <div class="col-12">
        <h1 class="mb-5 text-center">Surveys</h1>
    </div>

</div> <!-- /row -->
<div class="row justify-content-md-center">
<div class="col-9">
<div class="container-lg p-lg-2 p-2 bg-secondary-subtle rounded">
    


<?php foreach($config as $survey): ?> 

    <div class="card m-2">
        <div class="card-body pb-2">
            
            <div class="container">
            <div class="row align-items-center">
                <div class="col-8">
                    <h5 class="card-title"><?= $survey['name'] ?? '' ?></h5>
                    <h6 class="card-subtitle text-body-secondary">
                        <?= !empty($survey['courseId']) ? $course_map[$survey['courseId']]['name'] . ' (' . $course_map[$survey['courseId']]['code'] . ')' : '' ?>
                    </h6>
                    <p class="card-text"><small class="text-body-secondary">Form ID: <?= $survey['formId'] ?></small></p>
                </div>
                <div class="col-2">
                    <div><small>Last Sync: <?= isset($survey['lastResponsesUpdated']) ? date('Y-m-d', $survey['lastResponsesUpdated']) : 'n/a'  ?></small></div>
                    <div><small>Recent Responses: #</small></div>
                    <div><small>Another thing: #</small></div>
                </div>
                <div class="col-2">
                    <div class="btn-group-vertical btn-group-sm float-end border" role="group" aria-label="Vertical button group">
                        <a href="./edit-survey.php?formId=<?= $survey['formId'] ?>" type="button" class="btn btn-light text-light-emphasis">Edit</a>
                        <a href="./evaluation-report.php?formId=<?= $survey['formId'] ?>" type="button" class="btn btn-outline-info text-light-emphasis">View Report</a>
                    </div>
                </div>
                <hr class="my-1">
                <div class="mt-1">
                    <span class="float-start mb-0 badge <?= $survey['status'] == 'active' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= ucfirst($survey['status']) ?></span>
                </div>
            </div> <!-- /row -->
            
            </div> <!-- /container --> 
            
        </div>
    </div>


<?php endforeach; ?>


</div> <!-- /container -->
</div> <!-- /col -->
</div> <!-- /row -->
</div> <!-- /container -->


<?php endif ?> <!-- //canACcess() -->

</body>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>