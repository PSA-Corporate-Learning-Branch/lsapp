<?php require('inc/lsapp.php') ?>
<?php 
$platformId = (isset($_GET['id'])) ? $_GET['id'] : '';

// Load platforms data
$platforms = getAllPlatforms();

// Find the specific platform
$currentPlatform = null;
foreach ($platforms as $platform) {
    if ($platform['id'] === strtolower($platformId)) {
        $currentPlatform = $platform;
        break;
    }
}
?>

<?php getHeader() ?>
<title><?= $currentPlatform ? htmlspecialchars($currentPlatform['name']) : 'Platform Not Found' ?> - LSApp</title>
<?php getScripts() ?>
<body class="bg-light-subtle">
<?php getNavigation() ?>

<div class="container-fluid">
<div class="row justify-content-md-center mb-3">

<?php if($currentPlatform): ?>
<div class="col-md-6">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/lsapp/">Home</a></li>
        <li class="breadcrumb-item"><a href="/lsapp/platforms.php">Platforms</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($currentPlatform['name']) ?></li>
    </ol>
</nav>

<div class="card">
<div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="card-title mb-0"><?= htmlspecialchars($currentPlatform['name']) ?></h1>
        <div>
            <?php if(isAdmin()): ?>
            <a href="platform-update.php?id=<?= urlencode($currentPlatform['id']) ?>" class="btn btn-secondary">Edit</a>
            <?php endif ?>
            <?php if(isSuper()): ?>
            <form method="post" action="platform-delete.php" style="display: inline;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($currentPlatform['id']) ?>">
                <input type="submit" value="Delete" class="btn btn-sm btn-danger del">
            </form>
            <?php endif ?>
        </div>
    </div>
</div>
<div class="card-body">
    <p class="lead"><?= htmlspecialchars($currentPlatform['description']) ?></p>
    
    <?php if (!empty($currentPlatform['link'])): ?>
    <div class="mb-4">
        <a href="<?= htmlspecialchars($currentPlatform['link']) ?>" target="_blank">
            Visit Platform Website →
        </a>
    </div>
    <?php endif; ?>
    
    <hr>
    
    <h3>Courses on this Platform</h3>
    
    <?php
    // Get courses that use this platform
    $coursesFile = fopen('data/courses.csv', 'r');
    $headers = fgetcsv($coursesFile);
    $platformIndex = array_search('Platform', $headers);
    $activeCourses = [];
    $inactiveCourses = [];
    
    while ($row = fgetcsv($coursesFile)) {
        if (isset($row[$platformIndex]) && $row[$platformIndex] === $currentPlatform['name']) {
            if ($row[1] === 'Active') {
                $activeCourses[] = $row;
            } else {
                $inactiveCourses[] = $row;
            }
        }
    }
    fclose($coursesFile);
    
    // Reverse arrays to show most recently added first (assuming they're added to the end of the CSV)
    $activeCourses = array_reverse($activeCourses);
    $inactiveCourses = array_reverse($inactiveCourses);
    
    $totalCourses = count($activeCourses) + count($inactiveCourses);
    
    // Count LearningHUB included courses
    $hubCourses = 0;
    $allCourses = array_merge($activeCourses, $inactiveCourses);
    foreach ($allCourses as $course) {
        if (isset($course[53]) && ($course[53] === 'Yes' || $course[53] === 'on' || $course[53] === '1')) {
            $hubCourses++;
        }
    }
    
    if ($totalCourses > 0):
    ?>
    
    <div id="courselist">
        <input class="search form-control mb-3" placeholder="Search all courses...">
        
        <p class="text-muted mb-3">
            Active: <span class="badge badge-success" id="active-count"><?= count($activeCourses) ?></span>
            Inactive: <span class="badge badge-secondary" id="inactive-count"><?= count($inactiveCourses) ?></span>
            LearningHUB: <span class="badge badge-info" id="hub-count"><?= $hubCourses ?></span>
            Total: <span class="badge badge-primary"><?= $totalCourses ?></span>
        </p>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th class="sort" data-sort="status" style="cursor: pointer;">
                        Status <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                    </th>
                    <th class="sort" data-sort="name" style="cursor: pointer;">
                        Course Name <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                    </th>
                    <th class="sort" data-sort="hub" style="cursor: pointer;">
                        LearningHUB <i class="fas fa-sort" style="font-size: 0.7em; opacity: 0.6;"></i>
                    </th>
                </tr>
            </thead>
            <tbody class="list">
                <?php 
                // Combine all courses and sort by status (Active first)
                $allCourses = array_merge($activeCourses, $inactiveCourses);
                foreach ($allCourses as $course): 
                    $isActive = $course[1] === 'Active';
                    $hubInclude = isset($course[53]) && ($course[53] === 'Yes' || $course[53] === 'on' || $course[53] === '1');
                ?>
                <tr>
                    <td class="status">
                        <?php if ($isActive): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="name">
                        <a href="/lsapp/course.php?courseid=<?= urlencode($course[0]) ?>">
                            <?= htmlspecialchars($course[2]) ?>
                        </a>
                    </td>
                    <td class="hub">
                        <?php if ($hubInclude): ?>
                            <span class="badge badge-info">Learning<span class="fw-bold">HUB</span></span>
                        <?php else: ?>
                            <span class="text-muted">Not included</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php else: ?>
    <div class="alert alert-info">
        No courses are currently using this platform.
    </div>
    <?php endif; ?>
    
</div>
</div>

</div>

<?php else: ?>
<div class="col-md-6">
    <div class="card">
        <div class="card-body">
            <h2>Platform Not Found</h2>
            <p>The requested platform could not be found.</p>
            <a href="/lsapp/platforms.php" class="btn btn-primary">Back to Platforms</a>
        </div>
    </div>
</div>
<?php endif ?>

</div>
</div>

<?php require('templates/javascript.php') ?>

<script>
$(document).ready(function(){
    <?php if($totalCourses > 0): ?>
    var options = {
        valueNames: [ 'status', 'name', 'hub' ]
    };
    var courseList = new List('courselist', options);
    
    // Store original counts
    var totalActive = <?= count($activeCourses) ?>;
    var totalInactive = <?= count($inactiveCourses) ?>;
    
    // Function to update counts based on search
    function updateCounts() {
        var visibleActive = 0;
        var visibleInactive = 0;
        var visibleHub = 0;
        
        // Count visible rows
        $('#courselist tbody tr').each(function() {
            if ($(this).is(':visible')) {
                var statusText = $(this).find('.status .badge').text();
                var hubText = $(this).find('.hub .badge').text();
                
                if (statusText === 'Active') {
                    visibleActive++;
                } else if (statusText === 'Inactive') {
                    visibleInactive++;
                }
                
                if (hubText && hubText.includes('HUB')) {
                    visibleHub++;
                }
            }
        });
        
        // Update count displays
        $('#active-count').text(visibleActive);
        $('#inactive-count').text(visibleInactive);
        $('#hub-count').text(visibleHub);
    }
    
    // Update counts on search
    courseList.on('searchComplete', updateCounts);
    courseList.on('filterComplete', updateCounts);
    
    // Update counts on search input
    $('.search').on('keyup', function() {
        setTimeout(updateCounts, 10);
    });
    
    <?php endif; ?>
});
</script>

<?php require('templates/footer.php') ?>