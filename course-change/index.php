<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path);

// Directory containing the JSON files
$directory = "requests";

// Get all JSON files
$files = glob("{$directory}/*.json");

// Initialize an array to store request data
$changeRequests = [];

foreach ($files as $file) {
    $changeData = json_decode(file_get_contents($file), true);

    if ($changeData) {
        $changeRequests[] = [
            'changeid' => $changeData['changeid'],
            'courseid' => $changeData['courseid'],
            'category' => $changeData['category'] ?? '',
            'assign_to' => $changeData['assign_to'],
            'progress' => $changeData['progress'],
            'urgent' => $changeData['urgent'] ? 'Yes' : 'No',
            'date_created' => date('Y-m-d H:i:s', $changeData['date_created']),
            'date_modified' => date('Y-m-d H:i:s', $changeData['date_modified']),
        ];
    }
}
?>

<?php if(canACcess()): ?>

<?php getHeader() ?>

<title>Change Request Dashboard</title>

<?php getScripts() ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>
</body>
<?php getNavigation() ?>

<div class="container">
    <div class="row justify-content-md-center">
        <div class="col">
        <h1>Change Requests Dashboard</h1>
        <div class="mb-4">Incomplete change requests</div>
        <?php if (!empty($changeRequests)): ?>
            <!-- Search and sort controls -->
            <div id="change-requests-list">
                <input class="search form-control mb-3" placeholder="Search Change Requests" />

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="category">Category</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="course">Course</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="urgent">Urgent</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="assigned">Assigned To</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="progress">Progress</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="date-created">Date Created</button></th>
                            <th><button class="btn btn-sm btn-secondary sort" data-sort="date-modified">Date Modified</button></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="list">
                        <?php foreach ($changeRequests as $request): ?>
                            <?php if($request['progress'] !== 'Closed'): ?>
                            <tr>
                                <td class="category"><?php echo htmlspecialchars($request['category'] ?? ''); ?></td>
                                <td class="course">
                                    <?php $deets = getCourse($request['courseid']); ?>
                                    <a href="/lsapp/course.php?courseid=<?php echo htmlspecialchars($request['courseid']); ?>">
                                        <?php echo htmlspecialchars($deets[2]); ?>
                                    </a>
                                </td>
                                <td class="urgent"><?php echo htmlspecialchars($request['urgent']); ?></td>
                                <td class="assigned"><?php echo htmlspecialchars($request['assign_to']); ?></td>
                                <td class="progressstat"><?php echo htmlspecialchars($request['progress'] ?? 'Draft'); ?></td>
                                <td class="date-created"><?php echo htmlspecialchars($request['date_created']); ?></td>
                                <td class="date-modified"><?php echo htmlspecialchars($request['date_modified']); ?></td>
                                <td>
                                    <a href="view.php?courseid=<?php echo htmlspecialchars($request['courseid']); ?>&changeid=<?php echo htmlspecialchars($request['changeid']); ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No change requests found.</div>
        <?php endif; ?>
    </div>

    <script>
    var options = {
        valueNames: ['category', 'course', 'assigned', 'progressstat', 'urgent', 'date-created', 'date-modified']
    };

    var changeRequestsList = new List('change-requests-list', options);
</script>
    <?php endif ?>
</div>
</div>
</div>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>