<?php
opcache_reset();
$path = '../inc/lsapp.php';
require($path);
require('../inc/Parsedown.php');
$Parsedown = new Parsedown();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if(canACcess()) {
    $action = $_POST['action'] ?? '';
    $categoriesFile = '../data/course-change-guidance.json';

    // Ensure file exists
    if (!file_exists($categoriesFile)) {
        file_put_contents($categoriesFile, json_encode([]));
    }

    $categories = json_decode(file_get_contents($categoriesFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error decoding JSON: " . json_last_error_msg());
    }

    if ($action === 'create' || $action === 'update') {
        $category = $_POST['category'] ?? '';
        $guidance = $_POST['guidance'] ?? '';

        if (empty($category)) {
            die("Category is required.");
        }

        // Update or create entry
        $found = false;
        foreach ($categories as &$cat) {
            if ($cat['category'] === $category) {
                $cat['guidance'] = $guidance;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $categories[] = [
                'category' => $category,
                'guidance' => $guidance
            ];
        }
    } elseif ($action === 'delete') {
        $category = $_POST['category'] ?? '';
        $categories = array_filter($categories, function($cat) use ($category) {
            return $cat['category'] !== $category;
        });
    }

    // Save changes
    file_put_contents($categoriesFile, json_encode(array_values($categories), JSON_PRETTY_PRINT));
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}
}

// Load current categories
$categoriesFile = '../data/course-change-guidance.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
if (json_last_error() !== JSON_ERROR_NONE) {
    $categories = [];
}
?>
<?php if(canACcess()): ?>

<?php getHeader() ?>

<title>Change Request Dashboard</title>

<?php getScripts() ?>
</body>
<?php getNavigation() ?>

<div class="container">
    <div class="row justify-content-md-center">
        <div class="col-md-6">

    <!-- Create/Update Form -->
    <div class="card mb-4">
            <div class="card-header">Add or Update Guidance</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" required>
                    </div>
                    <div class="mb-3">
                        <label for="guidance" class="form-label">Guidance (Markdown)</label>
                        <textarea class="form-control" id="guidance" name="guidance" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        </div>
        <div class="col-md-6">
        <h2 class="mb-4">Existing Guidance</h2>
        <div class="accordion" id="guidanceList">
            <?php foreach ($categories as $index => $cat): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?= $index; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index; ?>" aria-expanded="false" aria-controls="collapse<?= $index; ?>">
                            <?= htmlspecialchars($cat['category']); ?>
                        </button>
                    </h2>
                    <div id="collapse<?= $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $index; ?>" data-bs-parent="#guidanceList">
                        <div class="accordion-body">
                            <p><strong>Guidance:</strong></p>
                            <div class="p-3 rounded-3 bg-light-subtle">
                                <?= $Parsedown->text(htmlspecialchars($cat['guidance'])); ?>
                            </div>

                            <!-- Update Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="category" value="<?= htmlspecialchars($cat['category']); ?>">
                                <div class="mb-3">
                                    <label for="guidance" class="form-label">Update Guidance</label>
                                    <textarea class="form-control" name="guidance" rows="3" required><?= htmlspecialchars($cat['guidance']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-warning">Update</button>
                            </form>

                            <!-- Delete Form -->
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category" value="<?= htmlspecialchars($cat['category']); ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php require('../templates/javascript.php') ?>
<?php require('../templates/footer.php') ?>
<?php endif ?>