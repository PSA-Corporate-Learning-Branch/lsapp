<?php
/**
 * Web UI for viewing email subscriptions with manual management
 */
require('../inc/lsapp.php');

// Check if user is admin
$isAdminUser = isAdmin();

// Get newsletter ID from query string
$newsletterId = isset($_GET['newsletter_id']) ? (int)$_GET['newsletter_id'] : 1;

// Database connection - use the database in data folder
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

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'add_subscriber') {
                $email = trim($_POST['email']);
                
                // Validate email
                if (empty($email)) {
                    throw new Exception("Email address is required");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email address format");
                }
                
                $email = strtolower($email);
                $now = date('Y-m-d H:i:s');
                
                // Check if email already exists for this newsletter
                $checkStmt = $db->prepare("SELECT email, status FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                $checkStmt->execute([$email, $newsletterId]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    if ($existing['status'] === 'active') {
                        throw new Exception("Email address is already subscribed");
                    } else {
                        // Reactivate unsubscribed email
                        $updateStmt = $db->prepare("
                            UPDATE subscriptions 
                            SET status = 'active', updated_at = ?
                            WHERE email = ? AND newsletter_id = ?
                        ");
                        $updateStmt->execute([$now, $email, $newsletterId]);
                        $message = "Email address reactivated successfully";
                    }
                } else {
                    // Add new subscription
                    $insertStmt = $db->prepare("
                        INSERT INTO subscriptions (email, status, created_at, updated_at, source, newsletter_id)
                        VALUES (?, 'active', ?, ?, 'manual', ?)
                    ");
                    $insertStmt->execute([$email, $now, $now, $newsletterId]);
                    $message = "Email address added successfully";
                }
                
                // Log to history
                $historyStmt = $db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, 'subscribe', ?, 'manual-web-ui', ?, ?)
                ");
                $historyStmt->execute([$email, $now, json_encode(['source' => 'manual', 'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']), $newsletterId]);
                
                $messageType = 'success';
                
            } elseif ($action === 'unsubscribe') {
                $email = trim($_POST['email']);
                
                if (empty($email)) {
                    throw new Exception("Email address is required");
                }
                
                $email = strtolower($email);
                $now = date('Y-m-d H:i:s');
                
                // Check if email exists and is active for this newsletter
                $checkStmt = $db->prepare("SELECT email, status FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                $checkStmt->execute([$email, $newsletterId]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    throw new Exception("Email address not found in database");
                }
                
                if ($existing['status'] === 'unsubscribed') {
                    throw new Exception("Email address is already unsubscribed");
                }
                
                // Unsubscribe email
                $updateStmt = $db->prepare("
                    UPDATE subscriptions 
                    SET status = 'unsubscribed', updated_at = ?
                    WHERE email = ? AND newsletter_id = ?
                ");
                $updateStmt->execute([$now, $email, $newsletterId]);
                
                // Log to history
                $historyStmt = $db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, 'unsubscribe', ?, 'manual-web-ui', ?, ?)
                ");
                $historyStmt->execute([$email, $now, json_encode(['source' => 'manual', 'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']), $newsletterId]);
                
                $message = "Email address unsubscribed successfully";
                $messageType = 'success';
                
            } elseif ($action === 'delete' && $isAdminUser) {
                // Admin-only delete action
                $email = trim($_POST['email']);
                
                if (empty($email)) {
                    throw new Exception("Email address is required");
                }
                
                $email = strtolower($email);
                $now = date('Y-m-d H:i:s');
                
                // Check if email exists for this newsletter
                $checkStmt = $db->prepare("SELECT email FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                $checkStmt->execute([$email, $newsletterId]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    throw new Exception("Email address not found in database");
                }
                
                // Log deletion to history before deleting
                $historyStmt = $db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, 'deleted', ?, 'manual-web-ui-admin', ?, ?)
                ");
                $historyStmt->execute([$email, $now, json_encode(['source' => 'admin-delete', 'admin_user' => $_SERVER['REMOTE_USER'] ?? 'unknown', 'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']), $newsletterId]);
                
                // Delete from subscriptions table
                $deleteStmt = $db->prepare("DELETE FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                $deleteStmt->execute([$email, $newsletterId]);
                
                $message = "Email address permanently deleted from database";
                $messageType = 'success';
            }
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filter from query string
$statusFilter = $_GET['status'] ?? 'active';
$searchQuery = $_GET['search'] ?? '';

// Build query based on filters
$query = "SELECT email, status, created_at, updated_at FROM subscriptions";
$params = [];
$conditions = [];

// Always filter by newsletter_id
$conditions[] = "newsletter_id = :newsletter_id";
$params[':newsletter_id'] = $newsletterId;

if ($statusFilter !== 'all') {
    $conditions[] = "status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($searchQuery)) {
    $conditions[] = "email LIKE :search";
    $params[':search'] = "%$searchQuery%";
}

$query .= " WHERE " . implode(" AND ", $conditions);

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for this newsletter
$statsQuery = "SELECT status, COUNT(*) as count FROM subscriptions WHERE newsletter_id = ? GROUP BY status";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([$newsletterId]);
$stats = [];
$totalCount = 0;
while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['status']] = $row['count'];
    $totalCount += $row['count'];
}

// Get recent activity for this newsletter
$recentQuery = "SELECT email, action, timestamp FROM subscription_history WHERE newsletter_id = ? ORDER BY timestamp DESC LIMIT 10";
$recentStmt = $db->prepare($recentQuery);
$recentStmt->execute([$newsletterId]);
$recentActivity = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php getHeader() ?>
<title><?php echo htmlspecialchars($newsletter['name']); ?> - Subscriptions Dashboard</title>
<?php getScripts() ?>
</head>
<body>
<?php getNavigation() ?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1><?php echo htmlspecialchars($newsletter['name']); ?> Subscriptions</h1>
            <?php 
            // Get last sync time for this newsletter
            $lastSyncStmt = $db->prepare("
                SELECT created_at, records_processed 
                FROM last_sync 
                WHERE newsletter_id = ? AND sync_type = 'submissions' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $lastSyncStmt->execute([$newsletterId]);
            $lastSyncInfo = $lastSyncStmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($lastSyncInfo): ?>
                <p class="text-secondary">Last sync: <?php echo date('Y-m-d H:i:s', strtotime($lastSyncInfo['created_at'])); ?> (<?php echo $lastSyncInfo['records_processed']; ?> records processed)</p>
            <?php else: ?>
                <p class="text-secondary">No sync history available - <a href="sync_subscriptions.php?newsletter_id=<?php echo $newsletterId; ?>">run your first sync</a></p>
            <?php endif; ?>
            <div class="mb-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">← All Newsletters</a>
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">Dashboard</a>
                <a href="sync_subscriptions.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">🔄 Sync Subscriptions</a>
                <a href="send_newsletter.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-primary me-2">✉️ Send Newsletter</a>
                <?php if (!empty($newsletter['form_id'])): ?>
                    <a href="https://submit.digital.gov.bc.ca/app/form/submit?f=<?php echo htmlspecialchars($newsletter['form_id']); ?>" 
                       class="btn btn-sm btn-success" 
                       target="_blank" 
                       rel="noopener noreferrer">📝 Subscription Form</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <div class="container">
        <?php if (!empty($message)): ?>
            <?php 
                $alertClass = 'alert';
                if($messageType === 'success') $alertClass .= ' alert-success';
                elseif($messageType === 'error') $alertClass .= ' alert-danger';
                else $alertClass .= ' alert-info';
            ?>
            <div class="<?php echo $alertClass; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="row mb-4" role="region" aria-label="Statistics">
            <div class="col-md-3 mb-3">
                <div class="card bg-light-subtle">
                    <div class="card-body">
                        <h2 class="card-title h3"><?php echo $totalCount; ?></h2>
                        <p class="card-text text-uppercase small text-secondary">Total Subscriptions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success-subtle">
                    <div class="card-body">
                        <h2 class="card-title h3"><?php echo $stats['active'] ?? 0; ?></h2>
                        <p class="card-text text-uppercase small text-secondary">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger-subtle">
                    <div class="card-body">
                        <h2 class="card-title h3"><?php echo $stats['unsubscribed'] ?? 0; ?></h2>
                        <p class="card-text text-uppercase small text-secondary">Unsubscribed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info-subtle">
                    <div class="card-body">
                        <h2 class="card-title h3"><?php echo round((($stats['active'] ?? 0) / max($totalCount, 1)) * 100, 1); ?>%</h2>
                        <p class="card-text text-uppercase small text-secondary">Active Rate</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="card bg-light-subtle mb-4" role="search">
            <div class="card-body">
                <form method="get" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="status" class="form-label text-secondary small">Status Filter</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Subscriptions</option>
                            <option value="unsubscribed" <?php echo $statusFilter === 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed Only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label text-secondary small">Search Email</label>
                        <input type="search" name="search" id="search" class="form-control" placeholder="Search by email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <?php if ($statusFilter !== 'active' || !empty($searchQuery)): ?>
                            <a href="?" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <section class="card mb-4" role="region" aria-label="Subscriptions">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-uppercase small">Email Address</th>
                                <th scope="col" class="text-uppercase small">Status</th>
                                <th scope="col" class="text-uppercase small">Subscribed Date</th>
                                <th scope="col" class="text-uppercase small">Last Updated</th>
                                <th scope="col" class="text-uppercase small">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-5">No subscriptions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                        <td>
                                            <?php if($sub['status'] === 'active'): ?>
                                                <span class="badge bg-success"><?php echo ucfirst($sub['status']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?php echo ucfirst($sub['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($sub['created_at'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($sub['updated_at'])); ?></td>
                                        <td>
                                            <?php if ($sub['status'] === 'active'): ?>
                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to unsubscribe <?php echo htmlspecialchars($sub['email']); ?>?')">
                                                    <input type="hidden" name="action" value="unsubscribe">
                                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($sub['email']); ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Unsubscribe</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to reactivate <?php echo htmlspecialchars($sub['email']); ?>?')">
                                                    <input type="hidden" name="action" value="add_subscriber">
                                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($sub['email']); ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($isAdminUser): ?>
                                                <form method="post" action="" class="d-inline ms-1" onsubmit="return confirm('⚠️ ADMIN ACTION: Are you sure you want to PERMANENTLY DELETE <?php echo htmlspecialchars($sub['email']); ?> from the database? This cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($sub['email']); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Permanently delete (Admin only)">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card bg-light-subtle mb-4" role="region" aria-label="Manual Management">
            <div class="card-body">
                <h2 class="card-title">Manual Subscription Management</h2>
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="h5">Add New Subscriber</h3>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="add_subscriber">
                            <div class="mb-3">
                                <label for="add-email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" id="add-email" name="email" class="form-control" placeholder="subscriber@example.com" required>
                                    <button type="submit" class="btn btn-primary">Add Subscriber</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h5">Unsubscribe Email</h3>
                        <form method="post" action="" onsubmit="return confirm('Are you sure you want to unsubscribe this email address?')">
                            <input type="hidden" name="action" value="unsubscribe">
                            <div class="mb-3">
                                <label for="unsubscribe-email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" id="unsubscribe-email" name="email" class="form-control" placeholder="subscriber@example.com" required>
                                    <button type="submit" class="btn btn-danger">Unsubscribe</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                

            </div>
        </section>
    </div>
<?php include('../templates/javascript.php') ?>
<?php include('../templates/footer.php') ?>