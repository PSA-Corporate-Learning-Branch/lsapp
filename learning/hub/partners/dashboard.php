<?php 
opcache_reset();
require('../../../lsapp/inc/lsapp.php');
$idir = LOGGED_IN_IDIR;

$partnerid = urldecode($_GET['partnerid']) ?? '';
if(empty($partnerid)) {
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
                    break;
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

$partnercourses = [];
$elmcourses = [];
$nonelmcourses = [];
$inactivecourses = [];
$requestedcourses = [];
$draftcourses = [];

if (($handle = fopen("../../../lsapp/data/courses.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        
        if (isset($partnerid) && $data[36] == $partnerid) {
            if ($data[1] === 'Draft') {
                $platform = $data[52] ?? 'Unknown';
                if (!isset($draftcourses[$platform])) {
                    $draftcourses[$platform] = [];
                }
                $draftcourses[$platform][] = $data;
            } elseif ($data[1] === 'Request' || $data[1] === 'Requested') {
                $platform = $data[52] ?? 'Unknown';
                if (!isset($requestedcourses[$platform])) {
                    $requestedcourses[$platform] = [];
                }
                $requestedcourses[$platform][] = $data;
            } elseif ($data[1] !== 'Active') {
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
    }
    fclose($handle);
}

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
          <div class="alert alert-info mt-4">Thank you, we've received your request(s) and will contact you as soon as possible.</div>
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
                <input type="hidden" name="partner_id" value="<?= htmlspecialchars($matched_partner['id']) ?>">
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
    <div class="col-md-4">
      <?php if (count($user_partners) > 1): ?>
          <div class="mb-2">
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Your other partners
              </button>
              <ul class="dropdown-menu">
                <?php foreach($user_partners as $partner): ?>
                  <?php if ($partner['name'] === $matched_partner['name']) continue; ?>
                  <li>
                    <a class="dropdown-item" href="dashboard.php?partnerid=<?= $partner['id'] ?>">
                      <?= htmlspecialchars($partner['name']) ?>
                    </a>
                  </li>
                <?php endforeach ?>
              </ul>
            </div>
          </div>
        <?php endif ?>

      <h2 class="h4">Partner Contacts</h2>
      
      <?php if(isset($_GET['message']) && $_GET['message'] === 'ContactRetired'): ?>
        <div class="alert alert-success alert-sm mb-3">Contact has been retired successfully.</div>
      <?php endif ?>
      
      <?php if (!empty($matched_partner['contacts'])): ?>
        <ul class="list-group mb-3">
          <?php foreach($matched_partner['contacts'] as $contactIndex => $contact): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start">
              <div class="me-auto">
                
                <?php if (!empty($contact['email']) && $contact['email'] !== 'unknown@gov.bc.ca'): ?>
                  <div class="fw-bold">
                    <a href="mailto:<?= htmlspecialchars($contact['email']) ?>">
                      <?= htmlspecialchars($contact['name']) ?>
                    </a>
                  </div>
                  <?php else: ?>
                    <div class="fw-bold"><?= htmlspecialchars($contact['name']) ?></div>
                <?php endif ?>
                <?php if (!empty($contact['idir']) && $contact['idir'] === $idir): ?>
                  <span class="badge bg-dark-subtle">You</span>
                <?php endif ?>
                <?php if (!empty($contact['title'])): ?>
                  <small class="text-muted"><?= htmlspecialchars($contact['title']) ?></small><br>
                <?php endif ?>
              </div>
              <div class="d-flex flex-column">
                <?php if (canAccess()): ?>
                  <form method="POST" action="retire-contact.php" style="display: inline;" 
                        onsubmit="return confirm('Are you sure you want to retire <?= htmlspecialchars($contact['name']) ?> as a contact for this partner?\n\nThis will move them to the contact history and they will no longer have access to manage courses.')">
                    <input type="hidden" name="partner_id" value="<?= htmlspecialchars($partnerid) ?>">
                    <input type="hidden" name="contact_email" value="<?= htmlspecialchars($contact['email']) ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Retire this contact">
                      <i class="bi bi-person-x"></i>
                    </button>
                  </form>
                <?php endif ?>
              </div>
            </li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <div class="alert alert-info">No active contacts for this partner.</div>
      <?php endif ?>
      
      
      <h2 class="h4">Welcome</h2>
      <p>As an administrator for <?= htmlspecialchars($matched_partner['name']) ?>, you can manage the courses here. Please use the list to the right to choose a course to manage.</p>
      <details>
        <summary>Read more</summary>
          <p>You can <a href="course-form.php?partnerid=<?= $partnerid ?>&newcourse=1">add a new course</a>. By default, new courses need to be reviewed before being published. You can create a course as a draft and update it until you have your details right. When you're ready, you can choose to request the course be reviewed. Our reviewers will strive to process new course requests into the system as soon as is possible.</p>
          <p>Once your new course has been reviewed and made active, it will then be published to the LearningHUB during the next sync process. While new courses usually sync within a few hours, it may take 24-48 hours before the course will appear in the catalog.</p>
          <p>At this point, you can make subsequent updates to your course without needing a review.</p>
      </details>
      
      
    </div>
    
    <div class="col-md-8">

    <?php if(isset($_GET['message'])): ?>
        <?php if($_GET['message'] === 'CourseCreated'): ?>
            <div class="alert alert-success mb-3">Course has been created successfully.</div>
        <?php elseif($_GET['message'] === 'CourseUpdated'): ?>
            <div class="alert alert-success mb-3">Course has been updated successfully.</div>
        <?php endif ?>
    <?php endif ?>

    <div class="mb-4 float-end">
      <a href="course-form.php?partnerid=<?= $partnerid ?>&newcourse=1" class="btn btn-success">+ New Course</a>
    </div>

    <?php if (!empty($draftcourses)): ?>
    <h3>Draft Courses <span class="badge bg-secondary"><?= array_sum(array_map('count', $draftcourses)) ?></span></h3>
    <?php foreach($draftcourses as $platform => $courses): ?>
      <h4><?= htmlspecialchars($platform) ?></h4>
      <ul class="list-group mb-4">
        <?php foreach($courses as $pc): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($pc[2]) ?></span>
            <div>
              <a href="course-form.php?partnerid=<?= $pc[36] ?>&courseid=<?= htmlspecialchars($pc[0]) ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
              <a href="https://corporatelearning.gww.gov.bc.ca/learninghub/course/<?= htmlspecialchars($pc[55]) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endforeach ?>
    <hr class="my-4">
    <?php endif ?>

    <?php if (!empty($requestedcourses)): ?>
    <h3>Requested Courses <span class="badge bg-warning"><?= array_sum(array_map('count', $requestedcourses)) ?></span></h3>
    <?php foreach($requestedcourses as $platform => $courses): ?>
      <h4><?= htmlspecialchars($platform) ?></h4>
      <ul class="list-group mb-4">
        <?php foreach($courses as $pc): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($pc[2]) ?></span>
            <div>
              <a href="course-form.php?partnerid=<?= $pc[36] ?>&courseid=<?= htmlspecialchars($pc[0]) ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
              <a href="https://corporatelearning.gww.gov.bc.ca/learninghub/course/<?= htmlspecialchars($pc[55]) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endforeach ?>
    <hr class="my-4">
    <?php endif ?>

    <h3>Active Courses <span class="badge bg-primary"><?= array_sum(array_map('count', $nonelmcourses)) + count($elmcourses) ?></span></h3>
    <?php if (!empty($nonelmcourses)): ?>
      <?php foreach($nonelmcourses as $platform => $courses): ?>
        <h4><?= htmlspecialchars($platform) ?></h4>
        <ul class="list-group mb-4">
          <?php foreach($courses as $pc): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <a href="course-form.php?partnerid=<?= $pc[36] ?>&courseid=<?= htmlspecialchars($pc[0]) ?>">
                <?= htmlspecialchars($pc[2]) ?>
              </a>
              <div>
                <?php 
                $hubInclude = trim($pc[53] ?? '');
                $hubIncludePersist = trim($pc[59] ?? '');
                $hubPersistMessage = trim($pc[60] ?? '');
                $hubPersistState = trim($pc[61] ?? '');
                
                // Show persistent badge first if applicable
                if (strtolower($hubIncludePersist) === 'yes'):
                  $persistTitle = 'Persistent course';
                  if (!empty($hubPersistMessage)) {
                    $persistTitle .= ': ' . htmlspecialchars($hubPersistMessage);
                  }
                  $persistClass = (strtolower($hubPersistState) === 'inactive') ? 'bg-warning' : 'bg-info';
                ?>
                  <span class="badge <?= $persistClass ?> me-1" title="<?= $persistTitle ?>">Persistent</span>
                <?php endif; ?>
                
                <?php if (strtolower($hubInclude) === 'yes'): ?>
                  <span class="badge bg-success" title="Included in LearningHUB catalog">Learning<b>HUB</b></span>
                <?php elseif (strtolower($hubInclude) === 'no'): ?>
                  <span class="badge bg-secondary" title="Not included in LearningHUB catalog">No Learning<b>HUB</b></span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endforeach ?>
    <?php endif ?>

    <h4>PSA Learning System</h4>
    <p>Courses in the PSA Learning System (PSALS a.k.a. ELM) are edited in PSALS. Choosing a course will launch PSALS.</p>
    <?php if (!empty($elmcourses)): ?>
      <ul class="list-group mb-4">
        <?php foreach($elmcourses as $pc): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($pc[2]) ?></span>
            <div class="d-flex align-items-center">
              <?php 
              $hubInclude = trim($pc[53] ?? '');
              $hubIncludePersist = trim($pc[59] ?? '');
              $hubPersistMessage = trim($pc[60] ?? '');
              $hubPersistState = trim($pc[61] ?? '');
              
              // Show persistent badge first if applicable
              if (strtolower($hubIncludePersist) === 'yes'):
                $persistTitle = 'Persistent course';
                if (!empty($hubPersistMessage)) {
                  $persistTitle .= ': ' . htmlspecialchars($hubPersistMessage);
                }
                $persistClass = (strtolower($hubPersistState) === 'inactive') ? 'bg-warning' : 'bg-info';
              ?>
                <span class="badge <?= $persistClass ?> me-1" title="<?= $persistTitle ?>">Persistent</span>
              <?php endif; ?>
              
              <?php if (strtolower($hubInclude) === 'yes'): ?>
                <span class="badge bg-success me-2" title="Included in LearningHUB catalog">Learning<b>HUB</b></span>
              <?php elseif (strtolower($hubInclude) === 'no'): ?>
                <span class="badge bg-secondary me-2" title="Not included in LearningHUB catalog">No Learning<b>HUB</b></span>
              <?php endif; ?>
              <a href="https://learning.gov.bc.ca/psp/CHIPSPLM/EMPLOYEE/ELM/c/LM_COURSESTRUCTURE.LM_CI_LA_CMP.GBL?LM_CI_ID=<?= htmlspecialchars($pc[50]) ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank">Edit</a>
              <a href="https://corporatelearning.gww.gov.bc.ca/learninghub/course/<?= htmlspecialchars($pc[55]) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
            </div>
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
              <a href="course-form.php?partnerid=<?= $pc[36] ?>&courseid=<?= htmlspecialchars($pc[0]) ?>">
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
  </div>
</div>

</div>


<?php require('../templates/footer.php') ?>
</div>
