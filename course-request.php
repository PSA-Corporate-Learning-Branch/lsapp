<?php 
require('inc/lsapp.php');
opcache_reset();

if(!canAccess()) {
    header('Location: /lsapp/');
    exit;
}

$user = LOGGED_IN_IDIR;

// Load partners and platforms from JSON files
$partnersJson = file_get_contents('data/partners.json');
$partners = json_decode($partnersJson, true);

$platformsJson = file_get_contents('data/platforms.json');
$platforms = json_decode($platformsJson, true);

// Get taxonomy options
$topics = getAllTopics();
$audience = getAllAudiences();
$deliverymethods = getDeliveryMethods();
$levels = getLevels();
$reportinglist = getReportingList();

?>
<?php getHeader() ?>

<title>Course Request</title>
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
.info-modal {
    font-size: 0.875rem;
}
</style>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container mb-5">
<div class="row justify-content-md-center">
<div class="col-md-10">


<h1 class="mb-4">Request a Course</h1>

<form method="post" action="course-create.php" class="mb-3" id="serviceRequestForm">
    
    <!-- Hidden fields -->
    <input type="hidden" name="Requested" value="<?= date('Y-m-d') ?>">
    <input type="hidden" name="RequestedBy" value="<?= sanitize($user) ?>">
    <input type="hidden" name="HUBInclude" value="No">
    <input type="hidden" name="Status" value="Requested">
    
    <!-- Basic Information Section -->
    <div class="form-section">
        <div class="form-section-title">Basic Information</div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="LearningHubPartner" class="form-label">
                    Learning Hub Partner
                </label>
                <select name="LearningHubPartner" id="LearningHubPartner" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($partners as $partner): ?>
                        <option value="<?= sanitize($partner['id']) ?>"><?= sanitize($partner['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="Platform" class="form-label">
                    Registration Platform
                    <i class="bi bi-question-circle text-muted" 
                       data-bs-toggle="tooltip" 
                       data-bs-placement="top" 
                       title="Set this to 'PSA Learning System' unless learners actually register through another platform."></i>
                </label>
                <select name="Platform" id="Platform" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($platforms as $platform): ?>
                        <option value="<?= sanitize($platform['name']) ?>"><?= sanitize($platform['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="Method" class="form-label">Delivery Method</label>
                <select name="Method" id="Method" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <option value="eLearning">eLearning</option>
                    <option value="Webinar">Webinar</option>
                    <option value="Curated Pathway">Curated Pathway</option>
                    <option value="Blended">Blended</option>
                    <option value="Classroom">Classroom</option>
                </select>
            </div>
        </div>
        
        <div id="notelm" class="d-none">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="RegistrationLink" class="form-label">Registration Link</label>
                    <small class="d-block text-muted">If not in Learning System, where to register?</small>
                    <input type="url" name="RegistrationLink" id="RegistrationLink" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="HubExpirationDate" class="form-label">Expiration Date</label>
                    <small class="d-block text-muted">Date to remove from search results</small>
                    <input type="date" name="HubExpirationDate" id="HubExpirationDate" class="form-control">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Course Details Section -->
    <div class="form-section">
        <div class="form-section-title">Course Details</div>
        
        <div class="mb-3">
            <label for="CourseName" class="form-label">Course Name (Long)</label>
            <small class="d-block text-muted">Max 200 characters - Full/Complete title of the course</small>
            <input type="text" name="CourseName" id="CourseName" class="form-control" required maxlength="200">
            <div class="form-text" id="cnameCharNum"></div>
        </div>
        
        <div class="mb-3">
            <label for="CourseShort" class="form-label">Course Name (Short)</label>
            <small class="d-block text-muted">Max 10 characters, no spaces - Appropriate acronym</small>
            <input type="text" name="CourseShort" id="CourseShort" class="form-control" maxlength="10">
            <div class="form-text" id="cnameshortCharNum"></div>
        </div>
        
        <div class="mb-3">
            <label for="CourseDescription" class="form-label">Course Description</label>
            <small class="d-block text-muted">Max 254 characters - Overall purpose in 2-3 sentences including: course duration, target learners, delivery method</small>
            <textarea name="CourseDescription" id="CourseDescription" class="form-control" rows="3" required maxlength="254"></textarea>
            <div class="form-text" id="cdescChar"></div>
        </div>
        
        <div class="mb-3">
            <label for="CourseAbstract" class="form-label">Course Abstract</label>
            <small class="d-block text-muted">Max 4000 characters - Detailed elaboration including background, objectives, benefits, structure, competencies</small>
            <textarea name="CourseAbstract" id="CourseAbstract" class="form-control" rows="6" maxlength="4000"></textarea>
            <div class="form-text" id="cabstractChar"></div>
        </div>
        
        <div class="row">
            <!-- <div class="col-md-6 mb-3">
                <label for="Prerequisites" class="form-label">Prerequisites</label>
                <small class="d-block text-muted">Required courses or resources to complete before this course</small>
                <input type="text" name="Prerequisites" id="Prerequisites" class="form-control">
            </div> -->
            <div class="col mb-3">
                <label for="Keywords" class="form-label">Keywords</label>
                <small class="d-block text-muted">Comma-separated search terms <span class="fw-bold">not in title/description</span></small>
                <input type="text" name="Keywords" id="Keywords" class="form-control">
            </div>
        </div>
        
        <div class="mb-3">
            <label for="CourseNotes" class="form-label">Notes</label>
            <textarea name="CourseNotes" id="CourseNotes" class="form-control" rows="3"></textarea>
        </div>
    </div>
    
    <!-- Taxonomies Section -->
    <div class="form-section">
        <div class="form-section-title">Taxonomies</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="Topics" class="form-label">Topic</label>
                <select name="Topics" id="Topics" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($topics as $t): ?>
                        <option value="<?= sanitize($t) ?>"><?= sanitize($t) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Audience" class="form-label">Audience</label>
                <select name="Audience" id="Audience" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($audience as $a): ?>
                        <option value="<?= sanitize($a) ?>"><?= sanitize($a) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="Levels" class="form-label">Group</label>
                <select name="Levels" id="Levels" class="form-select" required>
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($levels as $l): ?>
                        <option value="<?= sanitize($l) ?>"><?= sanitize($l) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Reporting" class="form-label">Evaluation</label>
                <select name="Reporting" id="Reporting" class="form-select">
                    <option value="" disabled selected>Select one</option>
                    <?php foreach($reportinglist as $r): ?>
                        <option value="<?= sanitize($r) ?>"><?= sanitize($r) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
    </div>
    
    <!-- People Section -->
    <div class="form-section">
        <div class="form-section-title">People</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="CourseOwner" class="form-label">Owner</label>
                <small class="d-block text-muted">The manager responsible for delivery</small>
                <select name="CourseOwner" id="CourseOwner" class="form-select" required>
                    <option value="">Select one</option>
                    <?php getPeople($user) ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Developer" class="form-label">Developer</label>
                <small class="d-block text-muted">Responsible for materials creation/revisions</small>
                <select name="Developer" id="Developer" class="form-select">
                    <option value="">Select one</option>
                    <?php getPeople($user) ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="EffectiveDate" class="form-label">Effective Date</label>
            <small class="d-block text-muted">Date the course should be visible to learners</small>
            <input type="date" name="EffectiveDate" id="EffectiveDate" class="form-control" required>
        </div>
    </div>
    
    <!-- Delivery Details Section -->
    <div class="form-section">
        <div class="form-section-title">Delivery Details</div>
        
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="MinEnroll" class="form-label">Min Participants</label>
                <input type="number" name="MinEnroll" id="MinEnroll" class="form-control" min="1">
            </div>
            <div class="col-md-3 mb-3">
                <label for="MaxEnroll" class="form-label">Max Participants</label>
                <input type="number" name="MaxEnroll" id="MaxEnroll" class="form-control" min="1">
            </div>
            <div class="col-md-2 mb-3">
                <label for="ClassDays" class="form-label">Days</label>
                <input type="text" name="ClassDays" id="ClassDays" class="form-control">
            </div>
            <div class="col-md-2 mb-3">
                <label for="StartTime" class="form-label">Start Time</label>
                <input type="text" name="StartTime" id="StartTime" class="form-control starttime">
            </div>
            <div class="col-md-2 mb-3">
                <label for="EndTime" class="form-label">End Time</label>
                <input type="text" name="EndTime" id="EndTime" class="form-control endtime">
            </div>
        </div>
    </div>
    
    <!-- Links & Resources Section -->
    <div class="form-section">
        <div class="form-section-title">Links & Resources</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="elearning" class="form-label">eLearning Course URL</label>
                <small class="d-block text-muted">Include the URL link for the course</small>
                <input type="url" name="elearning" id="elearning" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <!-- Spacer for layout -->
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="PreWork" class="form-label">Pre-work Link</label>
                <input type="url" name="PreWork" id="PreWork" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label for="PostWork" class="form-label">Post-work Link</label>
                <input type="url" name="PostWork" id="PostWork" class="form-control">
            </div>
        </div>
    </div>
    
    <!-- Additional Options Section -->
    <div class="form-section">
        <div class="form-section-title">Additional Options</div>
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-secondary">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="WeShip" id="WeShip">
                        <label class="form-check-label" for="WeShip">
                            <strong>Learning Centre ships materials?</strong><br>
                            <small>Check if Learning Centre manages &amp; ships course materials</small>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-secondary">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="Alchemer" id="Alchemer" value="1">
                        <label class="form-check-label" for="Alchemer">
                            <strong>Uses Alchemer survey?</strong><br>
                            <small>Check if this course uses an Alchemer survey</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-center">
        <button type="submit" class="btn btn-primary btn-lg">Submit New Course Request</button>
    </div>
</form>

</div>
</div>
</div>


<?php require('templates/javascript.php') ?>
<script>
$(document).ready(function(){
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Platform-based field visibility
    $('#Platform').on('change', function() {
        if($(this).val() === 'PSA Learning System') {
            $('#notelm').addClass('d-none');
        } else {
            $('#notelm').removeClass('d-none');
        }
    });
    
    // Time picker setup
    var moment = rome.moment;
    var endtime = rome(document.querySelector('.endtime'), { 
        date: false,
        timeValidator: function (d) {
            var m = moment(d);
            var start = m.clone().hour(7).minute(59).second(59);
            var end = m.clone().hour(16).minute(30).second(1);
            return m.isAfter(start) && m.isBefore(end);
        }
    });
    var starttime = rome(document.querySelector('.starttime'), { 
        date: false,
        timeValidator: function (d) {
            var m = moment(d);
            var start = m.clone().hour(7).minute(59).second(59);
            var end = m.clone().hour(16).minute(0).second(1);
            return m.isAfter(start) && m.isBefore(end);
        }
    });
    
    // Character count for Course Name
    $('#CourseName').on('input', function() {
        var max = 200;
        var len = $(this).val().length;
        var remaining = max - len;
        var $feedback = $('#cnameCharNum');
        
        if (len >= max) {
            $feedback.removeClass('text-success').addClass('text-danger')
                .text('Character limit reached');
        } else if (remaining <= 20) {
            $feedback.removeClass('text-success').addClass('text-warning')
                .text(remaining + ' characters remaining');
        } else {
            $feedback.removeClass('text-danger text-warning').addClass('text-success')
                .text(remaining + ' characters remaining');
        }
    });
    
    // Character count for Course Short Name
    $('#CourseShort').on('input', function() {
        var max = 10;
        var len = $(this).val().length;
        var remaining = max - len;
        var $feedback = $('#cnameshortCharNum');
        
        if (len >= max) {
            $feedback.removeClass('text-success').addClass('text-danger')
                .text('Character limit reached');
        } else {
            $feedback.removeClass('text-danger').addClass('text-success')
                .text(remaining + ' characters remaining');
        }
    });
    
    // Character count for Course Description
    $('#CourseDescription').on('input', function() {
        var max = 254;
        var len = $(this).val().length;
        var remaining = max - len;
        var $feedback = $('#cdescChar');
        
        if (len >= max) {
            $feedback.removeClass('text-success').addClass('text-danger')
                .text('Character limit reached');
        } else if (remaining <= 50) {
            $feedback.removeClass('text-success').addClass('text-warning')
                .text(remaining + ' characters remaining');
        } else {
            $feedback.removeClass('text-danger text-warning').addClass('text-success')
                .text(remaining + ' characters remaining');
        }
    });
    
    // Character count for Course Abstract
    $('#CourseAbstract').on('input', function() {
        var max = 4000;
        var len = $(this).val().length;
        var remaining = max - len;
        var $feedback = $('#cabstractChar');
        
        if (len >= max) {
            $feedback.removeClass('text-success').addClass('text-danger')
                .text('Character limit reached');
        } else if (remaining <= 200) {
            $feedback.removeClass('text-success').addClass('text-warning')
                .text(remaining + ' characters remaining');
        } else {
            $feedback.removeClass('text-danger text-warning').addClass('text-success')
                .text(remaining + ' characters remaining');
        }
    });
    
    // Form validation
    $('#serviceRequestForm').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Check required selects
        $(this).find('select[required]').each(function() {
            if(!$(this).val() || $(this).val() === '') {
                isValid = false;
                $(this).addClass('is-invalid');
                errors.push('Please select a ' + $(this).prev('label').text().replace(':', ''));
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Check required inputs
        $(this).find('input[required], textarea[required]').each(function() {
            if(!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('is-invalid');
                errors.push('Please fill in ' + $(this).prev('label').text().replace(':', ''));
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Check delivery method dropdown
        if(!$('#Method').val()) {
            isValid = false;
            $('#Method').addClass('is-invalid');
            errors.push('Please select a delivery method');
        }
        
        if(!isValid) {
            e.preventDefault();
            alert('Please correct the following errors:\n\n' + errors.join('\n'));
        }
    });
    
    // Remove invalid class on change/input
    $('select[required], input[required], textarea[required]').on('change input', function() {
        $(this).removeClass('is-invalid');
    });
});
</script>
<?php require('templates/footer.php') ?>