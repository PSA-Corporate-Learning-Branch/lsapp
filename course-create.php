<?php 
require('inc/lsapp.php');
require('inc/ches_client.php');
opcache_reset();

if(!canAccess()) {
    header('Location: /lsapp/');
    exit;
}

// Validate required fields
$requiredFields = ['CourseName', 'CourseDescription', 'LearningHubPartner', 'Platform', 'Method', 'CourseOwner', 'EffectiveDate'];
$missingFields = [];

foreach($requiredFields as $field) {
    if(empty($_POST[$field])) {
        $missingFields[] = $field;
    }
}

if(!empty($missingFields)) {
    // Redirect back with error message
    $error = urlencode('Missing required fields: ' . implode(', ', $missingFields));
    header("Location: course-request.php?error={$error}");
    exit;
}

// Generate unique course ID and timestamp
$courseid = date('YmdHis');
$now = date('Y-m-d\TH:i:s');

// Process checkboxes with proper defaults
$weship = isset($_POST['WeShip']) ? 'Yes' : 'No';
$alchemer = isset($_POST['Alchemer']) ? 'Yes' : 'No';

// Handle HUBInclude - default to 'Yes' for partner form submissions
if (!empty($_POST['partner_redirect'])) {
    // From partner form - use form value, default to 'Yes' if not set
    $hubInclude = (isset($_POST['HUBInclude']) && $_POST['HUBInclude'] == '1') ? 'Yes' : 'No';
} else {
    // From regular form - use form value if set, otherwise 'No'
    $hubInclude = isset($_POST['HUBInclude']) ? 'Yes' : 'No';
}

// Combine start and end times (if provided)
$combinedtimes = '';
if(!empty($_POST['StartTime']) && !empty($_POST['EndTime'])) {
    $combinedtimes = sanitize($_POST['StartTime']) . ' - ' . sanitize($_POST['EndTime']);
}

// Create URL slug from course name
$slug = createSlug($_POST['CourseName']);

// Build course data array with proper sanitization
$newcourse = [
    $courseid,                                      // 0: CourseID
    sanitize($_POST['Status']),                     // 2: Status
    sanitize($_POST['CourseName']),                 // 2: CourseName
    sanitize($_POST['CourseShort'] ?? ''),          // 3: CourseShort
    '',                                             // 4: ItemCode (empty for new requests)
    $combinedtimes,                                 // 5: ClassTimes
    sanitize($_POST['ClassDays'] ?? ''),            // 6: ClassDays
    '',                                             // 7: ELM link (empty for new requests)
    sanitize($_POST['PreWork'] ?? ''),              // 8: PreWork
    sanitize($_POST['PostWork'] ?? ''),             // 9: PostWork
    sanitize($_POST['CourseOwner']),                // 10: CourseOwner
    '',                                             // 11: MinMax (legacy field, unused)
    sanitize($_POST['CourseNotes'] ?? ''),          // 12: CourseNotes
    sanitize($_POST['Requested']),                  // 13: Requested date
    sanitize($_POST['RequestedBy']),                // 14: RequestedBy
    sanitize($_POST['EffectiveDate']),              // 15: EffectiveDate
    sanitize($_POST['CourseDescription']),          // 16: CourseDescription
    sanitize($_POST['CourseAbstract'] ?? ''),       // 17: CourseAbstract
    sanitize($_POST['Prerequisites'] ?? ''),        // 18: Prerequisites
    sanitize($_POST['Keywords'] ?? ''),             // 19: Keywords
    '',                                             // 20: Category (legacy field)
    sanitize($_POST['Method']),                     // 21: Method
    sanitize($_POST['elearning'] ?? ''),            // 22: elearning URL
    $weship,                                        // 23: WeShip
    '',                                             // 24: ProjectNumber (empty for requests)
    '',                                             // 25: Responsibility (empty for requests)
    '',                                             // 26: ServiceLine (empty for requests)
    '',                                             // 27: STOB (empty for requests)
    sanitize($_POST['MinEnroll'] ?? ''),            // 28: MinEnroll
    sanitize($_POST['MaxEnroll'] ?? ''),            // 29: MaxEnroll
    sanitize($_POST['StartTime'] ?? ''),            // 30: StartTime
    sanitize($_POST['EndTime'] ?? ''),              // 31: EndTime
    '#F1F1F1',                                      // 32: CourseColor (default)
    0,                                              // 33: Featured (default false)
    sanitize($_POST['Developer'] ?? ''),            // 34: Developer
    '',                                             // 35: EvaluationsLink (empty for requests)
    sanitize($_POST['LearningHubPartner']),         // 36: LearningHubPartner
    $alchemer,                                      // 37: Alchemer
    sanitize($_POST['Topics'] ?? ''),               // 38: Topics
    sanitize($_POST['Audience'] ?? ''),             // 39: Audience
    sanitize($_POST['Levels'] ?? ''),               // 40: Levels
    sanitize($_POST['Reporting'] ?? ''),            // 41: Reporting
    '',                                             // 42: PathLAN (empty for requests)
    '',                                             // 43: PathStaging (empty for requests)
    '',                                             // 44: PathLive (empty for requests)
    '',                                             // 45: PathNIK (empty for requests)
    '',                                             // 46: CHEFSFormID (empty for requests)
    0,                                              // 47: isMoodle (default false)
    0,                                              // 48: TaxonomyProcessed (default false)
    '',                                             // 49: TaxonomyProcessedBy (empty)
    '',                                             // 50: ELMCourseID (empty for requests)
    $now,                                           // 51: LastModified
    sanitize($_POST['Platform']),                   // 52: Platform
    $hubInclude,                                    // 53: HUBInclude
    sanitize($_POST['RegistrationLink'] ?? ''),     // 54: RegistrationLink
    $slug,                                          // 55: Slug
    sanitize($_POST['HubExpirationDate'] ?? ''),    // 56: HubExpirationDate
    0,                                              // 57: OpenAccessOptin (default false)
    'yes',                                          // 58: HubIncludeSync (default yes)
    'no',                                           // 59: HubIncludePersist (default no)
    'This course is no longer available for registration.', // 60: HubPersistMessage (default)
    'active'                                        // 61: HubIncludePersistState (default active)
];

// Write course to CSV file
$fp = fopen('data/courses.csv', 'a+');
if($fp === false) {
    die('Error: Could not open courses.csv file for writing');
}

if(fputcsv($fp, $newcourse) === false) {
    fclose($fp);
    die('Error: Could not write course data to file');
}
fclose($fp);

// Send email notification for new course
try {
    $ches = new CHESClient();

    // Get partner name and contacts
    $partnersJson = file_get_contents('data/partners.json');
    $partnersData = json_decode($partnersJson, true);
    $partnerName = '';
    $partnerContacts = [];
    foreach($partnersData as $p) {
        if($p['id'] == $_POST['LearningHubPartner']) {
            $partnerName = $p['name'];
            if(!empty($p['contacts'])) {
                $partnerContacts = $p['contacts'];
            }
            break;
        }
    }

    // Collect email recipients
    $recipients = [];

    // Add partner admin contacts
    foreach($partnerContacts as $contact) {
        if(!empty($contact['email'])) {
            $recipients[] = $contact['email'];
        }
    }

    // Add course owner (steward)
    if(!empty($_POST['CourseOwner'])) {
        $owner = getPerson($_POST['CourseOwner']);
        if($owner && !empty($owner[3])) { // Email is at index 3
            $recipients[] = $owner[3];
        }
    }

    // Add developer if specified
    if(!empty($_POST['Developer'])) {
        $dev = getPerson($_POST['Developer']);
        if($dev && !empty($dev[3])) {
            $recipients[] = $dev[3];
        }
    }

    // Remove duplicates and empty values
    $recipients = array_unique(array_filter($recipients));

    // Only send if we have recipients
    if(!empty($recipients)) {
        // Build course URL
        $courseUrl = "https://gww.bcpublicservice.gov.bc.ca/lsapp/course.php?courseid=" . urlencode($courseid);
        $dashboardUrl = "https://gww.bcpublicservice.gov.bc.ca/lsapp/partners/dashboard.php";

        $subject = "New Course Created: " . sanitize($_POST['CourseName']);

        $bodyHtml = "<h2>New Course Created</h2>";
        $bodyHtml .= "<p>A new course has been created in the Learning Hub:</p>";
        $bodyHtml .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
        $bodyHtml .= "<tr><td><strong>Course Name:</strong></td><td>" . htmlspecialchars($_POST['CourseName']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Course ID:</strong></td><td>" . htmlspecialchars($courseid) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Partner:</strong></td><td>" . htmlspecialchars($partnerName) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Platform:</strong></td><td>" . htmlspecialchars($_POST['Platform']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Delivery Method:</strong></td><td>" . htmlspecialchars($_POST['Method']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Status:</strong></td><td>" . htmlspecialchars($_POST['Status']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Course Owner:</strong></td><td>" . htmlspecialchars($_POST['CourseOwner']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Effective Date:</strong></td><td>" . htmlspecialchars($_POST['EffectiveDate']) . "</td></tr>";
        $bodyHtml .= "<tr><td><strong>Created:</strong></td><td>" . htmlspecialchars($now) . "</td></tr>";
        $bodyHtml .= "</table>";
        $bodyHtml .= "<h3>Course Description:</h3>";
        $bodyHtml .= "<p>" . nl2br(htmlspecialchars($_POST['CourseDescription'])) . "</p>";
        $bodyHtml .= "<p><a href='" . htmlspecialchars($courseUrl) . "'>View Course</a> | ";
        $bodyHtml .= "<a href='" . htmlspecialchars($dashboardUrl) . "'>Partner Dashboard</a></p>";

        $bodyText = "New Course Created\n\n";
        $bodyText .= "Course Name: " . $_POST['CourseName'] . "\n";
        $bodyText .= "Course ID: " . $courseid . "\n";
        $bodyText .= "Partner: " . $partnerName . "\n";
        $bodyText .= "Platform: " . $_POST['Platform'] . "\n";
        $bodyText .= "Delivery Method: " . $_POST['Method'] . "\n";
        $bodyText .= "Status: " . $_POST['Status'] . "\n";
        $bodyText .= "Course Owner: " . $_POST['CourseOwner'] . "\n";
        $bodyText .= "Effective Date: " . $_POST['EffectiveDate'] . "\n";
        $bodyText .= "Created: " . $now . "\n\n";
        $bodyText .= "Course Description:\n" . $_POST['CourseDescription'] . "\n\n";
        $bodyText .= "View Course: " . $courseUrl . "\n";
        $bodyText .= "Partner Dashboard: " . $dashboardUrl . "\n";

        $result = $ches->sendEmail(
            $recipients,
            $subject,
            $bodyText,
            $bodyHtml,
            'learninghub_noreply@gov.bc.ca'
        );

        error_log("Sent course creation notification email to " . count($recipients) . " recipients (Transaction ID: {$result['txId']})");
    }

} catch (Exception $e) {
    error_log("ERROR: Failed to send course creation notification email: " . $e->getMessage());
}

// Add course people relationships if specified
if(!empty($_POST['CourseOwner']) || !empty($_POST['Developer'])) {
    $peoplefp = fopen('data/course-people.csv', 'a+');
    if($peoplefp === false) {
        // Course was created but people relationships failed - log this but continue
        error_log("Warning: Could not open course-people.csv for course ID: {$courseid}");
    } else {
        // Add steward relationship
        if(!empty($_POST['CourseOwner'])) {
            $stew = [$courseid, 'steward', sanitize($_POST['CourseOwner']), $now];
            fputcsv($peoplefp, $stew);
        }
        
        // Add developer relationship
        if(!empty($_POST['Developer'])) {
            $dev = [$courseid, 'dev', sanitize($_POST['Developer']), $now];
            fputcsv($peoplefp, $dev);
        }
        
        fclose($peoplefp);
    }
}

// Check if this is from partner portal
if (!empty($_POST['partner_redirect'])) {
    // Redirect back to partner portal dashboard using partner ID
    $partnerId = $_POST['LearningHubPartner'];
    header("Location: /learning/hub/partners/dashboard.php?partnerid={$partnerId}&message=CourseCreated");
} else {
    // Redirect to the new course page
    header("Location: /lsapp/course.php?courseid={$courseid}");
}
exit;