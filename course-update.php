<?php
ob_start();
require('inc/lsapp.php');
require('inc/Parsedown.php');
require('inc/ches_client.php');
$Parsedown = new Parsedown();
opcache_reset();

// Check authorization
// For POST requests, allow admins or partner admins
if($_POST) {
    $courseid = filter_var($_POST['CourseID']);
    if(!$courseid) {
        die("Invalid course ID");
    }

    if(!isAdmin()) {
        // They're not an admin, so check if they're a partner admin for this specific course
        if(!isCoursePartnerAdmin($courseid)) {
            die("You're not allowed to edit this course, sorry.");
        }
    }
} else {
    // For GET requests (viewing the full form), only admins are allowed
    // Partner admins use a separate simplified UI
    if(!isAdmin()) {
        die("You're not allowed to access this page, sorry.");
    }
}

// Handle form submission
if($_POST) {
    // courseid already validated above
    
    // Get current course data to check existing status
    $currentCourse = getCourse($courseid);
    $currentStatus = $currentCourse ? $currentCourse[1] : '';
    
    // Validate ItemCode for PSA Learning System courses being set to Active
    if($_POST['Platform'] === 'PSA Learning System' && 
       strcasecmp($_POST['Status'], 'Active') === 0 && 
       strcasecmp($currentStatus, 'Active') !== 0) {
        
        $itemCode = trim($_POST['ItemCode']);
        
        // Check if ItemCode is empty
        if(empty($itemCode)) {
            die("Error: Cannot set course to Active. PSA Learning System courses require an ItemCode (e.g., ITEM-12345).");
        }
        
        // Validate ItemCode format (must start with "ITEM-" and end with a number)
        if(!preg_match('/^ITEM-\d+$/', $itemCode)) {
            die("Error: Invalid ItemCode format. ItemCode must start with 'ITEM-' followed by a number (e.g., ITEM-12345).");
        }
    }
    
    // Ensure HTTPS for pre/post work links
    $prework = sanitize($_POST['PreWork']);
    $postwork = sanitize($_POST['PostWork']);
    
    if($prework) {
        $scheme = parse_url($prework, PHP_URL_SCHEME);
        if($scheme != 'https') {
            $prework = str_replace('http://', 'https://', $prework);
        }
    }
    
    if($postwork) {
        $scheme = parse_url($postwork, PHP_URL_SCHEME);
        if($scheme != 'https') {
            $postwork = str_replace('http://', 'https://', $postwork);
        }
    }
    
    // Process checkboxes
    $weship = isset($_POST['WeShip']) ? 'Yes' : 'No';
    $alchemer = isset($_POST['Alchemer']) ? 'Yes' : 'No';
    $hubInclude = isset($_POST['HUBInclude']) ? 'Yes' : 'No';
    $featured = isset($_POST['Featured']) ? 'Yes' : 'No';
    $isMoodle = isset($_POST['isMoodle']) ? 'Yes' : 'No';
    $openAccessOptin = isset($_POST['OpenAccessOptin']) ? 'Yes' : 'No';
    
    // Process new sync fields
    $hubIncludeSync = sanitize($_POST['HubIncludeSync'] ?? 'yes');
    $hubIncludePersist = sanitize($_POST['HubIncludePersist'] ?? 'no');
    $hubPersistMessage = sanitize($_POST['HubPersistMessage'] ?? 'This course is no longer available for registration.');
    
    // Combine times
    $combinedtimes = sanitize($_POST['StartTime']) . ' - ' . sanitize($_POST['EndTime']);
    
    // Clean LAN path
    $lanpath = ltrim(trim($_POST['PathLAN']),'\\');
    $lanpath = rtrim($lanpath,'\\');
    $pathnik = ltrim(trim($_POST['PathNIK']),'\\');
    $pathnik = rtrim($pathnik,'\\');
    
    // Create slug
    $slug = createSlug($_POST['CourseName']);
    
    // Get current timestamp
    $now = date('Y-m-d\TH:i:s');
    
    // Build course data array
    $course = [
        $_POST['CourseID'],
        sanitize($_POST['Status']),
        sanitize($_POST['CourseName']),
        sanitize($_POST['CourseShort']),
        sanitize($_POST['ItemCode']),
        $combinedtimes,
        sanitize($_POST['ClassDays']),
        sanitize($_POST['ELM']),
        $prework,
        $postwork,
        sanitize($_POST['CourseOwner'] ?? ''),
        '', // old minmax field
        sanitize($_POST['CourseNotes']),
        $currentCourse[13], // Requested date remains unchanged
        $currentCourse[14], // RequestedBy remains unchanged
        sanitize($_POST['EffectiveDate']),
        sanitize($_POST['CourseDescription']),
        sanitize($_POST['CourseAbstract']),
        sanitize($_POST['Prerequisites']),
        sanitize($_POST['Keywords']),
        '', // old category field
        sanitize($_POST['Method']),
        sanitize($_POST['elearning']),
        $weship,
        sanitize($_POST['ProjectNumber']),
        sanitize($_POST['Responsibility']),
        sanitize($_POST['ServiceLine']),
        sanitize($_POST['STOB']),
        sanitize($_POST['MinEnroll']),
        sanitize($_POST['MaxEnroll']),
        sanitize($_POST['StartTime']),
        sanitize($_POST['EndTime']),
        sanitize($_POST['CourseColor']),
        $featured,
        sanitize($_POST['Developer'] ?? ''),
        sanitize($_POST['EvaluationsLink']),
        sanitize($_POST['LearningHubPartner']),
        $alchemer,
        sanitize($_POST['Topics']),
        sanitize($_POST['Audience'] ?? ''),
        sanitize($_POST['Levels'] ?? ''),
        sanitize($_POST['Reporting'] ?? ''),
        $lanpath,
        sanitize($_POST['PathStaging']),
        sanitize($_POST['PathLive']),
        sanitize($pathnik),
        sanitize($_POST['CHEFSFormID']), // CHEFSFormID
        $isMoodle,
        sanitize($_POST['TaxonomyProcessed'] ?? ''),
        sanitize($_POST['TaxonomyProcessedBy'] ?? ''),
        sanitize($_POST['ELMCourseID']),
        $now,
        sanitize($_POST['Platform']),
        $hubInclude,
        sanitize($_POST['RegistrationLink']),
        $slug,
        sanitize($_POST['HubExpirationDate']),
        $openAccessOptin,
        $hubIncludeSync,
        $hubIncludePersist,
        $hubPersistMessage,
        sanitize($_POST['HubIncludePersistState'] ?? ''), // HubIncludePersistState
        LOGGED_IN_IDIR
    ];

    // Update courses.csv
    $f = fopen('data/courses.csv','r');
    $temp_table = fopen('data/courses-temp.csv','w');

    // Copy headers
    $headers = fgetcsv($f);
    fputcsv($temp_table, $headers);

    // Process rows
    $coursesteward = '';
    $coursedeveloper = '';

    while (($data = fgetcsv($f)) !== FALSE) {
        if($data[0] == $courseid) {
            $coursesteward = $data[10];
            $coursedeveloper = $data[34];

            // Note: $course array already has all 63 fields (indices 0-62) including
            // HubIncludePersistState and modifiedby, so we just write it directly
            fputcsv($temp_table, $course);
        } else {
            fputcsv($temp_table, $data);
        }
    }
    
    fclose($f);
    fclose($temp_table);
    
    rename('data/courses-temp.csv', 'data/courses.csv');

    // Send email notification for course update
    try {
        $chesClient = new CHESClient();

        // Field names mapping for better readability in email
        $fieldNames = [
            0 => 'CourseID',
            1 => 'Status',
            2 => 'CourseName',
            3 => 'CourseShort',
            4 => 'ItemCode',
            5 => 'ClassTimes',
            6 => 'ClassDays',
            7 => 'ELM Link',
            8 => 'PreWork',
            9 => 'PostWork',
            10 => 'CourseOwner',
            11 => 'MinMax (legacy)',
            12 => 'CourseNotes',
            13 => 'Requested',
            14 => 'RequestedBy',
            15 => 'EffectiveDate',
            16 => 'CourseDescription',
            17 => 'CourseAbstract',
            18 => 'Prerequisites',
            19 => 'Keywords',
            20 => 'Category (legacy)',
            21 => 'Method',
            22 => 'eLearning URL',
            23 => 'WeShip',
            24 => 'ProjectNumber',
            25 => 'Responsibility',
            26 => 'ServiceLine',
            27 => 'STOB',
            28 => 'MinEnroll',
            29 => 'MaxEnroll',
            30 => 'StartTime',
            31 => 'EndTime',
            32 => 'CourseColor',
            33 => 'Featured',
            34 => 'Developer',
            35 => 'EvaluationsLink',
            36 => 'LearningHubPartner',
            37 => 'Alchemer',
            38 => 'Topics',
            39 => 'Audience',
            40 => 'Levels',
            41 => 'Reporting',
            42 => 'PathLAN',
            43 => 'PathStaging',
            44 => 'PathLive',
            45 => 'PathNIK',
            46 => 'CHEFSFormID',
            47 => 'isMoodle',
            48 => 'TaxonomyProcessed',
            49 => 'TaxonomyProcessedBy',
            50 => 'ELMCourseID',
            51 => 'LastModified',
            52 => 'Platform',
            53 => 'HUBInclude',
            54 => 'RegistrationLink',
            55 => 'Slug',
            56 => 'HubExpirationDate',
            57 => 'OpenAccessOptin',
            58 => 'HubIncludeSync',
            59 => 'HubIncludePersist',
            60 => 'HubPersistMessage',
            61 => 'HubIncludePersistState',
            62 => 'ModifiedBy'
        ];

        // Build diff of changes
        $changes = [];
        $maxFields = max(count($currentCourse), count($course));

        for ($i = 0; $i < $maxFields; $i++) {
            // Skip LastModified field as it always changes
            if ($i === 51) continue;

            $oldValue = isset($currentCourse[$i]) ? $currentCourse[$i] : '';
            $newValue = isset($course[$i]) ? $course[$i] : '';

            // Only include if values are different
            if ($oldValue !== $newValue) {
                $fieldName = isset($fieldNames[$i]) ? $fieldNames[$i] : "Field $i";
                $changes[] = [
                    'field' => $fieldName,
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        // Only send email if there are actual changes
        if (!empty($changes)) {
            $emailBody = "Course Updated: " . sanitize($course[2]) . "\n";
            $emailBody .= "Course ID: {$courseid}\n\n";

            // Get the base URL for linking to the course
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $courseLink = $protocol . $host . "/lsapp/course.php?courseid={$courseid}";

            $emailBody .= "View Course: {$courseLink}\n\n";
            $emailBody .= "Changes Made:\n";
            $emailBody .= str_repeat("=", 80) . "\n\n";

            foreach ($changes as $change) {
                $emailBody .= "{$change['field']}:\n";
                $emailBody .= "  OLD: " . (empty($change['old']) ? '(empty)' : $change['old']) . "\n";
                $emailBody .= "  NEW: " . (empty($change['new']) ? '(empty)' : $change['new']) . "\n";
                $emailBody .= "\n";
            }

            $emailBody .= str_repeat("=", 80) . "\n";
            $emailBody .= "Updated by: " . LOGGED_IN_IDIR . "\n";
            $emailBody .= "Updated at: {$now}\n";

            // Send the email
            $emailResult = $chesClient->sendEmail(
                ['allan.haggett@gov.bc.ca'],
                "Course Updated: " . sanitize($course[2]),
                $emailBody
            );

            //error_log("Course update notification sent successfully for course ID: {$courseid}");
        }
    } catch (Exception $e) {
        error_log("CHES Email Exception on course update: " . $e->getMessage());
    }

    // Update course-people.csv if steward or developer changed
    $peoplefp = fopen('data/course-people.csv', 'a+');
    
    if(($_POST['CourseOwner'] ?? '') != $coursesteward && !empty($_POST['CourseOwner'])) {
        $stew = [$courseid, 'steward', $_POST['CourseOwner'], $now];
        fputcsv($peoplefp, $stew);
    }
    
    if(isset($_POST['Developer']) && $_POST['Developer'] != $coursedeveloper && !empty($_POST['Developer'])) {
        $dev = [$courseid, 'dev', $_POST['Developer'], $now];
        fputcsv($peoplefp, $dev);
    }
    
    fclose($peoplefp);

    // Update development partner relationships
    $devPartnerFile = 'data/courses-devpartners.csv';
    $devPartnerTempFile = 'data/courses-devpartners-temp.csv';

    // Read existing file and remove relationships for this course
    $maxId = 0;
    $existingRows = [];

    if (file_exists($devPartnerFile)) {
        $input = fopen($devPartnerFile, 'r');
        if ($input !== false) {
            fgetcsv($input); // Skip header
            while (($row = fgetcsv($input)) !== false) {
                if (!empty($row[0]) && is_numeric($row[0])) {
                    $maxId = max($maxId, (int)$row[0]);
                }
                // Keep rows that don't belong to this course
                if ($row[1] != $courseid) {
                    $existingRows[] = $row;
                }
            }
            fclose($input);
        }
    }

    // Write updated file
    $output = fopen($devPartnerTempFile, 'w');
    if ($output !== false) {
        // Write header
        fputcsv($output, ['id', 'course_id', 'development_partner_id']);

        // Write existing rows (excluding this course)
        foreach ($existingRows as $row) {
            fputcsv($output, $row);
        }

        // Add new relationships for this course
        if (!empty($_POST['DevelopmentPartners']) && is_array($_POST['DevelopmentPartners'])) {
            //error_log("DEBUG: DevelopmentPartners POST data: " . print_r($_POST['DevelopmentPartners'], true));
            //error_log("DEBUG: Course ID: " . $courseid);
            foreach ($_POST['DevelopmentPartners'] as $partnerId) {
                $maxId++;
                $relationship = [$maxId, $courseid, sanitize($partnerId)];
                //error_log("DEBUG: Writing relationship: " . print_r($relationship, true));
                fputcsv($output, $relationship);
            }
        } else {
            //error_log("DEBUG: No DevelopmentPartners in POST or not an array. POST keys: " . print_r(array_keys($_POST), true));
        }

        fclose($output);

        // Replace original file
        rename($devPartnerTempFile, $devPartnerFile);
    }

    // Check if this is from partner portal
    if (!empty($_POST['partner_redirect'])) {
        // Redirect back to partner portal dashboard
        $partnerId = $_POST['LearningHubPartner'];
        header("Location: /learning/hub/partners/dashboard.php?partnerid={$partnerId}&message=CourseUpdated");
    } else {
        header('Location: course.php?courseid=' . $courseid);
    }
    exit;
}

// Display form
$courseid = (isset($_GET['courseid'])) ? $_GET['courseid'] : 0;
$deets = getCourse($courseid);

if(!$deets) {
    header('Location: /lsapp/');
    exit;
}

// Get current steward and developer from course-people.csv
$stewsdevs = getCoursePeople($courseid);

// Load partners and platforms from JSON files
$partners = getAllPartners();
$platforms = getAllPlatforms();

// Get taxonomy options
$topics = getAllTopics();
$audience = getAllAudiences();
$deliverymethods = getDeliveryMethods();
$levels = getLevels();
$reportinglist = getReportingList();

// Load development partners
$devPartnersFile = 'data/development-partners.csv';
$devPartners = [];
if (file_exists($devPartnersFile)) {
    $data = array_map('str_getcsv', file($devPartnersFile));
    array_shift($data); // Remove header
    foreach ($data as $row) {
        if (!empty($row[0]) && ($row[1] ?? '') === 'active') {
            $devPartners[] = [
                'id' => $row[0],
                'name' => $row[3] ?? ''
            ];
        }
    }
    // Sort by name
    usort($devPartners, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}

// Get current development partners for this course
$currentDevPartners = getDevPartnersByCourseID($courseid);
$currentDevPartnerIds = array_map(function($dp) { return (string)$dp[0]; }, $currentDevPartners);
// error_log("DEBUG DISPLAY: Course ID: " . $courseid);
// error_log("DEBUG DISPLAY: Current dev partners raw: " . print_r($currentDevPartners, true));
// error_log("DEBUG DISPLAY: Current dev partner IDs: " . print_r($currentDevPartnerIds, true));

// Determine if ELM-synced fields should be locked (only for PSA Learning System courses)
$lockELMFields = ($deets[52] === 'PSA Learning System');

?>
<?php getHeader() ?>

<title>Update <?= sanitize($deets[2]) ?></title>
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
.form-control:disabled,
.form-select:disabled {
    background-color: #f8f9fa;
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container mb-5">
<div class="row justify-content-md-center">
<div class="col-md-10">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Update: <?= sanitize($deets[2]) ?></h1>
    <a href="course.php?courseid=<?= $courseid ?>" class="btn btn-light">Cancel</a>
</div>

<?php if ($lockELMFields): ?>
<div class="alert alert-warning">
    <div><strong>Active PSA Learning System course</strong></div>
    Fields marked with <span class="text-danger fw-bold">*</span> are synchronized from PSA Learning System (ELM) and cannot be edited here.
    Please use the <a href="/lsapp/course-change/create.php?&courseid=<?= sanitize($deets[0]) ?>" class="alert-link">course update request process</a> to update these fields in ELM.
</div>
<?php endif; ?>

<form method="post" action="course-update.php" class="mb-3" id="courseupdateform">
    
    <!-- Hidden fields -->
    <input type="hidden" name="CourseID" value="<?= sanitize($deets[0]) ?>">
    <!-- <input type="hidden" name="Requested" value="<?= sanitize($deets[13]) ?>">
    <input type="hidden" name="RequestedBy" value="<?= sanitize($deets[14]) ?>"> -->
    <input type="hidden" name="TaxonomyProcessed" value="<?= sanitize($deets[48]) ?>">
    <input type="hidden" name="TaxonomyProcessedBy" value="<?= sanitize($deets[49]) ?>">
    <input type="hidden" name="ProjectNumber" value="<?= sanitize($deets[24]) ?>">
    <input type="hidden" name="Responsibility" value="<?= sanitize($deets[25]) ?>">
    <input type="hidden" name="ServiceLine" value="<?= sanitize($deets[26]) ?>">
    <input type="hidden" name="STOB" value="<?= sanitize($deets[27]) ?>">
    <input type="hidden" name="Prerequisites" value="<?= sanitize($deets[18]) ?>">
    <input type="hidden" name="Featured" value="0">
    
    <!-- Basic Information Section -->
    <div class="form-section">
        <div class="form-section-title">Basic Information</div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="LearningHubPartner" class="form-label">
                    Learning Hub Partner <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?>
                </label>
                <select name="LearningHubPartner" id="LearningHubPartner" class="form-select" required <?= $lockELMFields ? 'disabled' : '' ?>>
                    <option value="" disabled <?= empty($deets[36]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($partners as $partner): ?>
                        <option value="<?= sanitize($partner['id']) ?>" <?= ($partner['id'] == $deets[36]) ? 'selected' : '' ?>>
                            <?= sanitize($partner['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="LearningHubPartner" value="<?= sanitize($deets[36]) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-4 mb-3">
                <label for="Platform" class="form-label">Platform</label>
                <select name="Platform" id="Platform" class="form-select" required>
                    <option value="" disabled <?= empty($deets[52]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($platforms as $platform): ?>
                        <option value="<?= sanitize($platform['name']) ?>" <?= ($platform['name'] == $deets[52]) ? 'selected' : '' ?>>
                            <?= sanitize($platform['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="Method" class="form-label">Delivery Method <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <select name="Method" id="Method" class="form-select" required <?= $lockELMFields ? 'disabled' : '' ?>>
                    <option value="" disabled>Select one</option>
                    <?php $methods = ['eLearning','Webinar','Curated Pathway','Blended','Classroom'] ?>
                    <?php foreach($methods as $method): ?>
                        <option value="<?= $method ?>" <?= ($method == $deets[21]) ? 'selected' : '' ?>><?= $method ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="Method" value="<?= sanitize($deets[21]) ?>">
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="Status" class="form-label">Status</label>
                <select name="Status" id="Status" class="form-select" required>
                    <option value="" disabled>Select one</option>
                    <?php $statuses = ['Requested','Active','Inactive'] ?>
                    <?php foreach($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= ($s == $deets[1]) ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="CourseColor" class="form-label">Color</label>
                <input type="text" name="CourseColor" id="CourseColor" class="form-control" value="<?= sanitize($deets[32]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="ItemCode" class="form-label">Item Code</label>
                <input type="text" name="ItemCode" id="ItemCode" class="form-control" value="<?= sanitize($deets[4]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="ELMCourseID" class="form-label">ELM Course ID <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <input type="text" name="ELMCourseID" id="ELMCourseID" class="form-control" value="<?= sanitize($deets[50]) ?>" <?= $lockELMFields ? 'disabled' : '' ?>>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="ELMCourseID" value="<?= sanitize($deets[50]) ?>">
                <?php endif; ?>
            </div>
        </div>
        
        <div id="notelm" class="<?= ($deets[52] == 'PSA Learning System') ? 'd-none' : '' ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="RegistrationLink" class="form-label">Registration Link</label>
                    <small class="d-block text-muted">If not in Learning System, where to register?</small>
                    <input type="url" name="RegistrationLink" id="RegistrationLink" class="form-control" value="<?= sanitize($deets[54]) ?>">
                </div>

            </div>
        </div>
    </div>
    
    <!-- Course Details Section -->
    <div class="form-section">
        <div class="form-section-title">Course Details</div>
        
        <div class="mb-3">
            <label for="CourseName" class="form-label">Course Name (Long) <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
            <small class="d-block text-muted">Max 200 characters - Full/Complete title of the course</small>
            <input type="text" name="CourseName" id="CourseName" class="form-control" required value="<?= sanitize($deets[2]) ?>" maxlength="200" <?= $lockELMFields ? 'disabled' : '' ?>>
            <?php if ($lockELMFields): ?>
            <input type="hidden" name="CourseName" value="<?= sanitize($deets[2]) ?>">
            <?php endif; ?>
            <div class="form-text" id="cnameCharNum"></div>
        </div>
        
        <div class="mb-3">
            <label for="CourseShort" class="form-label">Course Name (Short)</label>
            <small class="d-block text-muted">Max 10 characters, no spaces - Appropriate acronym</small>
            <input type="text" name="CourseShort" id="CourseShort" class="form-control" value="<?= sanitize($deets[3]) ?>" maxlength="10">
            <div class="form-text" id="cnameshortCharNum"></div>
        </div>
        
        <div class="mb-3">
            <label for="CourseDescription" class="form-label">Course Description <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
            <small class="d-block text-muted">Overall purpose in 2-3 sentences including: course duration, target learners, delivery method</small>
            <textarea name="CourseDescription" id="CourseDescription" class="form-control" rows="5" required <?= $lockELMFields ? 'disabled' : '' ?>><?= sanitize($deets[16]) ?></textarea>
            <?php if ($lockELMFields): ?>
            <input type="hidden" name="CourseDescription" value="<?= sanitize($deets[16]) ?>">
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label for="CourseAbstract" class="form-label">Course Abstract</label>
            <small class="d-block text-muted">Max 4000 characters - Detailed elaboration including background, objectives, benefits, structure, competencies</small>
            <textarea name="CourseAbstract" id="CourseAbstract" class="form-control" rows="6" maxlength="4000"><?= sanitize($deets[17]) ?></textarea>
            <div class="form-text" id="cabstractChar"></div>
        </div>
        
        <div class="row">
            <!-- <div class="col-md-6 mb-3">
                <label for="Prerequisites" class="form-label">Prerequisites</label>
                <small class="d-block text-muted">Required courses or resources to complete before this course</small>
                <input type="text" name="Prerequisites" id="Prerequisites" class="form-control" value="<?= sanitize($deets[18]) ?>">
            </div> -->
            <div class="col mb-3">
                <label for="Keywords" class="form-label">Keywords <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <small class="d-block text-muted">Comma-separated search terms <span class="fw-bold">not in title/description</span></small>
                <input type="text" name="Keywords" id="Keywords" class="form-control" value="<?= sanitize($deets[19]) ?>" <?= $lockELMFields ? 'disabled' : '' ?>>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="Keywords" value="<?= sanitize($deets[19]) ?>">
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="CourseNotes" class="form-label">Notes</label>
            <textarea name="CourseNotes" id="CourseNotes" class="form-control" rows="3"><?= sanitize($deets[12]) ?></textarea>
        </div>
    </div>
    
    <!-- Taxonomies Section -->
    <div class="form-section">
        <div class="form-section-title">Taxonomies</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="Topics" class="form-label">Topic <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <select name="Topics" id="Topics" class="form-select" required <?= $lockELMFields ? 'disabled' : '' ?>>
                    <option value="" disabled <?= empty($deets[38]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($topics as $t): ?>
                        <option value="<?= $t ?>" <?= ($deets[38] == $t) ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="Topics" value="<?= sanitize($deets[38]) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Audience" class="form-label">Audience <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <select name="Audience" id="Audience" class="form-select" required <?= $lockELMFields ? 'disabled' : '' ?>>
                    <option value="" disabled <?= empty($deets[39]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($audience as $a): ?>
                        <option value="<?= $a ?>" <?= ($deets[39] == $a) ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="Audience" value="<?= sanitize($deets[39]) ?>">
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="Levels" class="form-label">Group <?php if ($lockELMFields): ?><span class="text-danger fw-bold">*</span><?php endif; ?></label>
                <select name="Levels" id="Levels" class="form-select" <?= $lockELMFields ? 'disabled' : '' ?>>
                    <option value="" disabled <?= empty($deets[40]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($levels as $l): ?>
                        <option value="<?= $l ?>" <?= ($deets[40] == $l) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($lockELMFields): ?>
                <input type="hidden" name="Levels" value="<?= sanitize($deets[40]) ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Reporting" class="form-label">Evaluation</label>
                <select name="Reporting" id="Reporting" class="form-select">
                    <option value="" disabled <?= empty($deets[41]) ? 'selected' : '' ?>>Select one</option>
                    <?php foreach($reportinglist as $r): ?>
                        <option value="<?= $r ?>" <?= ($deets[41] == $r) ? 'selected' : '' ?>><?= $r ?></option>
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
                <label for="CourseOwner" class="form-label">Steward</label>
                <small class="d-block text-muted">The manager responsible for delivery</small>
                <select name="CourseOwner" id="CourseOwner" class="form-select">
                    <option value="">Select one</option>
                    <?php 
                    $currentSteward = (!empty($stewsdevs['stewards'][0][2])) ? $stewsdevs['stewards'][0][2] : $deets[10];
                    getPeople($currentSteward); 
                    ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="Developer" class="form-label">Developer</label>
                <small class="d-block text-muted">Responsible for materials creation/revisions</small>
                <select name="Developer" id="Developer" class="form-select">
                    <option value="">Select one</option>
                    <?php
                    $currentDeveloper = (!empty($stewsdevs['developers'][0][2])) ? $stewsdevs['developers'][0][2] : $deets[34];
                    getPeople($currentDeveloper);
                    ?>
                </select>
            </div>
        </div>
        <div class="row">
            <?php if (!empty($devPartners)): ?>
            <div class="col-md-6 mb-3">
                <label class="form-label">Development Partner(s)</label>
                <small class="d-block text-muted mb-2">External organizations that helped develop this course</small>
                <div class="border rounded p-3 bg-body-tertiary" style="max-height: 200px; overflow-y: auto;">
                    <div class="row g-2">
                        <?php foreach($devPartners as $dp): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="DevelopmentPartners[]" value="<?= htmlspecialchars($dp['id']) ?>" id="devPartner<?= htmlspecialchars($dp['id']) ?>" <?= in_array((string)$dp['id'], $currentDevPartnerIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="devPartner<?= htmlspecialchars($dp['id']) ?>">
                                    <?= htmlspecialchars($dp['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <?php endif ?>
            <div class="col-md-6 mb-3">
                <label for="EffectiveDate" class="form-label">Effective Date</label>
                <small class="d-block text-muted">Date the course should be visible to learners</small>
                <input type="date" name="EffectiveDate" id="EffectiveDate" class="form-control" value="<?= sanitize($deets[15]) ?>">
            </div>
        </div>
    </div>
    
    <!-- Delivery Details Section -->
    <div class="form-section">
        <div class="form-section-title">Delivery Details</div>
        
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="MinEnroll" class="form-label">Min Participants</label>
                <input type="number" name="MinEnroll" id="MinEnroll" class="form-control" min="1" value="<?= sanitize($deets[28]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="MaxEnroll" class="form-label">Max Participants</label>
                <input type="number" name="MaxEnroll" id="MaxEnroll" class="form-control" min="1" value="<?= sanitize($deets[29]) ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="ClassDays" class="form-label">Days</label>
                <input type="text" name="ClassDays" id="ClassDays" class="form-control" value="<?= sanitize($deets[6]) ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="StartTime" class="form-label">Start Time</label>
                <input type="text" name="StartTime" id="StartTime" class="form-control starttime" value="<?= sanitize($deets[30]) ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="EndTime" class="form-label">End Time</label>
                <input type="text" name="EndTime" id="EndTime" class="form-control endtime" value="<?= sanitize($deets[31]) ?>">
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
                <input type="url" name="elearning" id="elearning" class="form-control" value="<?= sanitize($deets[22]) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="ELM" class="form-label">ELM Link</label>
                <input type="url" name="ELM" id="ELM" class="form-control" value="<?= sanitize($deets[7]) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="PreWork" class="form-label">Pre-work Link</label>
                <input type="url" name="PreWork" id="PreWork" class="form-control" value="<?= sanitize($deets[8]) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="PostWork" class="form-label">Post-work Link</label>
                <input type="url" name="PostWork" id="PostWork" class="form-control" value="<?= sanitize($deets[9]) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="EvaluationsLink" class="form-label">Evaluation Link</label>
            <input type="url" name="EvaluationsLink" id="EvaluationsLink" class="form-control" value="<?= sanitize($deets[35]) ?>">
        </div>
    </div>
    
    
    <!-- Learning Hub Sync Options Section -->
    <div class="form-section">
        <div class="form-section-title">Learning Hub Sync Options</div>
        <div class="alert alert-secondary">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="HUBInclude" id="HUBInclude" 
                        <?= ($deets[53] == 'Yes' || $deets[53] == 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="HUBInclude">Include in LearningHUB?</label>
            </div>
        </div>
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading">Course Sync Behavior</h6>
            <p class="mb-2">These settings control how this course behaves in the Learning Hub synchronization process:</p>
            <ul class="">
                <li><strong>Normal courses</strong>: Removed from catalog when no longer in ELM (default behavior)</li>
                <li><strong>Always visible</strong>: Remain in catalog regardless of ELM status (HubIncludeSync = no)</li>
                <li><strong>Persist with message</strong>: Stay in catalog but segregated with custom message (HubIncludePersist = yes)</li>
            </ul>
            <p>Set an expiry date and your course will be removed from the catalog (or persist) after that date.</p>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="HubIncludeSync" class="form-label">Hub Include Sync</label>
                <small class="d-block text-muted">Should this course participate in automatic sync removal?</small>
                <select name="HubIncludeSync" id="HubIncludeSync" class="form-select">
                    <?php $hubIncludeSync = isset($deets[58]) ? $deets[58] : 'yes'; ?>
                    <option value="yes" <?= ($hubIncludeSync == 'yes') ? 'selected' : '' ?>>Yes - Normal sync behavior</option>
                    <option value="no" <?= ($hubIncludeSync == 'no') ? 'selected' : '' ?>>No - Always keep in catalog</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="HubIncludePersist" class="form-label">Hub Include Persist</label>
                <small class="d-block text-muted">Should this course persist with special handling when removed from ELM?</small>
                <select name="HubIncludePersist" id="HubIncludePersist" class="form-select">
                    <?php $hubIncludePersist = isset($deets[59]) ? $deets[59] : 'no'; ?>
                    <option value="no" <?= ($hubIncludePersist == 'no') ? 'selected' : '' ?>>No - Normal removal</option>
                    <option value="yes" <?= ($hubIncludePersist == 'yes') ? 'selected' : '' ?>>Yes - Keep with custom message</option>
                </select>
                
                <div id="persistStateInfo" class="mt-2" style="<?= ($hubIncludePersist == 'yes') ? '' : 'display: none;' ?>">
                    <?php $hubIncludePersistState = isset($deets[61]) ? $deets[61] : 'active'; ?>
                    <p class="text-body-secondary mb-0">
                        <small>
                            <strong>Current State:</strong>
                            <?php if ($hubIncludePersistState === 'active'): ?>
                                <span class="badge bg-success-subtle text-success-emphasis">Active</span>
                                - Course is currently in ELM feed
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis">Inactive</span>
                                - Course is not in ELM feed but persists in catalog
                            <?php endif; ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row" id="persistMessageRow" style="<?= ($hubIncludePersist == 'yes') ? '' : 'display: none;' ?>">
            <div class="col-12 mb-3">
                <label for="HubPersistMessage" class="form-label">Hub Persist Message</label>
                <small class="d-block text-muted">Message to display when course persists but has no offerings</small>
                <textarea name="HubPersistMessage" id="HubPersistMessage" class="form-control" rows="2"><?= sanitize(isset($deets[60]) ? $deets[60] : 'This course is no longer available for registration.') ?></textarea>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="HubExpirationDate" class="form-label">Expiration Date</label>
                <small class="d-block text-muted">Date to remove from search results</small>
                <input type="date" name="HubExpirationDate" id="HubExpirationDate" class="form-control" value="<?= sanitize($deets[56]) ?>">
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
                        <input type="checkbox" class="form-check-input" name="WeShip" id="WeShip" 
                               <?= ($deets[23] == 'Yes' || $deets[23] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="WeShip">
                            <strong>Learning Centre ships materials?</strong>
                            <div>Check if Learning Centre manages &amp; ships course materials</div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-secondary">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="Alchemer" id="Alchemer" value="1"
                               <?= ($deets[37] == 'Yes' || $deets[37] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="Alchemer">
                            <strong>Uses Alchemer survey?</strong>
                            <div>Check if this course uses an Alchemer survey</div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="alert alert-secondary">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="OpenAccessOptin" id="OpenAccessOptin" 
                            <?= ($deets[57] == 'Yes' || $deets[57] == 1) ? 'checked' : '' ?>
                            <?= empty($deets[3]) ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="OpenAccessOptin">OpenAccess Publish?</label>
                        <?php if (empty($deets[3])): ?>
                            <div class="alert alert-warning">
                                Add a course short name to enable this option.
                            </div>
                        <?php endif ?>
                        <div>Publish this course's 
                            <a href="/lsapp/courses.php?openaccess=true&sort=dateadded">OpenAccess public page</a>
                         on "NIK".</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-secondary">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="isMoodle" id="isMoodle" 
                            <?= ($deets[47] == 'Yes' || $deets[47] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isMoodle">PSA Moodle Course?</label>
                        <div>Is this course hosted in our PSA Moodle installation?</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Print Details Section 
    <div class="form-section">
        <div class="form-section-title">Print Materials Operating Codes</div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="ProjectNumber" class="form-label">Project Number</label>
                <input type="text" name="ProjectNumber" id="ProjectNumber" class="form-control" value="<?= sanitize($deets[24]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="Responsibility" class="form-label">Responsibility</label>
                <input type="text" name="Responsibility" id="Responsibility" class="form-control" value="<?= sanitize($deets[25]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="ServiceLine" class="form-label">Service Line</label>
                <input type="text" name="ServiceLine" id="ServiceLine" class="form-control" value="<?= sanitize($deets[26]) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="STOB" class="form-label">STOB</label>
                <input type="text" name="STOB" id="STOB" class="form-control" value="<?= sanitize($deets[27]) ?>">
            </div>
        </div>
    </div>
    -->
    
    <!-- Developer File Paths Section -->
    <div class="form-section">
        <div class="form-section-title">Developer File Paths</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="PathLAN" class="form-label">LAN Path</label>
                <input type="text" name="PathLAN" id="PathLAN" class="form-control" value="<?= sanitize($deets[42]) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="PathStaging" class="form-label">Staging Path</label>
                <input type="url" name="PathStaging" id="PathStaging" class="form-control" value="<?= sanitize($deets[43]) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="PathLive" class="form-label">Live Path</label>
                <input type="url" name="PathLive" id="PathLive" class="form-control" value="<?= sanitize($deets[44]) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="PathNIK" class="form-label">NIK Path</label>
                <input type="text" name="PathNIK" id="PathNIK" class="form-control" value="<?= sanitize($deets[45]) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="CHEFSFormID" class="form-label">CHEFS Form ID</label>
            <input type="text" name="CHEFSFormID" id="CHEFSFormID" class="form-control" minlength="34" maxlength="38" value="<?= sanitize($deets[46]) ?>">
        </div>
    </div>
    
    <div class="d-flex justify-content-center">
        <button type="submit" class="btn btn-primary btn-lg">Save Course Info</button>
    </div>
</form>

</div>
</div>
</div>

<?php require('templates/javascript.php') ?>
<script>
$(document).ready(function(){
    // Platform-based field visibility
    $('#Platform').on('change', function() {
        if($(this).val() === 'PSA Learning System') {
            $('#notelm').addClass('d-none');
        } else {
            $('#notelm').removeClass('d-none');
        }
    });
    
    // Hub Persist options visibility
    $('#HubIncludePersist').on('change', function() {
        if($(this).val() === 'yes') {
            $('#persistStateInfo').slideDown();
            $('#persistMessageRow').slideDown();
        } else {
            $('#persistStateInfo').slideUp();
            $('#persistMessageRow').slideUp();
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
    
    // Character count for Course Description - REMOVED per user request
    // $('#CourseDescription').on('input', function() {
    //     var max = 254;
    //     var len = $(this).val().length;
    //     var remaining = max - len;
    //     var $feedback = $('#cdescChar');
    //     
    //     if (len >= max) {
    //         $feedback.removeClass('text-success').addClass('text-danger')
    //             .text('Character limit reached');
    //     } else if (remaining <= 50) {
    //         $feedback.removeClass('text-success').addClass('text-warning')
    //             .text(remaining + ' characters remaining');
    //     } else {
    //         $feedback.removeClass('text-danger text-warning').addClass('text-success')
    //             .text(remaining + ' characters remaining');
    //     }
    // });
    
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
    $('#courseupdateform').on('submit', function(e) {
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
        
        // Validate ItemCode for PSA Learning System courses being set to Active
        var platform = $('#Platform').val();
        var status = $('#Status').val();
        var currentStatus = '<?= $deets[1] ?>'; // Get current status from PHP
        var itemCode = $('#ItemCode').val().trim();
        
        if(platform === 'PSA Learning System' && 
           status === 'Active' && 
           currentStatus.toLowerCase() !== 'active') {
            
            // Check if ItemCode is empty
            if(!itemCode) {
                isValid = false;
                $('#ItemCode').addClass('is-invalid');
                errors.push('PSA Learning System courses require an ItemCode to be set to Active (e.g., ITEM-12345)');
            }
            // Validate ItemCode format
            else if(!/^ITEM-\d+$/.test(itemCode)) {
                isValid = false;
                $('#ItemCode').addClass('is-invalid');
                errors.push('ItemCode must start with "ITEM-" followed by a number (e.g., ITEM-12345)');
            }
        }
        
        // Validate CHEFSFormID if entered
        var formId = $('#CHEFSFormID').val();

        if(formId !== '') {
            // Validate Form ID format
            if(!/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(formId)) {
                isValid = false;
                $('#CHEFSFormID').addClass('is-invalid');
                errors.push('Form ID format is invalid. Use the format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
            }
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
    
    // Real-time ItemCode validation for PSA Learning System
    function validateItemCode() {
        var platform = $('#Platform').val();
        var status = $('#Status').val();
        var currentStatus = '<?= $deets[1] ?>'; // Get current status from PHP
        var itemCode = $('#ItemCode').val().trim();
        var $itemCodeField = $('#ItemCode');
        var $helpText = $('#itemCodeHelp');
        
        // Remove any existing help text
        if($helpText.length === 0) {
            $helpText = $('<div id="itemCodeHelp" class="form-text"></div>');
            $itemCodeField.after($helpText);
        }
        
        if(platform === 'PSA Learning System' && 
           status === 'Active' && 
           currentStatus.toLowerCase() !== 'active') {
            
            if(!itemCode) {
                $itemCodeField.addClass('is-invalid');
                $helpText.removeClass('text-success').addClass('text-danger')
                    .html('<i class="bi bi-exclamation-circle"></i> Required for Active status (e.g., ITEM-12345)');
            } else if(!/^ITEM-\d+$/.test(itemCode)) {
                $itemCodeField.addClass('is-invalid');
                $helpText.removeClass('text-success').addClass('text-danger')
                    .html('<i class="bi bi-exclamation-circle"></i> Must start with "ITEM-" followed by numbers');
            } else {
                $itemCodeField.removeClass('is-invalid').addClass('is-valid');
                $helpText.removeClass('text-danger').addClass('text-success')
                    .html('<i class="bi bi-check-circle"></i> Valid ItemCode format');
            }
        } else {
            $itemCodeField.removeClass('is-invalid is-valid');
            $helpText.empty();
        }
    }
    
    // Trigger validation on relevant field changes
    $('#Platform, #Status, #ItemCode').on('change input', validateItemCode);
    
    // Run validation on page load
    validateItemCode();
    
    // Initialize character counters on page load
    $('#CourseName').trigger('input');
    $('#CourseShort').trigger('input');
    // $('#CourseDescription').trigger('input'); // Removed - no character limit
    $('#CourseAbstract').trigger('input');
});
</script>
<?php require('templates/footer.php') ?>