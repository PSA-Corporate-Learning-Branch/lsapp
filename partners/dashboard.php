<?php opcache_reset() ?>
<?php require('../inc/lsapp.php') ?>

<?php if(canACcess()): ?>

<?php getHeader() ?>

<title>Manage Learning Partner</title>

<?php getScripts() ?>

<?php 
$courses = getCourses();
$filteredCourses = array_filter($courses, function($course) {
    return ($course[1] === 'Request' || $course[1] === 'Requested') && $course[52] !== 'PSA Learning System';
});

// Load partner data
$partnerData = json_decode(file_get_contents('../data/partners.json'), true);
$partnerMap = [];
foreach ($partnerData as $partner) {
    $partnerMap[$partner['name']] = $partner['slug'];
}
?>
</head>
<body>
<?php getNavigation() ?>


<div class="container-lg p-lg-5 p-4 bg-light-subtle">
    <h1>Corporate Learning Partners</h1>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="dashboard.php">Partner Admin Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="index.php">Partner List</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="form.php">Add New Partner</a>
        </li>
    </ul>

    <div class="row">
       
        <div class="col-md-6">
            <h2>Partner Requests</h2>
            <p>New partnership requests.</p>
<?php
$pendingPartners = array_filter($partnerData, function($partner) {
    return !in_array(strtolower($partner['status']), ['active', 'inactive']);
});
if (!empty($pendingPartners)):
?>
<ul class="list-group mb-4">
    <?php foreach ($pendingPartners as $partner): ?>
        <li class="list-group-item">
            <a href="view.php?slug=<?php echo urlencode($partner['slug']); ?>"><?= htmlspecialchars($partner['name']) ?></a>
            <!-- <span class="badge bg-secondary"><?= htmlspecialchars($partner['status']) ?></span> -->
        </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="text-muted">No pending partner records at this time.</p>
<?php endif; ?>
</div>
<div class="col-md-6">
            <h2>Partner Contacts</h2>
            <p>Partner contacts to be added to an existing partner.</p>
<?php
$contactRequestsFile = '../data/partner_contact_requests.json';
if (file_exists($contactRequestsFile)):
    $contactRequests = json_decode(file_get_contents($contactRequestsFile), true);
    if (!empty($contactRequests)):
?>
    <ul class="list-group mb-4">
        <?php foreach ($contactRequests as $request): ?>
            <li class="list-group-item">
                <strong><?= htmlspecialchars($request['name']) ?></strong> (<?= htmlspecialchars($request['idir']) ?>) 
                requested to be added as <strong><?= htmlspecialchars($request['role']) ?></strong> 
                to <strong><?= htmlspecialchars($request['partner_name']) ?></strong>.
                <br>
                <small class="text-muted"><?= date('F j, Y, g:i a', strtotime($request['timestamp'])) ?></small>
                <form action="contact-approve.php" method="POST" class="mt-2">
                    <?php foreach (['partner_slug', 'partner_name', 'name', 'email', 'idir', 'title', 'role'] as $field): ?>
                        <input type="hidden" name="<?= $field ?>" value="<?= htmlspecialchars($request[$field]) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php
    else:
        echo "<p class='text-muted'>No contact requests at this time.</p>";
    endif;
else:
    echo "<p class='text-muted'>No contact requests found.</p>";
endif;
?>
</div>
<div class="col-m-12">
<h2>
    Course Requests
    <div class="float-end">
        <a href="https://learn.bcpublicservice.gov.bc.ca/learning-hub/learninghub-courses-for-review.xml" 
            title="Subscribe to the RSS feed of requested courses."
            class="btn btn-sm btn-outline-secondary" target="_blank">
                RSS Feed
        </a>
    </div>
</h2>
<p>Courses submitted by learning partners for review and publishing on the 
    LearningHUB.</p>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Course Name</th>
            <th>Learning Partner</th>
            <th>Status</th>
            <th>Requested By</th>
            <th>Created Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($filteredCourses as $course): ?>
        <tr>
            <td><a href="../course.php?courseid=<?php echo urlencode($course[0]); ?>"><?php echo htmlspecialchars($course[2]); ?></a></td>
            <td>
                <?php 
                $partnerInfo = getPartnerById($course[36]);
                if ($partnerInfo): ?>
                    <a href="view.php?slug=<?php echo urlencode($partnerInfo['slug']); ?>"><?php echo htmlspecialchars($partnerInfo['name']); ?></a>
                <?php else: ?>
                    <?php echo htmlspecialchars($course[36]); ?>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($course[1]); ?></td>
            <td><?php echo htmlspecialchars($course[14]); ?></td>
            <td><?php echo htmlspecialchars($course[15]); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- All Partner Contacts -->
<div class="row mt-5">
    <div class="col-12">
        <h2>All Partner Contacts</h2>
        <p>Browse and search through all partner contacts.</p>
        
        <?php
        // Build array of all contacts with partner info
        $allContacts = [];
        foreach ($partnerData as $partner) {
            if (isset($partner['contacts']) && is_array($partner['contacts'])) {
                foreach ($partner['contacts'] as $contact) {
                    // Show contacts that have a meaningful name
                    if (!empty($contact['name']) && $contact['name'] !== 'Unknown') {
                        $contact['partner_name'] = $partner['name'];
                        $contact['partner_slug'] = $partner['slug'];
                        $contact['employee_facing_contact'] = $partner['employee_facing_contact'] ?? '';
                        $allContacts[] = $contact;
                    }
                }
            }
        }
        
        // Sort contacts by first name
        usort($allContacts, function($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
        ?>
        
        <?php if (count($allContacts) > 0): ?>
        <div id="contactsList">
            <div class="mb-3">
                <input type="text" class="search form-control" placeholder="Search contacts by name, email, IDIR, title, role, or partner...">
            </div>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>IDIR</th>
                        <th>Title</th>
                        <th>Role</th>
                        <th>Partner</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php foreach ($allContacts as $contact): ?>
                    <tr>
                        <td class="name"><?= htmlspecialchars($contact['name'] ?? '-') ?></td>
                        <td class="email"><?= htmlspecialchars($contact['email'] ?? '-') ?></td>
                        <td class="idir"><?= htmlspecialchars($contact['idir'] ?? '-') ?></td>
                        <td class="title"><?= htmlspecialchars($contact['title'] ?? '-') ?></td>
                        <td class="role"><?= htmlspecialchars($contact['role'] ?? '-') ?></td>
                        <td class="partner"><a href="view.php?slug=<?= urlencode($contact['partner_slug']) ?>"><?= htmlspecialchars($contact['partner_name']) ?></a></td>
                        <td class="added"><?= isset($contact['added_at']) ? date('Y-m-d', strtotime($contact['added_at'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="text-muted"><small>Showing <?= count($allContacts) ?> active contacts</small></p>
        </div>
        <?php else: ?>
        <p class="alert alert-info">No active contacts found in the partner database. Most partners currently have "unassigned" or "unknown" contacts.</p>
        <?php endif; ?>
    </div>
</div>
</div>

<?php require('../templates/javascript.php') ?>

<?php if (count($allContacts) > 0): ?>
<script>
// Initialize List.js for the contacts table
var options = {
    valueNames: ['name', 'email', 'idir', 'title', 'role', 'partner', 'employee-contact', 'added'],
    searchClass: 'search'
};

var contactsList = new List('contactsList', options);

// Add counter for filtered results
contactsList.on('searchComplete', function() {
    var visibleItems = contactsList.visibleItems.length;
    var totalItems = contactsList.size();
    var counterText = document.querySelector('#contactsList .text-muted small');
    
    if (visibleItems === totalItems) {
        counterText.textContent = `Showing ${totalItems} active contacts`;
    } else {
        counterText.textContent = `Showing ${visibleItems} of ${totalItems} active contacts`;
    }
});
</script>
<?php endif; ?>

<?php require('../templates/footer.php') ?>

<?php endif ?>