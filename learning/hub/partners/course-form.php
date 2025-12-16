<?php 
opcache_reset();
require('../../../lsapp/inc/lsapp.php');
$idir = LOGGED_IN_IDIR;
// $idir = 'kefnk';

$partnerid = $_GET['partnerid'] ?? '';
/** #TODO make this graceful */
if(empty($partnerid)) {
  echo 'Please provide a partner ID.';
  exit;
}
$partners_file = '../../../lsapp/data/partners.json';
$user_partners = [];

if (file_exists($partners_file)) {
    $partners_json = file_get_contents($partners_file);
    $partners_data = json_decode($partners_json, true);

    foreach ($partners_data as $partner) {
        if (!empty($partner['contacts']) && is_array($partner['contacts'])) {
            foreach ($partner['contacts'] as $contact) {
                if (!empty($contact['idir']) && $contact['idir'] === $idir) {
                    $user_partners[] = $partner;
                    break; // Stop checking this partner after a match
                }
            }
        }
    }
}

// Check if the current partner matches any of the user's partner list
$matched_partner = null;
foreach ($user_partners as $partner) {
  if ($partner['id'] == $partnerid) {
    $matched_partner = $partner;
    break;
  }
}

$access_denied = false;
if (is_null($matched_partner)) {
  // fallback to matching the full partners list just to get the display name
  foreach ($partners_data as $partner) {
    if ($partner['id'] == $partnerid) {
      $matched_partner = $partner;
      break;
    }
  }
  // Check if user has general app access via canAccess()
  if (!canAccess()) {
    $access_denied = true;
  }
}

$pendingRequestExists = false;
$requestsFile = "../../../lsapp/data/partner_contact_requests.json";
if (file_exists($requestsFile)) {
    $allRequests = json_decode(file_get_contents($requestsFile), true) ?? [];
    foreach ($allRequests as $r) {
        if ($r['partner_slug'] === $partner['slug'] && $r['idir'] === LOGGED_IN_IDIR) {
            $pendingRequestExists = true;
            break;
        }
    }
}

$topics = getAllTopics();
$audience = getAllAudiences();
$deliverymethods = getDeliveryMethods ();
$levels = getLevels ();
$reportinglist = getReportingList();
$partners = getPartners();
$platforms = getPlatformNames();

$courseData = [
  'LearningHubPartner' => '',
  'CourseOwner' => LOGGED_IN_IDIR,
  'CourseName' => '',
  'CourseDescription' => '',
  'Method' => '',
  'Platform' => '',
  'RegistrationLink' => '',
  'Audience' => '',
  'Topic' => '',
  'Keywords' => '',
  'HubExpirationDate' => '',
  'Status' => 'Draft',
  'HUBInclude' => '0',
  'HubIncludePersist' => 'no',
  'HubPersistMessage' => 'This course is no longer available for registration.'
];

$courseid = $_GET['courseid'] ?? 0;

if ($courseid) {
    if (($handle = fopen("../../../lsapp/data/courses.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($data[0] == $courseid) {
              $courseData = [
                  'LearningHubPartner' => $data[36] ?? '',
                  'HUBInclude' => $data[53] ?? '',
                  'CourseOwner' => $data[10] ?? $courseData['CourseOwner'],
                  'CourseID' => $data[0] ?? '',
                  'CourseName' => $data[2] ?? '',
                  'CourseDescription' => $data[16] ?? '',
                  'Method' => $data[21] ?? '',
                  'Platform' => $data[52] ?? '',
                  'RegistrationLink' => $data[54] ?? '',
                  'Audience' => $data[39] ?? '',
                  'Topic' => $data[38] ?? '',
                  'Keywords' => $data[19] ?? '',
                  'HubExpirationDate' => $data[56] ?? '',
                  'Status' => $data[1] ?? 'Draft',
                  'HubIncludePersist' => $data[59] ?? 'no',
                  'HubPersistMessage' => $data[60] ?? 'This course is no longer available for registration.'
              ];
              break;
          }
        }
        fclose($handle);
    }
}


$statuses = ['Draft','Request','Active','Expired'];
?>

<?php require('../templates/header.php') ?>
<style>
a {
  text-decoration: none;
}
a:hover {
  text-decoration: underline;
}
/* EasyMDE custom styles to match Bootstrap theme */
.EasyMDEContainer .CodeMirror {
  border: 1px solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
}
.EasyMDEContainer .editor-toolbar {
  border: 1px solid var(--bs-border-color);
  border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0;
  background-color: var(--bs-secondary-bg);
}
.EasyMDEContainer .editor-toolbar button {
  color: var(--bs-body-color) !important;
}
.EasyMDEContainer .editor-toolbar button:hover {
  background-color: var(--bs-secondary-bg);
  border-color: var(--bs-border-color);
}
.EasyMDEContainer .editor-toolbar button.active {
  background-color: var(--bs-primary);
  color: white !important;
}
.EasyMDEContainer .CodeMirror-fullscreen {
  background-color: var(--bs-body-bg);
}
.editor-preview {
  background-color: var(--bs-body-bg);
  color: var(--bs-body-color);
}
.editor-preview-side {
  border-left: 1px solid var(--bs-border-color);
}
</style>
<!-- EasyMDE CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<div class="d-flex flex-column min-vh-100">

<div class="d-flex p-4 p-md-5 align-items-center bg-gov-green bg-gradient" style="height: 12vh; min-height: 100px;">
    <div class="container-lg py-4 py-md-5">
        <h1 class="text-white"><?= isset($_GET['newcourse']) ? 'New Course' : 'Edit Course' ?> for <?= htmlspecialchars($matched_partner['name']) ?></h1>
    </div>
</div>
<?php if ($access_denied): ?>
<div class="container-lg px-lg-5 px-4 pt-4 pb-2 bg-light-subtle">
  <div class="row mb-4">
    <div class="col-md-12">
      <h2 class="h4">Access Restricted</h2>
      <p>You are not listed as a contact for this partner, so you are unable to manage their courses here.</p>
      <?php if ($pendingRequestExists): ?>
          <div class="alert alert-info mt-4">Thank you, we've received your request and will contact you as soon as possible.</div>
          <?php if (!empty($user_partners)): ?>
            <p class="mt-4">If you are looking to manage a course for a partner you are associated with, please select one of your partners below:</p>
            <ul>
              <?php foreach ($user_partners as $user_partner): ?>
                <li><a href="dashboard.php?partnerid=<?= $user_partner['id'] ?>"><?= htmlspecialchars($user_partner['name']) ?></a></li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>
      <?php else: ?>
        <?php if (!empty($user_partners)): ?>
          <p>If you are looking to manage a course for a partner you are associated with, please select one of your partners below:</p>
          <ul>
            <?php foreach ($user_partners as $partner): ?>
              <li><a href="dashboard.php?partnerid=<?= $partner['id'] ?>"><?= htmlspecialchars($partner['name']) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>

        <div class="card">
          <div class="card-header fw-semibold">Request access to this partner</div>
          <div class="card-body">
            <form action="contact-request.php" method="POST">
                <input type="hidden" name="partner_slug" value="<?= htmlspecialchars($matched_partner['slug']) ?>">
                <input type="hidden" name="partner_name" value="<?= htmlspecialchars($matched_partner['name']) ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">IDIR</label>
                    <input type="text" name="idir" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" name="role" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require('../templates/footer.php'); exit; ?>
<?php endif ?>

<div class="container-lg p-lg-5 p-4 bg-light-subtle">
<div class="row">
<div class="col-md-12">

    <div class="mb-4">
      <a href="dashboard.php?partnerid=<?= $partnerid ?>" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

<?php if ($courseid || isset($_GET['newcourse'])): ?>
<?php
if (isset($_GET['newcourse'])) {
  // Use the partner ID instead of the slug/name
  $partnerId = $matched_partner ? $matched_partner['id'] : '';
  $courseData = [
    'LearningHubPartner' => $partnerId,
    'CourseOwner' => LOGGED_IN_IDIR,
    'CourseID' => '',
    'CourseName' => '',
    'CourseDescription' => '',
    'Method' => '',
    'Platform' => '',
    'RegistrationLink' => '',
    'Audience' => '',
    'Topic' => '',
    'Keywords' => '',
    'HubExpirationDate' => '',
    'Status' => 'Draft',
    'HUBInclude' => '0',
    'HubIncludePersist' => 'no',
    'HubPersistMessage' => 'This course is no longer available for registration.'
  ];
}

// Determine form action based on whether it's a new course or update
$formAction = isset($_GET['newcourse']) ? '../../../lsapp/course-create.php' : '../../../lsapp/course-update.php';
?>
<form method="post" action="<?= htmlspecialchars($formAction) ?>">
<input type="hidden" name="update" id="update" value="yes">
<input type="hidden" name="CourseID" id="CourseID" value="<?= $courseData['CourseID'] ?>">
<?php if (!isset($_GET['newcourse']) && $courseData['Status'] !== 'Draft'): ?>
<input type="hidden" name="Status" value="<?= $courseData['Status'] ?>">
<?php endif ?>

<input type="hidden" name="user_idir" value="<?= LOGGED_IN_IDIR ?>">
<?php 
// Ensure we're sending the partner ID, not the name
$partnerValue = $courseData['LearningHubPartner'];
// If it's not numeric, it might be a partner name - convert to ID
if (!is_numeric($partnerValue) && $matched_partner) {
    $partnerValue = $matched_partner['id'];
}
?>
<input type="hidden" name="LearningHubPartner" value="<?= $partnerValue ?>">
<input type="hidden" name="RequestedBy" value="<?= LOGGED_IN_IDIR ?>">
<input type="hidden" name="Requested" value="<?= date('Y-m-d') ?>">
<input type="hidden" name="EffectiveDate" value="<?= date('Y-m-d') ?>">
<input type="hidden" name="CourseOwner" value="<?= $courseData['CourseOwner'] ?? LOGGED_IN_IDIR ?>">
<input type="hidden" name="partner_redirect" value="1">
<div class="row">
<div class="col-md-12">

<?php 
$isHubIncluded = ($courseData['HUBInclude'] === 'yes' || $courseData['HUBInclude'] === 'Yes' || $courseData['HUBInclude'] == 1);
$isPersistent = ($courseData['HubIncludePersist'] === 'yes' || $courseData['HubIncludePersist'] === 'Yes');
?>
<?php if($_GET['message'] === 'Updated'): ?>
<div class="alert alert-success mb-1">Course is updated.</div>
<?php elseif($_GET['message'] === 'Created'): ?>
<div class="alert alert-success mb-1">Course is created.</div>
<?php endif ?>
<?php if($courseData['Status'] === 'Requested' || $courseData['Status'] === 'Request'): ?>
    <?php if($isHubIncluded): ?>
    <div class="alert alert-info mb-2">Course is <?= $courseData['Status'] ?> and will be live in the catalog when it's made active</div>
    <?php else: ?>
    <div class="alert alert-warning mb-2">Course is <?= $courseData['Status'] ?> and will NOT be live in the catalog when it's made active</div>
    <?php endif ?>
<?php elseif($courseData['Status'] === 'Draft'): ?>
    <div class="alert alert-info mb-2">Course is <?= $courseData['Status'] ?> and needs to be submitted for review before it gets made active</div>
<?php elseif($courseData['Status'] === 'Active'): ?>
    <?php if($isHubIncluded): ?>
    <div class="alert alert-success mb-2">Course is <?= $courseData['Status'] ?> and live in the catalog</div>
    <?php else: ?>
    <div class="alert alert-warning mb-2">Course is <?= $courseData['Status'] ?> and NOT live in the catalog</div>
    <?php endif ?>
<?php else: ?>
    <?php if($isHubIncluded): ?>
    <div class="alert alert-secondary mb-2">Course is <?= $courseData['Status'] ?> and will be live in the catalog when it's made active</div>
    <?php else: ?>
    <div class="alert alert-warning mb-2">Course is <?= $courseData['Status'] ?> and NOT live in the catalog</div>
    <?php endif ?>
<?php endif ?>
<?php if (!isset($_GET['newcourse']) && $courseData['Status'] !== 'Draft'): ?>
<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="HUBInclude" name="HUBInclude" value="1"
               <?= $isHubIncluded ? 'checked' : '' ?>>
        <label class="form-check-label fw-bold" for="HUBInclude">
            Include in Learning Hub Catalog
        </label>
    </div>
    <p class="text-muted small mb-0">When checked, this course will appear in the Learning Hub catalog and be searchable by learners.</p>
</div>
<?php else: ?>
<input type="hidden" name="HUBInclude" id="HUBInclude" value="1">
<?php endif; ?>

<?php if (isset($_GET['newcourse']) || $courseData['Status'] === 'Draft'): ?>
<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold">Status</label>
    <div class="mt-2">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="Status" id="StatusDraft" value="Draft" 
                   <?= ($courseData['Status'] === 'Draft') ? 'checked' : '' ?>>
            <label class="form-check-label" for="StatusDraft">
                <strong>Draft</strong>
                <small class="d-block text-muted">Keep working on this course privately</small>
            </label>
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="radio" name="Status" id="StatusRequest" value="Request" 
                   <?= ($courseData['Status'] === 'Request' || $courseData['Status'] === 'Requested') ? 'checked' : '' ?>>
            <label class="form-check-label" for="StatusRequest">
                <strong>Request</strong>
                <small class="d-block text-muted">Submit this course for review and approval</small>
            </label>
        </div>
    </div>
    <p class="text-muted small mt-2">Choose whether to keep this course as a draft or request it for review.</p>
</div>
<?php endif ?>




<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="CourseName">Course Name</label>
    <p>Please adhere to <a href="https://learningcentre.gww.gov.bc.ca/learning-development-tips/">corporate standards</a> for naming conventions.</p>
    <input id="CourseName" name="CourseName" class="form-control form-control-lg" type="text" placeholder="Enter course name" required value="<?= $courseData['CourseName'] ?>">
</div>


<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <div><label class="fw-bold" for="CourseDescription">Course Description</label></div>
    <p class="text-muted small">Use <a href="https://www.markdownguide.org/" target="_blank">Markdown</a> formatting for rich text. The editor provides a live preview and all standard markdown features.</p>
    <textarea rows="8" id="CourseDescription" name="CourseDescription" class="form-control" required><?= $courseData['CourseDescription'] ?></textarea>
</div>


<div class="row">
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Method">Delivery Method</label>
        <p class="text-muted small">How is the learning offered?</p>
        <select id="Method" name="Method" class="form-select" required>
        <?php foreach($deliverymethods as $dm): ?>
        <?php if($dm == $courseData['Method']): ?>
        <option selected><?= $dm ?></option>
        <?php else: ?>
        <option><?= $dm ?></option>
        <?php endif ?>
        <?php endforeach ?>
        </select>
    </div>
</div>
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Platform">Platform</label>
        <p class="text-muted small">Where do you register?</p>
        <select id="Platform" name="Platform" class="form-select" required>
        <?php foreach($platforms as $pl): ?>
        <?php if($pl == $courseData['Platform']): ?>
        <option selected><?= $pl ?></option>
        <?php else: ?>
        <option><?= $pl ?></option>
        <?php endif ?>
        <?php endforeach ?>
        </select>
    </div>
</div>
</div>


<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="RegistrationLink">Registration Link</label>
    <p class="text-muted small">Where the "Launch" button on the course listing goes to.</p>
    <input id="RegistrationLink" name="RegistrationLink" class="form-control" type="text" placeholder="https://..." required value="<?= $courseData['RegistrationLink'] ?>">
</div>

<div class="row">
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Audience">Audience</label>
        <p class="text-muted small">Who is the learning for?</p>
        <select id="Audience" name="Audience" class="form-select" required>
        <?php foreach($audience as $a): ?>
        <?php if($a == $courseData['Audience']): ?>
        <option selected><?= $a ?></option>
        <?php else: ?>
        <option><?= $a ?></option>
        <?php endif ?>
        <?php endforeach ?>
        </select>
    </div>
</div>
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Topic">Topic</label>
        <p class="text-muted small">What is the learning about?</p>
        <select id="Topics" name="Topics" class="form-select" required>
        <?php foreach($topics as $t): ?>
        <?php if($t == $courseData['Topic']): ?>
        <option selected><?= $t ?></option>
        <?php else: ?>
        <option><?= $t ?></option>
        <?php endif ?>
        <?php endforeach ?>
        </select>
    </div>
</div>
</div>
<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="Keywords">Keywords</label>
    <p class="text-muted small">Any words that are not contained within the title, description, or topic which learner might use to search for 
        and expect to find this course. There's no limit to the number of keywords you can include, but please refrain
        from "keyword stuffing." These keywords are not displayed to the learner.</p>
    <input id="Keywords" name="Keywords" class="form-control" type="text" placeholder="Comma separated values" value="<?= $courseData['Keywords'] ?>">
</div>
<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="HubExpirationDate">Expiration date</label>
    <p class="text-muted small">After this date, this course will be removed from the search results entirely and its page will no longer show a "Launch" button. You can choose to persist the course below so that it remains in the search and shows a custom message instead of being removed entirely. </p>
    <input id="HubExpirationDate" name="HubExpirationDate" class="form-control" type="date" value="<?= $courseData['HubExpirationDate'] ?>">
</div>

<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="HubIncludePersist" name="HubIncludePersist" value="yes"
               <?= $isPersistent ? 'checked' : '' ?> 
               onchange="togglePersistMessage()">
        <label class="form-check-label fw-bold" for="HubIncludePersist">
            Make this a Persistent Course
        </label>
    </div>
    <p class="text-muted small">Persistent courses remain in the catalog even after the expiration date has passed, displaying a custom message to learners.</p>
    
    <div id="persistMessageContainer" style="<?= $isPersistent ? '' : 'display: none;' ?>">
        <label class="fw-bold mt-3" for="HubPersistMessage">Message for Unavailable Course</label>
        <p class="text-muted small">This message will be displayed when the course has no active offerings.</p>
        <textarea id="HubPersistMessage" name="HubPersistMessage" class="form-control" rows="3"><?= htmlspecialchars($courseData['HubPersistMessage'] ?? 'This course is no longer available for registration.') ?></textarea>
    </div>
</div>


<button class="my-5 btn btn-lg d-block btn-primary"><?= isset($_GET['newcourse']) ? 'Add Course' : 'Update Course' ?></button>

<?php else: ?>
<div class="alert alert-warning">Please select a course from the dashboard or create a new one.</div>
<?php endif ?>

</div>
</div>
</div>

</form>

</div>
</div>
</div>

<script>
function togglePersistMessage() {
    const checkbox = document.getElementById('HubIncludePersist');
    const messageContainer = document.getElementById('persistMessageContainer');
    
    if (checkbox.checked) {
        messageContainer.style.display = 'block';
    } else {
        messageContainer.style.display = 'none';
    }
}
</script>
</div>

<!-- EasyMDE JS -->
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
// Check if this is a new course
var isNewCourse = <?= isset($_GET['newcourse']) ? 'true' : 'false' ?>;

// Initialize EasyMDE on the CourseDescription textarea
var easyMDE = new EasyMDE({
    element: document.getElementById("CourseDescription"),
    spellChecker: false,
    autosave: {
        enabled: !isNewCourse, // Disable autosave for new courses
        uniqueId: "CourseDescription_<?= $courseid ?>",
        delay: 1000,
    },
    toolbar: [
        "bold", "italic", "heading", "|",
        "quote", "unordered-list", "ordered-list", "|",
        "link", "image", "|",
        "preview", "side-by-side", "fullscreen", "|",
        "guide"
    ],
    status: ["autosave", "lines", "words", "cursor"],
    previewRender: function(plainText) {
        // You can customize the preview rendering here if needed
        return this.parent.markdown(plainText);
    },
    promptURLs: true,
    placeholder: "Enter course description using Markdown formatting...",
});

// Fix the form submission issue by removing the required attribute from the hidden textarea
// and adding custom validation
var form = document.querySelector('form');
var originalTextarea = document.getElementById('CourseDescription');

// Remove required attribute from the original textarea since EasyMDE hides it
originalTextarea.removeAttribute('required');

// Add form submit handler to validate the content
form.addEventListener('submit', function(e) {
    var content = easyMDE.value();
    if (!content || content.trim() === '') {
        e.preventDefault();
        alert('Please enter a course description.');
        return false;
    }
});
</script>

<?php require('../templates/footer.php') ?>
</div>