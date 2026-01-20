<?php
require('../inc/lsapp.php');

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header('Location: index.php?error=No partner ID provided');
    exit;
}

// Load development partner
$partnersFile = '../data/development-partners.csv';
$partner = null;

if (file_exists($partnersFile)) {
    $data = array_map('str_getcsv', file($partnersFile));
    array_shift($data); // Remove header
    foreach ($data as $row) {
        if (!empty($row[0]) && $row[0] == $id) {
            $partner = [
                'id' => $row[0] ?? '',
                'status' => $row[1] ?? '',
                'type' => $row[2] ?? '',
                'name' => $row[3] ?? '',
                'description' => $row[4] ?? '',
                'url' => $row[5] ?? '',
                'contact_name' => $row[6] ?? '',
                'contact_email' => $row[7] ?? ''
            ];
            break;
        }
    }
}

if (!$partner) {
    header('Location: index.php?error=Partner not found');
    exit;
}

// Find all courses associated with this development partner
$devPartnerRelFile = '../data/courses-devpartners.csv';
$courseIds = [];

if (file_exists($devPartnerRelFile)) {
    $relData = array_map('str_getcsv', file($devPartnerRelFile));
    array_shift($relData); // Remove header
    foreach ($relData as $row) {
        if (!empty($row[2]) && $row[2] == $id) {
            $courseIds[] = $row[1]; // course_id
        }
    }
}

// Get course details for each associated course
$courses = [];
if (!empty($courseIds)) {
    $allCourses = getCourses();
    array_shift($allCourses); // Remove header

    foreach ($allCourses as $course) {
        if (in_array($course[0], $courseIds)) {
            $courses[] = [
                'id' => $course[0],
                'name' => $course[2],
                'status' => $course[1],
                'method' => $course[21],
                'partner_id' => $course[36],
                'hub_include' => $course[53] ?? 'No'
            ];
        }
    }

    // Sort courses by name
    usort($courses, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
getScripts();
echo getHeader('Development Partner: ' . $partner['name']);
echo getNavigation();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?= htmlspecialchars($partner['name']) ?></h1>
                <div>
                    <a href="update.php?id=<?= htmlspecialchars($partner['id']) ?>" class="btn btn-primary">Edit</a>
                    <a href="index.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Partner Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    <?php
                                    $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                                    if ($partner['status'] === 'active') {
                                        $badgeClass = 'bg-success-subtle text-success-emphasis';
                                    } elseif ($partner['status'] === 'inactive') {
                                        $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars(ucfirst($partner['status'])) ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4">Type</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars(ucfirst($partner['type'])) ?></dd>

                                <?php if (!empty($partner['url'])): ?>
                                <dt class="col-sm-4">Website</dt>
                                <dd class="col-sm-8">
                                    <a href="<?= htmlspecialchars($partner['url']) ?>" target="_blank" rel="noopener">
                                        <?= htmlspecialchars($partner['url']) ?> <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </dd>
                                <?php endif; ?>

                                <?php if (!empty($partner['description'])): ?>
                                <dt class="col-sm-4">Description</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($partner['description']) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($partner['contact_name']) || !empty($partner['contact_email'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <?php if (!empty($partner['contact_name'])): ?>
                                <dt class="col-sm-4">Name</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($partner['contact_name']) ?></dd>
                                <?php endif; ?>

                                <?php if (!empty($partner['contact_email'])): ?>
                                <dt class="col-sm-4">Email</dt>
                                <dd class="col-sm-8">
                                    <a href="mailto:<?= htmlspecialchars($partner['contact_email']) ?>">
                                        <?= htmlspecialchars($partner['contact_email']) ?>
                                    </a>
                                </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Associated Courses</h5>
                            <span class="badge bg-primary"><?= count($courses) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($courses)): ?>
                                <div class="p-3 text-muted">No courses associated with this partner.</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($courses as $course): ?>
                                        <a href="../course.php?courseid=<?= htmlspecialchars($course['id']) ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($course['name']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($course['method']) ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <?php
                                                    $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                                                    if ($course['status'] === 'Active') {
                                                        $courseBadgeClass = 'bg-success-subtle text-success-emphasis';
                                                    } elseif ($course['status'] === 'Inactive') {
                                                        $courseBadgeClass = 'bg-danger-subtle text-danger-emphasis';
                                                    } 
                                                    ?>
                                                    <span class="badge <?= $courseBadgeClass ?> mb-1">
                                                        <?= htmlspecialchars($course['status']) ?>
                                                    </span>
                                                    <?php if ($course['hub_include'] === 'Yes'): ?>
                                                        <br><small class="badge bg-dark-subtle text-dark-emphasis">HUB</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <small class="text-muted">Partner ID: <?= htmlspecialchars($partner['id']) ?></small>
            </div>
        </div>
    </div>
</div>

<?php

include('../templates/javascript.php');
include('../templates/footer.php');
?>
