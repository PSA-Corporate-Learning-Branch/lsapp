<?php 
opcache_reset();
require('../../../lsapp/inc/lsapp.php');
$idir = LOGGED_IN_IDIR;
// $idir = 'kefnk';

$partnerslug = urldecode($_GET['partnerslug']) ?? '';
/** #TODO make this graceful */
if(empty($partnerslug)) {
  echo 'Please provide a partner.';
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
  if ($partner['name'] === $partnerslug) {
    $matched_partner = $partner;
    break;
  }
}

$access_denied = false;
if (is_null($matched_partner)) {
  // fallback to matching the full partners list just to get the display name
  foreach ($partners_data as $partner) {
    if ($partner['name'] === $partnerslug) {
      $matched_partner = $partner;
      break;
    }
  }
  $access_denied = true;
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
// $userreviews = getUserReviews($idir);
$partners = getPartners();
$platforms = getPlatformNames();

$courseData = [
  'LearningHubPartner' => '',
  'CourseOwner' => LOGGED_IN_IDIR,
  'CourseName' => '',
  'coursedesc' => '',
  'Method' => '',
  'Platform' => '',
  'RegistrationLink' => '',
  'Audience' => '',
  'Topic' => '',
  'Keywords' => '',
  'HubExpirationDate' => '',
  'Status' => 'Draft'
];

$partnercourses = [];
$elmcourses = [];
$nonelmcourses = [];
$inactivecourses = [];
$courseid = $_GET['courseid'] ?? 0;

    if (($handle = fopen("../../../lsapp/data/courses-new.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            
            if (isset($partnerslug) && $data[36] === $partnerslug) {
                if ($data[1] !== 'Active') {
                    $platform = $data[52] ?? 'Unknown';
                    if (!isset($inactivecourses[$platform])) {
                        $inactivecourses[$platform] = [];
                    }
                    $inactivecourses[$platform][] = $data;
                } elseif (!empty($data[4])) {
                    $elmcourses[] = $data;
                } else {
                    $platform = $data[52] ?? 'Unknown';
                    if (!isset($nonelmcourses[$platform])) {
                        $nonelmcourses[$platform] = [];
                    }
                    $nonelmcourses[$platform][] = $data;
                }
            }
            // Assuming courseid is in the first column
            if ($data[0] == $courseid) {
              $courseData = [
                  'LearningHubPartner' => $data[36] ?? '',
                  'HUBInclude' => $data[53] ?? '',
                  'CourseOwner' => $data[10] ?? $courseData['CourseOwner'],
                  'CourseID' => $data[0] ?? '',
                  'CourseName' => $data[2] ?? '',
                  'coursedesc' => $data[16] ?? '',
                  'Method' => $data[21] ?? '',
                  'Platform' => $data[52] ?? '',
                  'RegistrationLink' => $data[54] ?? '',
                  'Audience' => $data[39] ?? '',
                  'Topic' => $data[38] ?? '',
                  'Keywords' => $data[19] ?? '',
                  'HubExpirationDate' => $data[56] ?? '',
                  'Status' => $data[1] ?? 'Draft'
              ];
              continue;
          }

        }
        fclose($handle);
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
</style>
<div class="d-flex flex-column min-vh-100">

<div class="d-flex p-4 p-md-5 align-items-center bg-gov-green bg-gradient" style="height: 12vh; min-height: 100px;">
    <div class="container-lg py-4 py-md-5">
        <h1 class="text-white">Manage Courses for <?= htmlspecialchars($matched_partner['name']) ?></h1>
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
                <li><a href="course-form.php?partnerslug=<?= urlencode($user_partner['name']) ?>"><?= htmlspecialchars($user_partner['name']) ?></a></li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>
      <?php else: ?>
        <?php if (!empty($user_partners)): ?>
          <p>If you are looking to manage a course for a partner you are associated with, please select one of your partners below:</p>
          <ul>
            <?php foreach ($user_partners as $partner): ?>
              <li><a href="course-form.php?partnerslug=<?= urlencode($partner['name']) ?>"><?= htmlspecialchars($partner['name']) ?></a></li>
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
<div class="container-lg px-lg-5 px-4 pt-4 pb-2 bg-light-subtle">
  <div class="row mb-4">
    <div class="col-md-12">
      <h2 class="h4">Partner Contacts</h2>
      <?php foreach($matched_partner['contacts'] as $contact): ?>
        <div>
          <strong><?= htmlspecialchars($contact['name']) ?></strong>
          <?php if (!empty($contact['idir']) && $contact['idir'] === $idir): ?>
            <span class="badge bg-primary">You</span>
          <?php endif ?>
          <?php if (!empty($contact['email'])): ?>
            &mdash; <a href="mailto:<?= htmlspecialchars($contact['email']) ?>"><?= htmlspecialchars($contact['email']) ?></a>
          <?php endif ?>
        </div>
      <?php endforeach ?>
      <?php if (count($user_partners) > 1): ?>
          <div class="mt-3">
            <div class="dropdown">
              <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Other Partners
              </button>
              <ul class="dropdown-menu">
                <?php foreach($user_partners as $partner): ?>
                  <?php if ($partner['name'] === $matched_partner['name']) continue; ?>
                  <li>
                    <a class="dropdown-item" href="course-form.php?partnerslug=<?= urlencode($partner['name']) ?>">
                      <?= htmlspecialchars($partner['name']) ?>
                    </a>
                  </li>
                <?php endforeach ?>
              </ul>
            </div>
          </div>
        <?php endif ?>
    </div>
  </div>
</div>
<div class="container-lg p-lg-5 p-4 pt-lg-0 bg-light-subtle">
<div class="row">
<div class="col-md-6">

    <div class="mb-4">
      <a href="course-form.php?partnerslug=<?= urlencode($partnerslug) ?>&newcourse=1" class="btn btn-success">+ New Course</a>
    </div>

    <h3>Active Courses <span class="badge bg-primary"><?= array_sum(array_map('count', $nonelmcourses)) + count($elmcourses) ?></span></h3>
    <?php if (!empty($nonelmcourses)): ?>
      <?php foreach($nonelmcourses as $platform => $courses): ?>
        <h4><?= htmlspecialchars($platform) ?></h4>
        <ul class="list-group mb-4">
          <?php foreach($courses as $pc): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center<?php if (!empty($_GET['courseid']) && $_GET['courseid'] == $pc[0]) echo ' bg-secondary-subtle text-white'; ?>">
              <a href="course-form.php?partnerslug=<?= urlencode($pc[36]) ?>&courseid=<?= htmlspecialchars($pc[0]) ?>">
                <?= htmlspecialchars($pc[2]) ?>
              </a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endforeach ?>
    <?php endif ?>

    <h4>PSA Learning System</h4>
    <p>Courses in PSALS (a.k.a. ELM) are edited in PSALS. Click the course name to launch PSALS.</p>
    <?php if (!empty($elmcourses)): ?>
      <ul class="list-group mb-4">
        <?php foreach($elmcourses as $pc): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <a href="https://learning.gov.bc.ca/psp/CHIPSPLM/EMPLOYEE/ELM/c/LM_COURSESTRUCTURE.LM_CI_LA_CMP.GBL?LM_CI_ID=<?= htmlspecialchars($pc[50]) ?>"
                target="_blank">
              <?= htmlspecialchars($pc[2]) ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php else: ?>
      <p>No ELM courses found for this partner.</p>
    <?php endif ?>
      <hr class="my-5">
    <h3>Inactive Courses <span class="badge bg-primary"><?= array_sum(array_map('count', $inactivecourses)) ?></span></h3>
    <?php if (!empty($inactivecourses)): ?>
      <?php foreach($inactivecourses as $platform => $courses): ?>
        <h4><?= htmlspecialchars($platform) ?></h4>
        <ul class="list-group mb-4">
          <?php foreach($courses as $pc): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <a href="course-form.php?partnerslug=<?= urlencode($pc[36]) ?>&courseid=<?= htmlspecialchars($pc[0]) ?>">
                <?= htmlspecialchars($pc[2]) ?>
              </a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endforeach ?>
    <?php else: ?>
      <p>No inactive courses found for this partner.</p>
    <?php endif ?>

</div>
<?php if ($courseid || isset($_GET['newcourse'])): ?>
<div class="col-md-6">
<?php
if (isset($_GET['newcourse'])) {
  $courseData = [
    'LearningHubPartner' => $partnerslug,
    'CourseOwner' => LOGGED_IN_IDIR,
    'CourseID' => '',
    'CourseName' => '',
    'coursedesc' => '',
    'Method' => '',
    'Platform' => '',
    'RegistrationLink' => '',
    'Audience' => '',
    'Topic' => '',
    'Keywords' => '',
    'HubExpirationDate' => '',
    'Status' => 'Draft'
  ];
}
?>
<form method="post" action="#"> <!--course-process.php-->
<input type="hidden" name="update" id="update" value="yes">
<input type="hidden" name="CourseID" id="CourseID" value="<?= $courseData['CourseID'] ?>">

<input type="hidden" name="user_idir" id="user_idir" value="ahaggett">
<input type="hidden" id="LearningHubPartner" name="LearningHubPartner" value="<?= $courseData['LearningHubPartner'] ?>">
<div class="row">
<div class="col-md-12">

<?php if($courseData['HUBInclude'] === 'yes' || $courseData['HUBInclude'] === 1): ?>
<div class="alert alert-success">Course is live in the catalog</div>
<?php else: ?>
<div class="alert alert-warning">Course is NOT live in the catalog</div>
<?php endif ?>

<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="Status">Status</label>
    <select id="Status" name="Status" class="form-select d-inline" id="">
        <option value="Draft">Draft</option>
        <option value="Requested">Request</option>
        <!-- <option value="Schedule">Schedule</option> -->
    </select>
</div>



<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="CourseName">Course Name</label>
    <p>Please adhere to <a href="https://learningcentre.gww.gov.bc.ca/learning-development-tips/">corporate standards</a> for naming conventions.</p>
    <input id="CourseName" name="CourseName" class="form-control form-control-lg" type="text" placeholder="Enter course name" required value="<?= $courseData['CourseName'] ?>">
</div>


<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <div><label class="fw-bold" for="coursedesc">Course Description</label></div>
    <div id="toolbar" class="btn-group">
        <button class="btn btn-sm btn-dark" type="button" onclick="applyMarkdown('**', '**')"><strong>Bold</strong></button>
        <!-- <button class="btn btn-sm btn-dark" type="button" onclick="applyMarkdown('_', '_')">Italic</button> -->
        <button class="btn btn-sm btn-dark" type="button" onclick="applyLink()">Link</button>
        <button class="btn btn-sm btn-dark" type="button" onclick="applyList('unordered')">Unordered List</button>
        <button class="btn btn-sm btn-dark" type="button" onclick="applyList('ordered')">Ordered List</button>
    </div>
    <textarea rows="8" id="coursedesc" name="coursedesc" class="form-control" required><?= $courseData['coursedesc'] ?></textarea>
</div>


<div class="row">
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Method">Delivery Method</label>
        <p>How is the learning offered?</p>
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
        <p>Where do you register?</p>
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
    <label class="fw-bold" for="elearning">Registration Link</label>
    <p>Where the "Launch" button on the course listing goes to.</p>
    <input id="RegistrationLink" name="RegistrationLink" class="form-control" type="text" placeholder="https://..." required value="<?= $courseData['RegistrationLink'] ?>">
</div>

<div class="row">
<div class="col-md-6">
    <div class="p-3 mb-2 bg-secondary-subtle rounded-3">
        <label class="fw-bold" for="Audience">Audience</label>
        <p>Who is the learning for?</p>
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
        <p>What is the learning about?</p>
        <select id="Topic" name="Topic" class="form-select" required>
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
    <p>Any words that are not contained within the title, description, or topic which learner might use to search for 
        and expect to find this course. There's no limit to the number of keywords you can include, but please refrain
        from "keyword stuffing." These keywords are not displayed to the learner.</p>
    <input id="Keywords" name="Keywords" class="form-control" type="text" placeholder="Comma separated values" value="<?= $courseData['Keywords'] ?>">
</div>
<div class="p-3 mb-2 bg-secondary-subtle rounded-3">
    <label class="fw-bold" for="HubExpirationDate">Expiration date</label>
    <p>After this date, this course will be removed from the search results and its page will no 
        longer show a "Launch" button.</p>
    <input id="HubExpirationDate" name="HubExpirationDate" class="form-control" type="date" value="<?= $courseData['HubExpirationDate'] ?>">
</div>


<button class="my-5 btn btn-lg d-block btn-primary">Add Course</button>

</div>
<?php endif ?>
</div>
</div>

</form>

</div>
</div>
</div>

<script>
function applyMarkdown(before, after) {
  const textarea = document.getElementById("coursedesc");
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selectedText = textarea.value.slice(start, end);

  // Apply Markdown symbols
  const newText = before + selectedText + after;
  textarea.setRangeText(newText);

  // Re-select the newly formatted text
  textarea.setSelectionRange(start + before.length, end + before.length);
  textarea.focus();
}

function applyLink() {
  const textarea = document.getElementById("coursedesc");
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selectedText = textarea.value.slice(start, end);

  // Default link format
  const newText = `[${selectedText || 'text'}](https://example.com)`;
  textarea.setRangeText(newText);

  // Re-select the text to allow easy editing of the link
  if (!selectedText) {
    textarea.setSelectionRange(start + 1, start + 5); // Select 'text'
  } else {
    textarea.setSelectionRange(start + newText.length - 19, start + newText.length - 1); // Select 'https://example.com'
  }
  textarea.focus();
}

function applyList(type) {
  const textarea = document.getElementById("coursedesc");
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selectedText = textarea.value.slice(start, end);

  // Split selected text by lines
  const lines = selectedText.split('\n');
  
  // Prefix each line with list markdown symbols
  const newLines = lines.map((line, index) => {
    if (type === 'unordered') {
      return `* ${line}`;
    } else if (type === 'ordered') {
      return `${index + 1}. ${line}`;
    }
    return line;
  });
  
  const newText = newLines.join('\n');
  textarea.setRangeText(newText);

  // Re-select the newly formatted text
  textarea.setSelectionRange(start, start + newText.length);
  textarea.focus();
}
</script>
</div>

<?php require('../templates/footer.php') ?>
</div>