<?php
/**
 * CSV Import for Newsletter Subscribers
 * Allows bulk import of email addresses from a CSV file
 * Handles duplicates gracefully - only adds new addresses
 */
require('../inc/lsapp.php');

// Check if user is admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

// Get newsletter ID
$newsletterId = isset($_GET['newsletter_id']) ? (int)$_GET['newsletter_id'] : null;

if (!$newsletterId) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get newsletter details
$stmt = $db->prepare("SELECT * FROM newsletters WHERE id = ?");
$stmt->execute([$newsletterId]);
$newsletter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$newsletter) {
    header('Location: index.php');
    exit();
}

$message = '';
$messageType = '';
$importStats = null;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];

        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("File is too large. Maximum size is 5MB.");
        }

        // Validate file type
        $allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes) && !str_ends_with($file['name'], '.csv')) {
            throw new Exception("Invalid file type. Please upload a CSV file.");
        }

        // Open and read CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            throw new Exception("Could not open CSV file");
        }

        $stats = [
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'errors' => []
        ];

        $now = date('Y-m-d H:i:s');
        $rowNumber = 0;

        // Skip header row if present
        $firstRow = fgetcsv($handle);
        if ($firstRow) {
            // Check if first row looks like a header
            // Strip UTF-8 BOM if present
            $firstCell = strtolower(trim($firstRow[0]));
            $firstCell = str_replace("\xEF\xBB\xBF", '', $firstCell); // Remove UTF-8 BOM
            if (in_array($firstCell, ['email', 'email address', 'e-mail'])) {
                // This is a header row, skip it
            } else {
                // Process first row as data
                $rowNumber = 1;
                $email = trim($firstRow[0]);

                if (empty($email)) {
                    $stats['skipped']++;
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stats['invalid']++;
                    if (count($stats['errors']) < 10) {
                        $stats['errors'][] = "Row $rowNumber: Invalid email '$email'";
                    }
                } else {
                    $email = strtolower($email);
                    $stats['total']++;

                    // Check if email exists
                    $checkStmt = $db->prepare("SELECT email, status FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                    $checkStmt->execute([$email, $newsletterId]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        if ($existing['status'] !== 'active') {
                            // Reactivate
                            $updateStmt = $db->prepare("
                                UPDATE subscriptions
                                SET status = 'active', updated_at = ?, source = 'csv'
                                WHERE email = ? AND newsletter_id = ?
                            ");
                            $updateStmt->execute([$now, $email, $newsletterId]);

                            // Log to history
                            $historyStmt = $db->prepare("
                                INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                                VALUES (?, 'subscribe', ?, 'csv-import', ?, ?)
                            ");
                            $historyStmt->execute([$email, $now, json_encode(['source' => 'csv', 'reactivated' => true]), $newsletterId]);

                            $stats['updated']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } else {
                        // Add new
                        $insertStmt = $db->prepare("
                            INSERT INTO subscriptions (email, status, created_at, updated_at, source, newsletter_id)
                            VALUES (?, 'active', ?, ?, 'csv', ?)
                        ");
                        $insertStmt->execute([$email, $now, $now, $newsletterId]);

                        // Log to history
                        $historyStmt = $db->prepare("
                            INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                            VALUES (?, 'subscribe', ?, 'csv-import', ?, ?)
                        ");
                        $historyStmt->execute([$email, $now, json_encode(['source' => 'csv']), $newsletterId]);

                        $stats['added']++;
                    }
                }
            }
        }

        // Process remaining rows
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Get first column (email)
            $email = isset($row[0]) ? trim($row[0]) : '';

            // Skip empty rows
            if (empty($email)) {
                $stats['skipped']++;
                continue;
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['invalid']++;
                if (count($stats['errors']) < 10) {
                    $stats['errors'][] = "Row $rowNumber: Invalid email '$email'";
                }
                continue;
            }

            $email = strtolower($email);
            $stats['total']++;

            // Check if email exists for this newsletter
            $checkStmt = $db->prepare("SELECT email, status FROM subscriptions WHERE email = ? AND newsletter_id = ?");
            $checkStmt->execute([$email, $newsletterId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['status'] !== 'active') {
                    // Reactivate unsubscribed email
                    $updateStmt = $db->prepare("
                        UPDATE subscriptions
                        SET status = 'active', updated_at = ?, source = 'csv'
                        WHERE email = ? AND newsletter_id = ?
                    ");
                    $updateStmt->execute([$now, $email, $newsletterId]);

                    // Log to history
                    $historyStmt = $db->prepare("
                        INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                        VALUES (?, 'subscribe', ?, 'csv-import', ?, ?)
                    ");
                    $historyStmt->execute([$email, $now, json_encode(['source' => 'csv', 'reactivated' => true]), $newsletterId]);

                    $stats['updated']++;
                } else {
                    // Already active, skip
                    $stats['skipped']++;
                }
            } else {
                // Add new subscription
                $insertStmt = $db->prepare("
                    INSERT INTO subscriptions (email, status, created_at, updated_at, source, newsletter_id)
                    VALUES (?, 'active', ?, ?, 'csv', ?)
                ");
                $insertStmt->execute([$email, $now, $now, $newsletterId]);

                // Log to history
                $historyStmt = $db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, 'subscribe', ?, 'csv-import', ?, ?)
                ");
                $historyStmt->execute([$email, $now, json_encode(['source' => 'csv']), $newsletterId]);

                $stats['added']++;
            }
        }

        fclose($handle);

        $importStats = $stats;
        $message = "CSV import completed successfully!";
        $messageType = 'success';

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

?>
<?php getHeader() ?>
<title>Import Subscribers from CSV - <?php echo htmlspecialchars($newsletter['name']); ?></title>
<?php getScripts() ?>
<style>
.drop-zone {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}
.file-info {
    margin-top: 15px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 5px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('csv_file');
    const fileInfo = document.getElementById('file-info');
    const form = document.getElementById('import-form');

    // Click to upload
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileInfo(e.dataTransfer.files[0]);
        }
    });

    // File input change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            updateFileInfo(e.target.files[0]);
        }
    });

    function updateFileInfo(file) {
        fileInfo.innerHTML = `
            <strong>Selected file:</strong> ${file.name} (${(file.size / 1024).toFixed(2)} KB)
            <br><button type="submit" class="btn btn-primary mt-2">Upload and Import</button>
        `;
        fileInfo.style.display = 'block';
    }
});
</script>
</head>
<body>
<?php getNavigation() ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1>Import Subscribers from CSV</h1>
            <p class="text-secondary"><?php echo htmlspecialchars($newsletter['name']); ?></p>
            <div class="mb-3">
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert <?php
            if ($messageType === 'success') echo 'alert-success';
            elseif ($messageType === 'warning') echo 'alert-warning';
            else echo 'alert-danger';
        ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($importStats): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="card-title">Import Results</h3>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4><?php echo $importStats['total']; ?></h4>
                                <p class="text-secondary small mb-0">Total Processed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $importStats['added']; ?></h4>
                                <p class="small mb-0">New Subscribers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $importStats['updated']; ?></h4>
                                <p class="small mb-0">Reactivated</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning">
                            <div class="card-body text-center">
                                <h4><?php echo $importStats['skipped'] + $importStats['invalid']; ?></h4>
                                <p class="small mb-0">Skipped/Invalid</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importStats['errors'])): ?>
                    <div class="alert alert-warning mt-3">
                        <strong>Errors/Warnings:</strong>
                        <ul class="mb-0">
                            <?php foreach ($importStats['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                            <?php if (count($importStats['errors']) >= 10): ?>
                                <li><em>... and more (showing first 10 errors)</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Upload CSV File</h3>

                    <form id="import-form" method="post" enctype="multipart/form-data">
                        <div id="drop-zone" class="drop-zone">
                            <p class="mb-2"><strong>Click to browse</strong> or drag and drop your CSV file here</p>
                            <p class="text-secondary small mb-0">Maximum file size: 5MB</p>
                            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" style="display: none;" required>
                        </div>

                        <div id="file-info" class="file-info" style="display: none;"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-info-subtle">
                <div class="card-body">
                    <h5 class="card-title">CSV Format Requirements</h5>
                    <p class="small">Your CSV file should have:</p>
                    <ul class="small">
                        <li>Email addresses in the <strong>first column</strong></li>
                        <li>One email per row</li>
                        <li>Optional header row (will be skipped)</li>
                    </ul>

                    <p class="small mb-2"><strong>Example:</strong></p>
                    <pre class="small bg-light-subtle p-2 rounded">email
user1@example.com
user2@example.com
user3@example.com</pre>

                    <p class="small mb-0"><strong>Note:</strong> Duplicate emails will be skipped. Unsubscribed emails will be reactivated.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../templates/footer.php') ?>
