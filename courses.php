<?php 
require('inc/lsapp.php');

// Initialize filter variables
$filters = [
    'topic' => $_GET['topic'] ?? '',
    'audience' => $_GET['audience'] ?? '',
    'level' => $_GET['level'] ?? '',
    'category' => $_GET['category'] ?? '',
    'delivery' => $_GET['delivery'] ?? '',
    'platform' => $_GET['platform'] ?? '',
    'status' => $_GET['status'] ?? '',
    'processed' => $_GET['processed'] ?? '',
    'openaccess' => $_GET['openaccess'] ?? '',
    'hubonly' => $_GET['hubonly'] ?? '',
    'moodle' => $_GET['moodle'] ?? '',
    'sort' => $_GET['sort'] ?? ''
];

// Decode URL-encoded values
foreach (['topic', 'audience', 'level', 'category', 'platform'] as $key) {
    if ($filters[$key]) {
        $filters[$key] = urldecode($filters[$key]);
    }
}

// Get all courses and remove header row
$courses = getCourses();
array_shift($courses);

// Set up sorting
$sortField = 2; // Course name by default
$sortDir = SORT_ASC;
if ($filters['sort'] === 'dateadded') {
    $sortField = 13; // Requested field contains the actual date added
    $sortDir = SORT_DESC;
}

// Helper function to normalize different date formats
function normalizeDate($dateStr) {
    if (empty($dateStr) || $dateStr === '1900-01-01') {
        return '1900-01-01 00:00:00';
    }
    
    // Handle YYYYMMDDHHMMSS format (e.g., "20250614223927")
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $dateStr, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . 
               $matches[4] . ':' . $matches[5] . ':' . $matches[6];
    }
    
    // Handle YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr . ' 00:00:00';
    }
    
    // Handle YYYY-MM-DD HH:MM:SS format
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    
    // Default fallback
    return $dateStr;
}

// Sort function
function sortCourses($a, $b) {
    global $sortDir, $sortField;
    
    // Special handling for date sorting - treat empty dates as very old
    if ($sortField == 13) { // Requested field
        $aDateRaw = empty($a[$sortField]) ? '1900-01-01' : trim($a[$sortField], '"');
        $bDateRaw = empty($b[$sortField]) ? '1900-01-01' : trim($b[$sortField], '"');
        
        // Normalize different date formats for comparison
        $aDate = normalizeDate($aDateRaw);
        $bDate = normalizeDate($bDateRaw);
        
        return $sortDir == SORT_ASC 
            ? strcmp($aDate, $bDate)
            : strcmp($bDate, $aDate);
    }
    
    return $sortDir == SORT_ASC 
        ? strcmp($a[$sortField], $b[$sortField])
        : strcmp($b[$sortField], $a[$sortField]);
}

// Apply filters first, then sort
$filteredCourses = [];
$activeCount = 0;
$inactiveCount = 0;

foreach ($courses as $course) {
    // Status filter - if no status filter specified, only show active courses
    // If "inactive" is specified, only show inactive courses
    // If "active" is specified, only show active courses
    // If "requested" is specified, only show requested courses
    if (!$filters['status']) {
        // Default: only show active courses
        if ($course[1] !== 'Active') continue;
    } elseif ($filters['status'] === 'active') {
        if ($course[1] !== 'Active') continue;
    } elseif ($filters['status'] === 'inactive') {
        if ($course[1] !== 'Inactive') continue;
    } elseif ($filters['status'] === 'requested') {
        if ($course[1] !== 'Requested') continue;
    }
    
    // Apply other filters
    if ($filters['level'] && $filters['level'] !== $course[40]) continue;
    if ($filters['audience'] && $filters['audience'] !== $course[39]) continue;
    if ($filters['topic'] && $filters['topic'] !== $course[38]) continue;
    if ($filters['delivery'] && $filters['delivery'] !== $course[21]) continue;
    if ($filters['platform'] && $filters['platform'] !== $course[52]) continue;
    if ($filters['processed'] && $course[48] == $filters['processed']) continue;
    if ($filters['openaccess'] && !($course[57] === 'true' || $course[57] === 'on' || strtolower($course[57]) === 'yes')) continue;
    if ($filters['hubonly'] && strtolower($filters['hubonly']) === 'true' && strtolower($course[53]) !== 'yes') continue;
    if ($filters['moodle'] && strtolower($filters['moodle']) === 'true' && strtolower($course[47]) !== 'yes') continue;
    
    // Count courses by status
    if ($course[1] === 'Active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    
    $filteredCourses[] = $course;
}

// Sort the filtered courses
usort($filteredCourses, 'sortCourses');

// Get total counts for all courses (for display purposes)
$totalActiveCount = 0;
$totalInactiveCount = 0;
foreach ($courses as $course) {
    if ($course[1] === 'Active') {
        $totalActiveCount++;
    } elseif ($course[1] === 'Inactive') {
        $totalInactiveCount++;
    }
}

// Get filter options
$deliveryMethods = getDeliveryMethods();
$topics = getAllTopics();
$audiences = getAllAudiences();
$levels = getLevels();

// Get unique platforms from courses
$platforms = [];
foreach ($courses as $course) {
    if (!empty($course[52]) && !in_array($course[52], $platforms)) {
        $platforms[] = $course[52];
    }
}
sort($platforms);

// Helper function to build query string
function buildQueryString($filters, $exclude = []) {
    $query = [];
    foreach ($filters as $key => $value) {
        if ($value && !in_array($key, $exclude)) {
            $query[$key] = $value;
        }
    }
    return http_build_query($query);
}

// Helper function to generate filter link
function getFilterLink($filters, $key, $value = null) {
    if ($value === null) {
        // Remove filter
        return 'courses.php?' . buildQueryString($filters, [$key]);
    } else {
        // Add/update filter
        $newFilters = $filters;
        $newFilters[$key] = $value;
        return 'courses.php?' . buildQueryString($newFilters);
    }
}

?>
<?php getHeader() ?>
<title>Corporate Learning Branch Course Catalog</title>
<?php getScripts() ?>

<body>
<?php getNavigation() ?>

<div id="courses">
<div class="container-fluid">
<div class="row justify-content-md-center">

<div class="col-md-8">
    <?php if ($filters['status'] === 'inactive'): ?>
        <h1>Inactive Courses</h1>
    <?php elseif ($filters['status'] === 'active'): ?>
        <h1>Active Courses</h1>
    <?php elseif ($filters['status'] === 'requested'): ?>
        <h1>Requested Courses</h1>
    <?php else: ?>
        <h1>Courses</h1>
    <?php endif; ?>
</div>
</div>

<div class="row justify-content-md-center">
<div class="col-md-4 col-xl-3">

    <input class="search form-control mb-2" placeholder="search">
    
    <!-- Special filters -->
    <div class="my-3">
        <?php if ($filters['openaccess']): ?>
            <a href="<?= getFilterLink($filters, 'openaccess') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; Open Access</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'openaccess', 'true') ?>" class="badge bg-light-subtle text-primary-emphasis">Open Access</a>
        <?php endif; ?>
        
        <?php if ($filters['hubonly'] === 'true'): ?>
            <a href="<?= getFilterLink($filters, 'hubonly') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; LearningHUB</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'hubonly', 'true') ?>" class="badge bg-light-subtle text-primary-emphasis">LearningHUB</a>
        <?php endif; ?>
        
        <?php if ($filters['moodle'] === 'true'): ?>
            <a href="<?= getFilterLink($filters, 'moodle') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; Moodle</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'moodle', 'true') ?>" class="badge bg-light-subtle text-primary-emphasis">Moodle</a>
        <?php endif; ?>
        
        <!-- <a class="badge bg-light-subtle text-primary-emphasis float-end" href="courses.php?sort=dateadded">Clear All Filters</a> -->
    </div>
    
    <!-- Status Filter -->
    <div class="mb-3">
        <div class="fw-bold">Status</div>
        <?php if ($filters['status'] === 'active'): ?>
            <a href="<?= getFilterLink($filters, 'status') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; Active</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'status', 'active') ?>" class="badge bg-light-subtle text-primary-emphasis">Active</a>
        <?php endif; ?>
        
        <?php if ($filters['status'] === 'inactive'): ?>
            <a href="<?= getFilterLink($filters, 'status') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; Inactive</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'status', 'inactive') ?>" class="badge bg-light-subtle text-primary-emphasis">Inactive</a>
        <?php endif; ?>
        
        <?php if ($filters['status'] === 'requested'): ?>
            <a href="<?= getFilterLink($filters, 'status') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; Requested</a>
        <?php else: ?>
            <a href="<?= getFilterLink($filters, 'status', 'requested') ?>" class="badge bg-light-subtle text-primary-emphasis">Requested</a>
        <?php endif; ?>
    </div>
    
    <!-- Delivery Method Filter -->
    <div class="mb-3">
        <div class="fw-bold">Delivery Method</div>
        <?php foreach ($deliveryMethods as $method): ?>
            <?php if ($filters['delivery'] === $method): ?>
                <a href="<?= getFilterLink($filters, 'delivery') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; <?= $method ?></a>
            <?php else: ?>
                <a href="<?= getFilterLink($filters, 'delivery', $method) ?>" class="badge bg-light-subtle text-primary-emphasis"><?= $method ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <!-- Audience Filter -->
    <div class="mb-3">
        <div class="fw-bold">Audience</div>
        <?php foreach ($audiences as $audience): ?>
            <?php if ($filters['audience'] === $audience): ?>
                <a href="<?= getFilterLink($filters, 'audience') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; <?= $audience ?></a>
            <?php else: ?>
                <a href="<?= getFilterLink($filters, 'audience', $audience) ?>" class="badge bg-light-subtle text-primary-emphasis"><?= $audience ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <!-- Topics Filter -->
    <div class="mb-3">
        <div class="fw-bold">Topics</div>
        <?php foreach ($topics as $topic): ?>
            <?php if ($filters['topic'] === $topic): ?>
                <a href="<?= getFilterLink($filters, 'topic') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; <?= $topic ?></a>
            <?php else: ?>
                <a href="<?= getFilterLink($filters, 'topic', $topic) ?>" class="badge bg-light-subtle text-primary-emphasis"><?= $topic ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Platform Filter -->
    <div class="mb-3">
        <div class="fw-bold">Platform</div>
        <?php foreach ($platforms as $platform): ?>
            <?php if ($filters['platform'] === $platform): ?>
                <a href="<?= getFilterLink($filters, 'platform') ?>" class="badge bg-dark-subtle text-primary-emphasis">&times; <?= $platform ?></a>
            <?php else: ?>
                <a href="<?= getFilterLink($filters, 'platform', $platform) ?>" class="badge bg-light-subtle text-primary-emphasis"><?= $platform ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

</div>

<div class="col-md-5">
    <!-- Sorting Options -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="" id="course-count">
            <span class="badge bg-primary course-total"><?= count($filteredCourses) ?></span> course<span class="course-plural"><?= count($filteredCourses) !== 1 ? 's' : '' ?></span> found
        </div>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-sort-down"></i>
                <?php if ($filters['sort'] === 'dateadded'): ?>
                    Sort: Recent First
                <?php else: ?>
                    Sort: Alphabetical
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                <li>
                    <a class="dropdown-item <?= !$filters['sort'] ? 'active' : '' ?>" href="<?= getFilterLink($filters, 'sort') ?>">
                        <i class="bi bi-sort-alpha-down me-2"></i>Alphabetical (A-Z)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item <?= $filters['sort'] === 'dateadded' ? 'active' : '' ?>" href="<?= getFilterLink($filters, 'sort', 'dateadded') ?>">
                        <i class="bi bi-sort-numeric-down me-2"></i>Recent First
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="list">
    <?php foreach ($filteredCourses as $course): ?>
        <div class="mb-2 p-3 bg-light-subtle border border-secondary-subtle rounded-3">
            <div>
                <!-- <div class="float-end pl-3 pb-3">
                    <?php $statusBg = ($course[1] === 'Inactive') ? 'secondary' : 'primary'; ?>
                    <span class="badge text-light-subtle bg-<?= $statusBg ?>"><?= $course[1] ?></span> 
                </div> -->
                
                <a class="badge bg-body text-primary-emphasis" href="<?= getFilterLink($filters, 'delivery', $course[21]) ?>">
                    <?= $course[21] ?>
                </a>
                <?php if (!empty($course[39])): ?>
                    <a class="badge bg-body text-primary-emphasis" href="<?= getFilterLink($filters, 'audience', $course[39]) ?>"><?= $course[39] ?></a>
                <?php endif; ?>
                <?php if (!empty($course[38])): ?>
                    <a class="badge bg-body text-primary-emphasis" href="<?= getFilterLink($filters, 'topic', $course[38]) ?>"><?= $course[38] ?></a>
                <?php endif; ?>
                <?php if ($course[53] == 'Yes' || $course[53] == 1): ?>
                    <span class="badge bg-body">Learning<strong>HUB</strong></span>
                <?php endif; ?>
            </div>
            
            <div class="name" style="font-size: 1.3em">
                <a href="/lsapp/course.php?courseid=<?= $course[0] ?>"><?= sanitize($course[2]) ?></a>
            </div>
            
            <div class="mb-3">
                <?php if (!empty($course[4])): ?>
                    <a class="badge bg-light-subtle text-primary-emphasis" 
                       title="Find course in ELM by ITEM-code" 
                       target="_blank" 
                       href="https://learning.gov.bc.ca/psc/CHIPSPLM_6/EMPLOYEE/ELM/c/LM_OD_EMPLOYEE_FL.LM_FND_LRN_FL.GBL?Page=LM_FND_LRN_RSLT_FL&Action=U&KWRD=%22<?= sanitize($course[4]) ?>%22">
                       <?= $course[4] ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($course[50])): ?>
                    <a class="badge bg-light-subtle text-primary-emphasis" 
                       title="Edit course in ELM" 
                       target="_blank" 
                       href="https://learning.gov.bc.ca/psp/CHIPSPLM/EMPLOYEE/ELM/c/LM_COURSESTRUCTURE.LM_CI_LA_CMP.GBL?LM_CI_ID=<?= sanitize($course[50]) ?>">
                       <?= $course[50] ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="row bg-light-subtle">
                <div class="col-md-6">
                    <div class="p-2">
                        Platform: 
                        <?php if (!empty($course[52])): ?>
                            <?php 
                            $platformId = strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9\s-]/i', '', $course[52])));
                            ?>
                            <a href="/lsapp/platform.php?id=<?= urlencode($platformId) ?>"><?= sanitize($course[52]) ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-2">
                        Corp. Partner: 
                        <?php if (!empty($course[36])): ?>
                            <?php 
                            $partnerInfo = getPartnerById($course[36]);
                            if ($partnerInfo): ?>
                                <a href="partners/view.php?slug=<?= $partnerInfo['slug'] ?>"><?= sanitize($partnerInfo['name']) ?></a>
                            <?php else: ?>
                                <?= sanitize($course[36]) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

</div>
</div>
</div>

<?php require('templates/javascript.php') ?>

<script>
var options = {
    valueNames: ['name', 'delivery', 'category']
};
var courses = new List('courses', options);

// Update course count as user searches
courses.on('searchComplete', function() {
    var count = courses.update().matchingItems.length;
    var countElement = document.getElementById('course-count');
    var totalElement = countElement.querySelector('.course-total');
    var pluralElement = countElement.querySelector('.course-plural');
    
    totalElement.textContent = count;
    pluralElement.textContent = count !== 1 ? 's' : '';
});
</script>

<?php require('templates/footer.php') ?>