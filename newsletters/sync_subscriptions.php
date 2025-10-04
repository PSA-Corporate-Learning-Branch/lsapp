<?php
/**
 * Web Interface for Manual Subscription Sync
 * Allows triggering the manage_subscriptions.php script from web UI
 */
require('../inc/lsapp.php');

// Get newsletter ID from query string
$newsletterId = isset($_GET['newsletter_id']) ? (int)$_GET['newsletter_id'] : 1;

// Database connection to get newsletter details
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get newsletter details
    $stmt = $db->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$newsletterId]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newsletter) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    handleDatabaseError($e);
}

// Check if sync was requested
$syncOutput = '';
$syncRequested = false;
$syncSuccess = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    // Validate CSRF token
    requireCsrfToken();

    $syncRequested = true;
    
    // Simple rate limiting check
    $rateLimitFile = __DIR__ . '/../data/last_sync_time.txt';
    $rateLimitMinutes = 1;
    
    if (file_exists($rateLimitFile)) {
        $lastSyncTime = (int)file_get_contents($rateLimitFile);
        $timeSinceLastSync = time() - $lastSyncTime;
        $limitSeconds = $rateLimitMinutes * 60;
        
        if ($timeSinceLastSync < $limitSeconds) {
            $remainingMinutes = ceil(($limitSeconds - $timeSinceLastSync) / 60);
            $errorMessage = "Rate limit: Please wait {$remainingMinutes} minute(s) before syncing again.";
            $syncSuccess = false;
        }
    }
    
    if (empty($errorMessage)) {
        // Update last sync time
        file_put_contents($rateLimitFile, time());
        
        // CRITICAL: Close database connection completely before running sync
        // This prevents SQLite database lock errors
        $db = null;
        unset($db);
        
        // With WAL mode, we need less delay but still give time for connection cleanup
        usleep(50000); // 50ms delay (reduced from 100ms since WAL mode is more concurrent)
        
        // Run the sync script as a separate process
        $startTime = microtime(true);

        try {
            // Use proc_open to execute safely without shell interpretation
            // This prevents command injection attacks
            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            // Build command array - no shell, direct execution
            $scriptPath = __DIR__ . '/manage_subscriptions.php';
            $newsletterIdSafe = (string)(int)$newsletterId;  // Ensure integer

            // Get PHP executable path safely
            $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : PHP_BINDIR . '/php';

            // Fallback for Windows if needed
            if (!file_exists($phpBinary)) {
                $phpBinary = 'E:\php-8.3.16\php.exe';
            }

            $process = proc_open(
                [$phpBinary, $scriptPath, $newsletterIdSafe],
                $descriptorspec,
                $pipes,
                __DIR__  // Working directory
            );

            if (!is_resource($process)) {
                throw new Exception("Failed to start sync process");
            }

            // Close stdin (not needed)
            fclose($pipes[0]);

            // Read stdout and stderr
            $syncOutput = stream_get_contents($pipes[1]);
            $syncError = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            // Wait for process to finish and get exit code
            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                throw new Exception("Sync process failed with exit code $returnCode" .
                                  ($syncError ? ": $syncError" : ""));
            }

            $syncSuccess = true;

        } catch (Exception $e) {
            $syncOutput = "Error during sync: " . $e->getMessage();
            $syncSuccess = false;
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $syncOutput .= "\n\nExecution time: {$executionTime} seconds";
        
        // Re-establish database connection for the rest of the page
        $db = new PDO("sqlite:../data/subscriptions.db");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// Get last sync information and statistics for this newsletter
try {
    // Ensure we have a database connection
    if (!$db) {
        $db = new PDO("sqlite:../data/subscriptions.db");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    // Get last sync details for this newsletter
    $lastSyncStmt = $db->prepare("
        SELECT last_sync_timestamp, records_processed, created_at 
        FROM last_sync 
        WHERE sync_type = 'submissions' AND newsletter_id = ?
        ORDER BY id DESC 
        LIMIT 1
    ");
    $lastSyncStmt->execute([$newsletterId]);
    $lastSync = $lastSyncStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get statistics for this newsletter
    $statsQuery = "SELECT status, COUNT(*) as count FROM subscriptions WHERE newsletter_id = ? GROUP BY status";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$newsletterId]);
    $stats = [];
    while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<?php getHeader() ?>
<title>Sync <?php echo htmlspecialchars($newsletter['name']); ?> - Newsletter Management</title>
<style>
    .sync-output {
        background-color: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 5px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        max-height: 500px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    .status-card {
        transition: all 0.3s ease;
    }
    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
</style>
<?php getScripts() ?>
</head>
<body>
<?php getNavigation() ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1><?php echo htmlspecialchars($newsletter['name']); ?> - Sync Management</h1>
            <p class="text-secondary">Manually trigger synchronization with BC Gov Digital Forms API</p>
            <div class="mb-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">← All Newsletters</a>
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">Dashboard</a>
                <a href="send_newsletter.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">Send Newsletter</a>
                <?php if (!empty($newsletter['form_id'])): ?>
                    <a href="https://submit.digital.gov.bc.ca/app/form/submit?f=<?php echo htmlspecialchars($newsletter['form_id']); ?>" 
                       class="btn btn-sm btn-success" 
                       target="_blank" 
                       rel="noopener noreferrer">📝 Subscription Form</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($syncRequested): ?>
        <div class="alert <?php echo $syncSuccess ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <h4 class="alert-heading">
                <?php echo $syncSuccess ? '✓ Sync Completed Successfully' : '✗ Sync Failed'; ?>
            </h4>
            <?php if (!empty($errorMessage)): ?>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card status-card">
                <div class="card-body">
                    <h5 class="card-title">Last Sync Information</h5>
                    <?php if ($lastSync): ?>
                        <p class="card-text">
                            <strong>Last Sync:</strong> <?php echo date('Y-m-d H:i:s', strtotime($lastSync['created_at'])); ?><br>
                            <strong>Sync Timestamp:</strong> <?php echo htmlspecialchars($lastSync['last_sync_timestamp']); ?><br>
                            <strong>Records Processed:</strong> <?php echo $lastSync['records_processed']; ?>
                        </p>
                    <?php else: ?>
                        <p class="card-text text-muted">No sync history available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card status-card">
                <div class="card-body">
                    <h5 class="card-title">Current Statistics</h5>
                    <p class="card-text">
                        <strong>Active Subscriptions:</strong> <?php echo $stats['active'] ?? 0; ?><br>
                        <strong>Unsubscribed:</strong> <?php echo $stats['unsubscribed'] ?? 0; ?><br>
                        <strong>Total:</strong> <?php echo array_sum($stats); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Manual Sync Trigger</h5>
                    <p class="card-text">
                        Click the button below to manually trigger a synchronization with the BC Gov Digital Forms API. 
                        This will fetch new submissions and update the subscription database.
                    </p>
                    
                    <form method="post" action="" onsubmit="document.getElementById('syncBtn').disabled = true; document.getElementById('syncBtn').innerHTML = 'Syncing... Please wait';">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="sync">
                        <input type="hidden" name="newsletter_id" value="<?php echo $newsletterId; ?>">
                        <button type="submit" id="syncBtn" class="btn btn-primary btn-lg">
                            🔄 Run Subscription Sync Now
                        </button>
                    </form>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <strong>Note:</strong> The sync process will:
                            <ul>
                                <li>Connect to BC Gov Digital Forms API</li>
                                <li>Fetch new submissions since the last sync</li>
                                <li>Process subscribe/unsubscribe actions</li>
                                <li>Update the local database</li>
                                <li>Generate activity logs</li>
                            </ul>
                            <em>Rate limit: Maximum one sync every 5 minutes</em>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($syncRequested && !empty($syncOutput)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Sync Output Log</h5>
                    <div class="sync-output">
<?php echo htmlspecialchars($syncOutput); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    
</div>

<?php include('../templates/footer.php') ?>