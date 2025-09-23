<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path);
$partnersFile = "../data/partners.json";
$partners = file_exists($partnersFile) ? json_decode(file_get_contents($partnersFile), true) : [];
$partnerSlug = $_GET['slug'] ?? null;
$partner = null;

if ($partnerSlug) {
    foreach ($partners as $p) {
        if ($p['slug'] == $partnerSlug) {
            $partner = $p;
            break;
        }
    }
}

$pcourses = $partner ? getCoursesByPartnerName($partner["name"]) : [];

// Separate and sort courses
$activeCourses = [];
$inactiveCourses = [];

foreach ($pcourses as $course) {
    if ($course[1] === 'Active') {
        $activeCourses[] = $course;
    } else {
        $inactiveCourses[] = $course;
    }
}

// Sort by creation date (field 13) - most recent first
usort($activeCourses, function($a, $b) {
    return strcmp($b[13], $a[13]); // Descending order (newest first)
});

usort($inactiveCourses, function($a, $b) {
    return strcmp($b[13], $a[13]); // Descending order (newest first)
});
?>

<?php if(canACcess() && $partner): ?>

<?php getHeader() ?>
<title><?php echo htmlspecialchars($partner["name"]); ?> - Partner Details</title>

<?php getScripts() ?>
<body>
<?php getNavigation() ?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Corp. Learning Partners</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($partner["name"]); ?></li>
        </ol>
    </nav>

    <div class="row">
    <div class="col-md-6">
    <div class="card">
        <div class="card-header">
            <div class="mb-0">
            <?php 
            $statustype = 'primary';
            if($partner["status"] != 'active') $statustype = 'warning'; 
            ?>
                <span class="badge bg-<?= $statustype ?>">
                    <?php echo htmlspecialchars($partner["status"]); ?>
                </span>
            </div>
            <h2 class="mb-0"> <?php echo htmlspecialchars($partner["name"]); ?> </h2>
        </div>
        <div class="card-body">
            <h5>Description:</h5>
            <p><?php echo nl2br(htmlspecialchars($partner["description"])); ?></p>
            
            <?php if (!empty($partner["employee_facing_contact"])): ?>
            <div class="alert alert-secondary">
                <h6 class="alert-heading">Employee Support Contact</h6>
                <?php if ($partner["employee_facing_contact"] === "CRM"): ?>
                    <p class="mb-0">Employees should use the CRM system for support with courses from this partner.</p>
                <?php elseif (filter_var($partner["employee_facing_contact"], FILTER_VALIDATE_EMAIL)): ?>
                    <p class="mb-0">Contact: <a href="mailto:<?php echo htmlspecialchars($partner["employee_facing_contact"]); ?>"><?php echo htmlspecialchars($partner["employee_facing_contact"]); ?></a></p>
                <?php else: ?>
                    <p class="mb-0"><?php echo htmlspecialchars($partner["employee_facing_contact"]); ?></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <h6 class="alert-heading">⚠️ Missing Employee Support Contact</h6>
                <p class="mb-0">This partner does not have an employee-facing contact configured. Employees will not know how to get support for courses from this partner.</p>
            </div>
            <?php endif; ?>
            
            <div class="my-3">
                <a href="<?php echo htmlspecialchars($partner["link"]); ?>" class="" target="_blank">
                    LearningHUB
                </a> | 
                <a href="https://gww.bcpublicservice.gov.bc.ca/learning/hub/partners/dashboard.php?partnerid=<?php echo htmlspecialchars($partner["id"]); ?>" class="" target="_blank">
                    Partner Admin Panel
                </a>
            </div>
            <h5>Administrative Contacts:</h5>
            <?php if (!empty($partner["contacts"])): ?>
            <ul class="list-group">
                <?php foreach ($partner["contacts"] as $contact): ?>
                    <li class="list-group-item">

                        <div>
                            <?php echo htmlspecialchars($contact["name"]); ?> 
                            &lt;<?php echo htmlspecialchars($contact["email"]); ?>&gt;
                            (<?php echo htmlspecialchars($contact["idir"]); ?>)
                        </div>
                        <div>
                            Title: <?php echo htmlspecialchars($contact["title"]); ?>
                        </div>
                        <div>
                            Role: <?php echo htmlspecialchars($contact["role"]); ?>
                        </div>
                        <div>Added: <?php echo htmlspecialchars($contact["added_at"]); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <div class="alert alert-warning">
                    There is no contact listed for this partner!
                </div>
            <?php endif ?>
            
            <?php if (!empty($partner["contact_history"])): ?>
            <details class="mt-3">
                <summary>Contact History</summary>
                <?php foreach ($partner["contact_history"] as $index => $contact): ?>
                <div class="mb-2 p-3 bg-light-subtle rounded-3">
                    <div>
                        <?php echo htmlspecialchars($contact["name"]); ?> 
                        &lt;<?php echo htmlspecialchars($contact["email"]); ?>&gt;
                        (<?php echo htmlspecialchars($contact["idir"]); ?>)
                    </div>
                    <div>
                        Title: <?php echo htmlspecialchars($contact["title"]); ?>
                    </div>
                    <div>
                        Role: <?php echo htmlspecialchars($contact["role"]); ?>
                    </div>
                    <div>Added: <?php echo htmlspecialchars($contact["added_at"]); ?></div>
                    <div>Retired: <?php echo htmlspecialchars($contact["removed_at"]); ?></div>
                </div>
                <?php endforeach; ?>
            </details>
            <?php endif; ?>
            
            <div class="mt-3">
                <a href="form.php?id=<?php echo $partner['id']; ?>" class="btn btn-dark">
                    Edit partner info
                </a>
            </div>
        </div>
    </div>
    </div>

    <?php if (!empty($pcourses)): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Courses Offered</h5>
                <input class="search form-control" placeholder="Search courses..." />
            </div>
            <div class="card-body" id="course-list">
                
                <?php if (!empty($activeCourses)): ?>
                <div class="mb-4">
                    <h6 class="text-success">Active Courses (<span id="active-count"><?= count($activeCourses) ?></span>)</h6>
                    <div class="list">
                        <?php foreach ($activeCourses as $course): ?>
                        <div class="mb-2 p-2 bg-light-subtle rounded course-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="course-name">
                                        <a href="/lsapp/course.php?courseid=<?php echo htmlspecialchars($course[0]); ?>">
                                            <?php echo htmlspecialchars($course[2] ?? 'Untitled Course'); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="hub-status ms-2">
                                    <?php if ($course[53] == 'Yes' || $course[53] == 1): ?>
                                        <span class="badge bg-success">Learning<strong>HUB</strong></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Learning<strong>HUB</strong></span>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($inactiveCourses)): ?>
                <div class="mb-4">
                    <h6 class="text-secondary">Inactive Courses (<span id="inactive-count"><?= count($inactiveCourses) ?></span>)</h6>
                    <div class="list">
                        <?php foreach ($inactiveCourses as $course): ?>
                        <div class="mb-2 p-2 bg-light-subtle rounded course-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="course-name">
                                        <a href="/lsapp/course.php?courseid=<?php echo htmlspecialchars($course[0]); ?>">
                                            <?php echo htmlspecialchars($course[2] ?? 'Untitled Course'); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="hub-status ms-2">
                                    <?php if ($course[53] == 'Yes' || $course[53] == 1): ?>
                                        <span class="badge bg-success">Learning<strong>HUB</strong></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Learning<strong>HUB</strong></span>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($activeCourses) && empty($inactiveCourses)): ?>
                <div class="alert alert-info">
                    No courses found for this partner.
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<?php endif ?>

<?php require('../templates/javascript.php') ?>

<script>
$(document).ready(function(){
    // Initialize List.js for course search
    var options = {
        valueNames: ['course-name']
    };
    var courseList = new List('course-list', options);
    
    // Custom search to handle both active and inactive sections
    $('.search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        if (searchTerm === '') {
            // Show all courses when search is empty
            $('.course-item').show();
        } else {
            // Hide/show courses based on search term
            $('.course-item').each(function() {
                var courseName = $(this).find('.course-name').text().toLowerCase();
                
                if (courseName.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        // Update section headings to show/hide if no results
        updateSectionVisibility();
    });
    
    function updateSectionVisibility() {
        // Check if any active courses are visible
        var visibleActiveCourses = $('.mb-4:first .course-item:visible').length;
        if (visibleActiveCourses === 0) {
            $('.mb-4:first h6').hide();
        } else {
            $('.mb-4:first h6').show();
            $('#active-count').text(visibleActiveCourses);
        }
        
        // Check if any inactive courses are visible
        var visibleInactiveCourses = $('.mb-4:last .course-item:visible').length;
        if (visibleInactiveCourses === 0) {
            $('.mb-4:last h6').hide();
        } else {
            $('.mb-4:last h6').show();
            $('#inactive-count').text(visibleInactiveCourses);
        }
    }
});
</script>

<?php require('../templates/footer.php') ?>
