<?php
/**
 * Course Map - Advanced Filtering Interface
 *
 * Provides comprehensive multi-term filtering for courses including:
 * - Multiple selections per taxonomy
 * - Learning Partners filtering (Corporate + Development)
 * - Faceted counts for each filter option
 * - Enhanced UI with filter management
 */

require('inc/lsapp.php');

// Parse filter parameters - accept both comma-separated strings and arrays
function parseFilterParam($key) {
    if (!isset($_GET[$key])) return [];

    $value = $_GET[$key];

    // If already an array, sanitize each value
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }

    // If comma-separated string, split and sanitize
    if (strpos($value, ',') !== false) {
        return array_map('sanitize', explode(',', $value));
    }

    // Single value
    return $value ? [sanitize($value)] : [];
}

// Build filter arrays
$filters = [
    'topics' => parseFilterParam('topics'),
    'audiences' => parseFilterParam('audiences'),
    'levels' => parseFilterParam('levels'),
    'categories' => parseFilterParam('categories'),
    'delivery' => parseFilterParam('delivery'),
    'platforms' => parseFilterParam('platforms'),
    'corporate_partners' => parseFilterParam('corporate_partners'),
    'dev_partners' => parseFilterParam('dev_partners'),
    'openaccess' => isset($_GET['openaccess']) ? sanitize($_GET['openaccess']) : '',
    'hubonly' => isset($_GET['hubonly']) ? sanitize($_GET['hubonly']) : '',
    'moodle' => isset($_GET['moodle']) ? sanitize($_GET['moodle']) : '',
    'status' => isset($_GET['status']) ? sanitize($_GET['status']) : '',
    'search' => isset($_GET['search']) ? sanitize($_GET['search']) : '',
    'sort' => isset($_GET['sort']) ? sanitize($_GET['sort']) : ''
];

// Load all courses
$allCourses = getCourses();
array_shift($allCourses); // Remove header row

// Get all active courses (for facet counts)
$activeCourses = [];
foreach ($allCourses as $course) {
    if (isset($course[1]) && $course[1] === 'Active') {
        $activeCourses[] = $course;
    }
}

// Load partner data
$allPartners = getAllPartners();
$devPartnersData = [];
$devPartnersFile = 'data/development-partners.csv';
if (file_exists($devPartnersFile)) {
    $devPartnersRaw = array_map('str_getcsv', file($devPartnersFile));
    array_shift($devPartnersRaw); // Remove header
    foreach ($devPartnersRaw as $dp) {
        $devPartnersData[$dp[0]] = [
            'id' => $dp[0],
            'status' => $dp[1],
            'type' => $dp[2],
            'name' => $dp[3],
            'description' => $dp[4] ?? '',
            'url' => $dp[5] ?? '',
            'contact_name' => $dp[6] ?? '',
            'contact_email' => $dp[7] ?? ''
        ];
    }
}

// Load course-development partner relationships
$courseDevPartners = [];
$relationFile = 'data/courses-devpartners.csv';
if (file_exists($relationFile)) {
    $relations = array_map('str_getcsv', file($relationFile));
    array_shift($relations); // Remove header
    foreach ($relations as $rel) {
        $courseId = $rel[1];
        $devPartnerId = $rel[2];
        if (!isset($courseDevPartners[$courseId])) {
            $courseDevPartners[$courseId] = [];
        }
        $courseDevPartners[$courseId][] = $devPartnerId;
    }
}

// Filter courses
$filteredCourses = [];
foreach ($allCourses as $course) {
    // Default: only show Active courses unless status filter is applied
    if (empty($filters['status'])) {
        if (!isset($course[1]) || $course[1] !== 'Active') continue;
    } else {
        if (!isset($course[1]) || $course[1] !== $filters['status']) continue;
    }

    // Multi-term filtering with OR logic within each category

    // Topics filter
    if (!empty($filters['topics'])) {
        if (!isset($course[38]) || !in_array($course[38], $filters['topics'])) continue;
    }

    // Audiences filter
    if (!empty($filters['audiences'])) {
        if (!isset($course[39]) || !in_array($course[39], $filters['audiences'])) continue;
    }

    // Levels filter
    if (!empty($filters['levels'])) {
        if (!isset($course[40]) || !in_array($course[40], $filters['levels'])) continue;
    }

    // Categories filter
    if (!empty($filters['categories'])) {
        if (!isset($course[20]) || !in_array($course[20], $filters['categories'])) continue;
    }

    // Delivery method filter
    if (!empty($filters['delivery'])) {
        if (!isset($course[21]) || !in_array($course[21], $filters['delivery'])) continue;
    }

    // Platforms filter
    if (!empty($filters['platforms'])) {
        if (!isset($course[52]) || !in_array($course[52], $filters['platforms'])) continue;
    }

    // Corporate Partners filter
    if (!empty($filters['corporate_partners'])) {
        $coursePartnerId = $course[36] ?? '';
        if (!in_array($coursePartnerId, $filters['corporate_partners'])) continue;
    }

    // Development Partners filter
    if (!empty($filters['dev_partners'])) {
        $courseId = $course[0];
        $courseDevPartnerIds = $courseDevPartners[$courseId] ?? [];

        // Check if course has any of the selected dev partners
        $hasMatch = false;
        foreach ($filters['dev_partners'] as $selectedDevPartner) {
            if (in_array($selectedDevPartner, $courseDevPartnerIds)) {
                $hasMatch = true;
                break;
            }
        }
        if (!$hasMatch) continue;
    }

    // Binary filters
    if ($filters['openaccess'] && (!isset($course[57]) || ($course[57] !== 'true' && $course[57] !== 'on'))) continue;
    if ($filters['hubonly'] && (!isset($course[53]) || $course[53] !== 'Yes')) continue;
    if ($filters['moodle'] && (!isset($course[47]) || $course[47] !== 'Yes')) continue;

    // Search filter (if provided)
    if ($filters['search']) {
        $searchTerm = strtolower($filters['search']);
        $searchableText = strtolower(
            ($course[2] ?? '') . ' ' .
            ($course[3] ?? '') . ' ' .
            ($course[16] ?? '') . ' ' .
            ($course[19] ?? '')
        );
        if (strpos($searchableText, $searchTerm) === false) continue;
    }

    $filteredCourses[] = $course;
}

// Calculate faceted counts for each filter option
function calculateFacetCounts($courses, $currentFilters, $courseDevPartners) {
    $facets = [
        'topics' => [],
        'audiences' => [],
        'levels' => [],
        'categories' => [],
        'delivery' => [],
        'platforms' => [],
        'corporate_partners' => [],
        'dev_partners' => []
    ];

    // For each course in the current result set
    foreach ($courses as $course) {
        // Count topics
        if (isset($course[38]) && !empty($course[38])) {
            $facets['topics'][$course[38]] = ($facets['topics'][$course[38]] ?? 0) + 1;
        }

        // Count audiences
        if (isset($course[39]) && !empty($course[39])) {
            $facets['audiences'][$course[39]] = ($facets['audiences'][$course[39]] ?? 0) + 1;
        }

        // Count levels
        if (isset($course[40]) && !empty($course[40])) {
            $facets['levels'][$course[40]] = ($facets['levels'][$course[40]] ?? 0) + 1;
        }

        // Count categories
        if (isset($course[20]) && !empty($course[20])) {
            $facets['categories'][$course[20]] = ($facets['categories'][$course[20]] ?? 0) + 1;
        }

        // Count delivery methods
        if (isset($course[21]) && !empty($course[21])) {
            $facets['delivery'][$course[21]] = ($facets['delivery'][$course[21]] ?? 0) + 1;
        }

        // Count platforms
        if (isset($course[52]) && !empty($course[52])) {
            $facets['platforms'][$course[52]] = ($facets['platforms'][$course[52]] ?? 0) + 1;
        }

        // Count corporate partners
        if (isset($course[36]) && !empty($course[36])) {
            $facets['corporate_partners'][$course[36]] = ($facets['corporate_partners'][$course[36]] ?? 0) + 1;
        }

        // Count development partners
        $courseId = $course[0] ?? null;
        if (!$courseId) continue;
        if (isset($courseDevPartners[$courseId])) {
            foreach ($courseDevPartners[$courseId] as $devPartnerId) {
                $facets['dev_partners'][$devPartnerId] = ($facets['dev_partners'][$devPartnerId] ?? 0) + 1;
            }
        }
    }

    return $facets;
}

// Calculate facet counts from ALL active courses, not just filtered ones
// This ensures all filter options remain visible even when other filters are applied
$facetCounts = calculateFacetCounts($activeCourses, $filters, $courseDevPartners);

// Sorting
$sortField = 'name';
$sortDir = 'asc';

if (!empty($filters['sort'])) {
    $sortParts = explode('-', $filters['sort']);
    $sortField = $sortParts[0] ?? 'name';
    $sortDir = $sortParts[1] ?? 'asc';
}

usort($filteredCourses, function($a, $b) use ($sortField, $sortDir) {
    $result = 0;

    switch ($sortField) {
        case 'name':
            $result = strcmp($a[2] ?? '', $b[2] ?? '');
            break;
        case 'topic':
            $result = strcmp($a[38] ?? '', $b[38] ?? '');
            break;
        case 'audience':
            $result = strcmp($a[39] ?? '', $b[39] ?? '');
            break;
        case 'delivery':
            $result = strcmp($a[21] ?? '', $b[21] ?? '');
            break;
        case 'platform':
            $result = strcmp($a[52] ?? '', $b[52] ?? '');
            break;
        case 'dateadded':
            $result = strtotime($b[51] ?? '1970-01-01') - strtotime($a[51] ?? '1970-01-01');
            break;
    }

    return $sortDir === 'desc' ? -$result : $result;
});

// Check if export is requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Build descriptive filename from active filters
    $filenameParts = ['courses'];

    // Add search term if present
    if (!empty($filters['search'])) {
        $filenameParts[] = 'search-' . preg_replace('/[^a-zA-Z0-9]/', '', substr($filters['search'], 0, 20));
    }

    // Add topics (limit to first 2)
    if (!empty($filters['topics'])) {
        $topics = array_slice($filters['topics'], 0, 2);
        foreach ($topics as $topic) {
            $filenameParts[] = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', $topic));
        }
    }

    // Add audiences (limit to first 2)
    if (!empty($filters['audiences'])) {
        $audiences = array_slice($filters['audiences'], 0, 2);
        foreach ($audiences as $audience) {
            $filenameParts[] = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', $audience));
        }
    }

    // Add delivery methods (limit to first 2)
    if (!empty($filters['delivery'])) {
        $delivery = array_slice($filters['delivery'], 0, 2);
        foreach ($delivery as $method) {
            $filenameParts[] = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', $method));
        }
    }

    // Add platforms (limit to first 1)
    if (!empty($filters['platforms'])) {
        $platform = array_slice($filters['platforms'], 0, 1);
        foreach ($platform as $p) {
            $filenameParts[] = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', substr($p, 0, 15)));
        }
    }

    // Add corporate partners (limit to first 1)
    if (!empty($filters['corporate_partners'])) {
        $partnerIds = array_slice($filters['corporate_partners'], 0, 1);
        foreach ($partnerIds as $partnerId) {
            $partner = getPartnerById($partnerId);
            if ($partner) {
                $filenameParts[] = preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', substr($partner['name'], 0, 20)));
            }
        }
    }

    // Add binary filters
    if ($filters['openaccess']) {
        $filenameParts[] = 'OpenAccess';
    }
    if ($filters['hubonly']) {
        $filenameParts[] = 'LearningHUB';
    }
    if ($filters['moodle']) {
        $filenameParts[] = 'Moodle';
    }

    // Add count
    $filenameParts[] = count($filteredCourses) . 'courses';

    // Add timestamp
    $filenameParts[] = date('Y-m-d');

    // Build final filename (limit total length to avoid filesystem issues)
    $filename = implode('-', $filenameParts);
    $filename = substr($filename, 0, 200); // Limit to 200 chars
    $filename .= '.csv';

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Write CSV header row
    $headerRow = [
        'CourseID', 'Status', 'CourseName', 'CourseShort', 'ItemCode', 'ClassTimes', 'ClassDays',
        'ELM', 'PreWork', 'PostWork', 'CourseOwner', 'MinMax', 'CourseNotes', 'Requested',
        'RequestedBy', 'EffectiveDate', 'CourseDescription', 'CourseAbstract', 'Prerequisites',
        'Keywords', 'Category', 'Method', 'elearning', 'WeShip', 'ProjectNumber', 'Responsibility',
        'ServiceLine', 'STOB', 'MinEnroll', 'MaxEnroll', 'StartTime', 'EndTime', 'Color',
        'Featured', 'Developer', 'EvaluationsLink', 'LearningHubPartner', 'Alchemer', 'Topics',
        'Audience', 'Levels', 'Reporting', 'PathLAN', 'PathStaging', 'PathLive', 'PathNIK',
        'PathTeams', 'isMoodle', 'TaxProcessed', 'TaxProcessedBy', 'ELMCourseID', 'Modified',
        'Platform', 'HUBInclude', 'RegistrationLink', 'CourseNameSlug', 'HubExpirationDate',
        'OpenAccessOptin', 'HubIncludeSync', 'HubIncludePersist', 'HubPersistMessage', 'HubIncludePersistState'
    ];
    fputcsv($output, $headerRow);

    // Write data rows
    foreach ($filteredCourses as $course) {
        fputcsv($output, $course);
    }

    fclose($output);
    exit;
}

// Helper function to build filter URLs
function buildFilterUrl($filterKey, $value, $action = 'add') {
    global $filters;

    $newFilters = $filters;

    // Check if this filter is array-based or string-based
    $isArrayFilter = is_array($newFilters[$filterKey]);

    if ($action === 'add') {
        if ($isArrayFilter) {
            if (!in_array($value, $newFilters[$filterKey])) {
                $newFilters[$filterKey][] = $value;
            }
        } else {
            $newFilters[$filterKey] = $value;
        }
    } elseif ($action === 'remove') {
        if ($isArrayFilter) {
            $newFilters[$filterKey] = array_filter($newFilters[$filterKey], function($v) use ($value) {
                return $v !== $value;
            });
        } else {
            $newFilters[$filterKey] = '';
        }
    } elseif ($action === 'toggle') {
        if ($isArrayFilter) {
            if (in_array($value, $newFilters[$filterKey])) {
                $newFilters[$filterKey] = array_filter($newFilters[$filterKey], function($v) use ($value) {
                    return $v !== $value;
                });
            } else {
                $newFilters[$filterKey][] = $value;
            }
        } else {
            // For string filters, toggle between value and empty
            $newFilters[$filterKey] = ($newFilters[$filterKey] === $value) ? '' : $value;
        }
    }

    // Build query string
    $params = [];
    foreach ($newFilters as $key => $val) {
        if (is_array($val) && !empty($val)) {
            $params[$key] = implode(',', $val);
        } elseif (!is_array($val) && $val !== '') {
            $params[$key] = $val;
        }
    }

    return 'course-map.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Helper function to check if filter is active
function isFilterActive($filterKey, $value) {
    global $filters;
    return is_array($filters[$filterKey]) && in_array($value, $filters[$filterKey]);
}

// Helper function to build sort URL
function buildSortUrl($field) {
    global $filters, $sortField, $sortDir;

    // Toggle direction if clicking on currently sorted column
    $newDir = 'asc';
    if ($sortField === $field && $sortDir === 'asc') {
        $newDir = 'desc';
    }

    return buildFilterUrl('sort', $field . '-' . $newDir, 'add');
}

// Helper function to get sort indicator
function getSortIndicator($field) {
    global $sortField, $sortDir;

    if ($sortField !== $field) {
        return '<i class="bi bi-arrow-down-up opacity-25 ms-1"></i>';
    }

    if ($sortDir === 'asc') {
        return '<i class="bi bi-arrow-up ms-1"></i>';
    }

    return '<i class="bi bi-arrow-down ms-1"></i>';
}

// Load filter options (not strictly needed anymore since we use facet data directly, but kept for consistency)
$deliveryMethods = getDeliveryMethods();
$topics = getAllTopics();
$audiences = getAllAudiences();
$levels = getLevels();

// Get all platforms using the existing function
$platforms = getPlatformNames();
sort($platforms);

// Build corporate partners list with counts
$partnersList = [];
foreach ($facetCounts['corporate_partners'] as $partnerId => $count) {
    if (empty($partnerId)) continue;
    $partner = getPartnerById($partnerId);
    if ($partner) {
        $partnersList[] = [
            'id' => $partnerId,
            'name' => $partner['name'],
            'count' => $count
        ];
    }
}
usort($partnersList, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Build development partners list with counts
$devPartnersList = [];
foreach ($facetCounts['dev_partners'] as $devPartnerId => $count) {
    if (isset($devPartnersData[$devPartnerId])) {
        $devPartnersList[] = [
            'id' => $devPartnerId,
            'name' => $devPartnersData[$devPartnerId]['name'],
            'count' => $count
        ];
    }
}
usort($devPartnersList, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

?>
<?php getHeader() ?>
<title>Course Map - Learning System</title>
<?php getScripts() ?>
<style>
        .filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .filter-badge.active {
            background: var(--bs-primary);
            color: var(--bs-white);
            border: 1px solid var(--bs-primary);
        }
        .filter-badge.active:hover {
            opacity: 0.9;
        }
        .filter-badge.inactive {
            background: var(--bs-body-bg);
            color: var(--bs-secondary-color);
            border: 1px solid var(--bs-border-color);
        }
        .filter-badge.inactive:hover {
            background: var(--bs-secondary-bg);
        }
        details summary {
            list-style: none;
            cursor: pointer;
        }
        details summary::-webkit-details-marker {
            display: none;
        }
        details summary::marker {
            display: none;
        }
        .table-responsive {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .table thead.sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--bs-tertiary-bg);
        }
        .table thead th {
            background: var(--bs-tertiary-bg);
            border-bottom: 2px solid var(--bs-border-color);
        }
        .table thead th a {
            display: block;
            white-space: nowrap;
            color: var(--bs-emphasis-color) !important;
        }
        .table thead th a:hover {
            color: var(--bs-primary) !important;
        }
    </style>

<body>
<?php getNavigation() ?>

<div id="course-map">
<div class="container-fluid">
<div class="row justify-content-md-center">
<div class="col-md-12">
    <div class="mt-4 mb-5 px-3">
        <div class="row gx-4">
            <!-- Filters Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div style="top: 1rem;">
                    <h5 class="mb-3">Filter Courses</h5>

                    <!-- Search Box -->
                    <div class="search-box">
                        <form method="get" action="course-map.php">
                            <?php foreach ($filters as $key => $val): ?>
                                <?php if ($key !== 'search' && !empty($val)): ?>
                                    <?php if (is_array($val)): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars(implode(',', $val)) ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       placeholder="Search courses..."
                                       value="<?= htmlspecialchars($filters['search']) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Binary Filters -->
                    <div class="bg-body-secondary rounded p-3 mb-3">
                        <h6 class="fw-semibold mb-3 text-uppercase small text-secondary">Quick Filters</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="openaccess"
                                   <?= $filters['openaccess'] ? 'checked' : '' ?>
                                   onchange="window.location.href='<?= buildFilterUrl('openaccess', 'true', 'toggle') ?>'">
                            <label class="form-check-label" for="openaccess">
                                Open Access
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="hubonly"
                                   <?= $filters['hubonly'] ? 'checked' : '' ?>
                                   onchange="window.location.href='<?= buildFilterUrl('hubonly', 'Yes', 'toggle') ?>'">
                            <label class="form-check-label" for="hubonly">
                                LearningHUB Only
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="moodle"
                                   <?= $filters['moodle'] ? 'checked' : '' ?>
                                   onchange="window.location.href='<?= buildFilterUrl('moodle', 'Yes', 'toggle') ?>'">
                            <label class="form-check-label" for="moodle">
                                Moodle Courses
                            </label>
                        </div>
                    </div>

                    <!-- Topics Filter -->
                    <details open class="mb-3">
                        <summary class="fw-bold mb-2">Topics</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php
                            // Get actual topics from facet counts and sort
                            $actualTopics = array_keys($facetCounts['topics']);
                            sort($actualTopics);
                            foreach ($actualTopics as $topic):
                                if (empty($topic)) continue;
                                $count = $facetCounts['topics'][$topic];
                                $isActive = isFilterActive('topics', $topic);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('topics', $topic, 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($topic) ?>
                                        <span class="ms-2 opacity-75 small">(<?= $count ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Audiences Filter -->
                    <details open class="mb-3">
                        <summary class="fw-bold mb-2">Audiences</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php
                            // Only show the 4 valid audiences
                            $validAudiences = ['All Employees', 'People Leaders', 'Senior Leaders', 'Executive'];
                            foreach ($validAudiences as $audience):
                                $count = $facetCounts['audiences'][$audience] ?? 0;
                                if ($count === 0) continue;
                                $isActive = isFilterActive('audiences', $audience);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('audiences', $audience, 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($audience) ?>
                                        <span class="ms-2 opacity-75 small">(<?= $count ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Delivery Methods Filter -->
                    <details open class="mb-3">
                        <summary class="fw-bold mb-2" style="cursor: pointer;">Delivery Methods</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php
                            $actualDelivery = array_keys($facetCounts['delivery']);
                            sort($actualDelivery);
                            foreach ($actualDelivery as $method):
                                if (empty($method)) continue;
                                $count = $facetCounts['delivery'][$method];
                                $isActive = isFilterActive('delivery', $method);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('delivery', $method, 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($method) ?>
                                        <span class="ms-2 opacity-75 small">(<?= $count ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Corporate Partners Filter -->
                    <details class="mb-3">
                        <summary class="fw-bold mb-2" style="cursor: pointer;">Corporate Partners</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php foreach ($partnersList as $partnerData):
                                $isActive = isFilterActive('corporate_partners', $partnerData['id']);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('corporate_partners', $partnerData['id'], 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($partnerData['name']) ?>
                                        <span class="filter-count">(<?= $partnerData['count'] ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Development Partners Filter -->
                    <details class="mb-3">
                        <summary class="fw-bold mb-2" style="cursor: pointer;">Development Partners</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php foreach ($devPartnersList as $devPartnerData):
                                $isActive = isFilterActive('dev_partners', $devPartnerData['id']);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('dev_partners', $devPartnerData['id'], 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($devPartnerData['name']) ?>
                                        <span class="filter-count">(<?= $devPartnerData['count'] ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Platforms Filter -->
                    <details class="mb-3">
                        <summary class="fw-bold mb-2" style="cursor: pointer;">Platforms</summary>
                        <div class="bg-body-secondary rounded p-3">
                            <?php foreach ($platforms as $platform):
                                if (empty($platform)) continue;
                                $count = $facetCounts['platforms'][$platform] ?? 0;
                                if ($count === 0) continue;
                                $isActive = isFilterActive('platforms', $platform);
                            ?>
                                <div class="d-inline-block m-1">
                                    <a href="<?= buildFilterUrl('platforms', $platform, 'toggle') ?>"
                                       class="filter-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= htmlspecialchars($platform) ?>
                                        <span class="ms-2 opacity-75 small">(<?= $count ?>)</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <!-- Clear All Filters -->
                    <?php
                    $hasActiveFilters = false;
                    foreach ($filters as $key => $val) {
                        if (($key !== 'sort' && $key !== 'status') && (!empty($val))) {
                            $hasActiveFilters = true;
                            break;
                        }
                    }
                    if ($hasActiveFilters):
                    ?>
                        <div class="mt-3">
                            <a href="course-map.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle"></i> Clear All Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Results Column -->
            <div class="col-md-9 col-lg-10">
                <!-- Active Filters Bar -->
                <?php if ($hasActiveFilters): ?>
                    <div class="bg-primary-subtle border border-primary-subtle rounded p-3 mb-4">
                        <strong>Active Filters:</strong>
                        <div class="mt-2">
                            <?php foreach ($filters as $key => $values): ?>
                                <?php if (is_array($values) && !empty($values)): ?>
                                    <?php foreach ($values as $value): ?>
                                        <span class="badge text-bg-primary me-2 mb-2">
                                            <?php
                                            $displayValue = $value;
                                            if ($key === 'corporate_partners') {
                                                $partner = getPartnerById($value);
                                                $displayValue = $partner ? $partner['name'] : $value;
                                            } elseif ($key === 'dev_partners') {
                                                $displayValue = $devPartnersData[$value]['name'] ?? $value;
                                            }
                                            echo htmlspecialchars($displayValue);
                                            ?>
                                            <a href="<?= buildFilterUrl($key, $value, 'remove') ?>"
                                               class="link-light ms-1 text-decoration-none">×</a>
                                        </span>
                                    <?php endforeach; ?>
                                <?php elseif (!is_array($values) && $values !== '' && $key !== 'sort' && $key !== 'status'): ?>
                                    <span class="badge text-bg-primary me-2 mb-2">
                                        <?= htmlspecialchars(ucfirst($key)) ?>: <?= htmlspecialchars($values) ?>
                                        <a href="<?= buildFilterUrl($key, '', 'remove') ?>"
                                           class="link-light ms-1 text-decoration-none">×</a>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Results Header -->
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <h4 class="mb-0">Courses</h4>
                        <small class="text-muted"><?= count($filteredCourses) ?> courses found</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') ?>export=csv"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                        <select class="form-select form-select-sm" onchange="window.location.href=this.value">
                            <option value="<?= buildFilterUrl('sort', 'name-asc', 'add') ?>"
                                    <?= ($sortField === 'name' && $sortDir === 'asc') ? 'selected' : '' ?>>
                                Name (A-Z)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'name-desc', 'add') ?>"
                                    <?= ($sortField === 'name' && $sortDir === 'desc') ? 'selected' : '' ?>>
                                Name (Z-A)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'topic-asc', 'add') ?>"
                                    <?= ($sortField === 'topic' && $sortDir === 'asc') ? 'selected' : '' ?>>
                                Topic (A-Z)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'audience-asc', 'add') ?>"
                                    <?= ($sortField === 'audience' && $sortDir === 'asc') ? 'selected' : '' ?>>
                                Audience (A-Z)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'delivery-asc', 'add') ?>"
                                    <?= ($sortField === 'delivery' && $sortDir === 'asc') ? 'selected' : '' ?>>
                                Delivery (A-Z)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'platform-asc', 'add') ?>"
                                    <?= ($sortField === 'platform' && $sortDir === 'asc') ? 'selected' : '' ?>>
                                Platform (A-Z)
                            </option>
                            <option value="<?= buildFilterUrl('sort', 'dateadded-desc', 'add') ?>"
                                    <?= ($sortField === 'dateadded') ? 'selected' : '' ?>>
                                Recently Updated
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Course Results -->
                <?php if (empty($filteredCourses)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No courses found matching your filters. Try adjusting your search criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle">
                            <thead class="sticky-top">
                                <tr>
                                    <th scope="col" style="width: 40%;">
                                        <a href="<?= buildSortUrl('name') ?>" class="text-decoration-none">
                                            Course Name <?= getSortIndicator('name') ?>
                                        </a>
                                    </th>
                                    <th scope="col" style="width: 14%;">
                                        <a href="<?= buildSortUrl('topic') ?>" class="text-decoration-none">
                                            Topic <?= getSortIndicator('topic') ?>
                                        </a>
                                    </th>
                                    <th scope="col" style="width: 14%;">
                                        <a href="<?= buildSortUrl('audience') ?>" class="text-decoration-none">
                                            Audience <?= getSortIndicator('audience') ?>
                                        </a>
                                    </th>
                                    <th scope="col" style="width: 10%;">
                                        <a href="<?= buildSortUrl('delivery') ?>" class="text-decoration-none">
                                            Delivery <?= getSortIndicator('delivery') ?>
                                        </a>
                                    </th>
                                    <th scope="col" style="width: 14%;">
                                        <a href="<?= buildSortUrl('platform') ?>" class="text-decoration-none">
                                            Platform <?= getSortIndicator('platform') ?>
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredCourses as $course): ?>
                                    <tr>
                                        <td>
                                            <a href="course.php?courseid=<?= urlencode($course[0]) ?>" class="text-decoration-none fw-semibold">
                                                <?= htmlspecialchars($course[2]) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($course[38])): ?>
                                                <?= htmlspecialchars($course[38]) ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($course[39])): ?>
                                                <?= htmlspecialchars($course[39]) ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($course[21])): ?>
                                                <?= htmlspecialchars($course[21]) ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($course[52])): ?>
                                                <small><?= htmlspecialchars($course[52]) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
