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
    handleDatabaseError($e);
}

// Get newsletter details
$stmt = $db->prepare("SELECT * FROM newsletters WHERE id = ?");
$stmt->execute([$newsletterId]);
$newsletter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$newsletter) {
    header('Location: index.php');
    exit();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportStatusFilter = $_GET['status'] ?? 'active';
    $exportSearchQuery = $_GET['search'] ?? '';

    // Validate status filter against whitelist
    $allowedStatuses = ['active', 'unsubscribed', 'all'];
    if (!in_array($exportStatusFilter, $allowedStatuses, true)) {
        $exportStatusFilter = 'active'; // Default to safe value
    }

    // Sanitize and validate search query
    if (!empty($exportSearchQuery)) {
        // Remove any characters that aren't valid for email addresses
        $exportSearchQuery = preg_replace('/[^a-zA-Z0-9@._\-+]/', '', $exportSearchQuery);

        // Limit length to prevent abuse
        if (strlen($exportSearchQuery) > 100) {
            $exportSearchQuery = substr($exportSearchQuery, 0, 100);
        }

        // Escape LIKE wildcards to prevent SQL wildcard injection
        $exportSearchQuery = str_replace(['%', '_'], ['\\%', '\\_'], $exportSearchQuery);
    }

    // Build export query
    $exportQuery = "SELECT email, status, created_at, updated_at FROM subscriptions";
    $exportParams = [];
    $exportConditions = [];

    $exportConditions[] = "newsletter_id = :newsletter_id";
    $exportParams[':newsletter_id'] = $newsletterId;

    if ($exportStatusFilter !== 'all') {
        $exportConditions[] = "status = :status";
        $exportParams[':status'] = $exportStatusFilter;
    }

    if (!empty($exportSearchQuery)) {
        $exportConditions[] = "email LIKE :search ESCAPE '\\'";
        $exportParams[':search'] = "%$exportSearchQuery%";
    }

    $exportQuery .= " WHERE " . implode(" AND ", $exportConditions);
    $exportQuery .= " ORDER BY created_at DESC";

    $exportStmt = $db->prepare($exportQuery);
    $exportStmt->execute($exportParams);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    $filename = preg_replace('/[^a-z0-9]/i', '_', $newsletter['name']) . '_subscribers_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Output CSV
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add header row
    fputcsv($output, ['Email', 'Status', 'Subscribed Date', 'Last Updated']);

    // Add data rows with CSV injection protection
    foreach ($exportData as $row) {
        fputcsv($output, [
            sanitizeCSVValue($row['email']),
            sanitizeCSVValue($row['status']),
            sanitizeCSVValue($row['created_at']),
            sanitizeCSVValue($row['updated_at'])
        ]);
    }

    fclose($output);
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCsrfToken();

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        try{
            if ($action === 'add_subscriber') {
                $email = trim($_POST['email']);
                
                // Validate email
                if (empty($email)) {
                    throw new Exception("Email address is required");
                }

                if (!validateEmail($email)) {
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
            $message = getUserFriendlyError($e);
            $messageType = 'error';
        }
    }
}

// Get filter from query string with validation
$statusFilter = $_GET['status'] ?? 'active';
$searchQuery = $_GET['search'] ?? '';

// Validate status filter against whitelist
$allowedStatuses = ['active', 'unsubscribed', 'all'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'active'; // Default to safe value
}

// Sanitize and validate search query
if (!empty($searchQuery)) {
    // Remove any characters that aren't valid for email addresses
    $searchQuery = preg_replace('/[^a-zA-Z0-9@._\-+]/', '', $searchQuery);

    // Limit length to prevent abuse
    if (strlen($searchQuery) > 100) {
        $searchQuery = substr($searchQuery, 0, 100);
    }

    // Escape LIKE wildcards to prevent SQL wildcard injection
    $searchQuery = str_replace(['%', '_'], ['\\%', '\\_'], $searchQuery);
}

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
    $conditions[] = "email LIKE :search ESCAPE '\\'";
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
            <?php if (!empty($newsletter['form_id']) && !empty($newsletter['api_username'])): ?>
                <?php if ($lastSyncInfo): ?>
                    <p class="text-secondary">Last sync: <?php echo date('Y-m-d H:i:s', strtotime($lastSyncInfo['created_at'])); ?> (<?php echo $lastSyncInfo['records_processed']; ?> records processed)</p>
                <?php else: ?>
                    <p class="text-secondary">No sync history available - <a href="sync_subscriptions.php?newsletter_id=<?php echo $newsletterId; ?>">run your first sync</a></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-secondary">Form API not configured - use CSV import to add subscribers</p>
            <?php endif; ?>
            <div class="mb-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">← All Newsletters</a>
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">Dashboard</a>
                <?php if (!empty($newsletter['form_id']) && !empty($newsletter['api_username'])): ?>
                    <a href="sync_subscriptions.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">🔄 Sync Subscriptions</a>
                    <a href="https://submit.digital.gov.bc.ca/app/form/submit?f=<?php echo htmlspecialchars($newsletter['form_id']); ?>"
                       class="btn btn-sm btn-success me-2"
                       target="_blank"
                       rel="noopener noreferrer">📝 Subscription Form</a>
                <?php endif; ?>
                <a href="send_newsletter.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-primary me-2">✉️ Send Newsletter</a>
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
                <div class="mt-3">
                    <?php
                    $exportUrl = '?newsletter_id=' . $newsletterId . '&export=csv';
                    if ($statusFilter !== 'active') {
                        $exportUrl .= '&status=' . urlencode($statusFilter);
                    }
                    if (!empty($searchQuery)) {
                        $exportUrl .= '&search=' . urlencode($searchQuery);
                    }
                    ?>
                    <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-success">
                        📥 Export to CSV (<?php echo count($subscriptions); ?> records)
                    </a>
                </div>
            </div>
        </section>

        <?php
        // Get recent campaigns with queue progress
        $recentCampaigns = [];
        try {
            $stmt = $db->prepare("
                SELECT
                    c.id,
                    c.subject,
                    c.from_email,
                    c.sent_to_count,
                    c.sent_at,
                    c.status,
                    c.processing_status,
                    c.ches_transaction_id,
                    c.error_message,
                    c.processed_count,
                    (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id) as total_count,
                    (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'sent') as sent_count,
                    (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'pending') as pending_count,
                    (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'failed') as failed_count
                FROM email_campaigns c
                WHERE c.newsletter_id = ?
                ORDER BY c.sent_at DESC
                LIMIT 10
            ");
            $stmt->execute([$newsletterId]);
            $recentCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch recent campaigns: " . $e->getMessage());
        }
        ?>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <section class="card h-100" role="region" aria-label="Subscriptions">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Subscriptions</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light-subtle">
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
                                                            <?php csrfField(); ?>
                                                            <input type="hidden" name="action" value="unsubscribe">
                                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($sub['email']); ?>">
                                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Unsubscribe</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to reactivate <?php echo htmlspecialchars($sub['email']); ?>?')">
                                                            <?php csrfField(); ?>
                                                            <input type="hidden" name="action" value="add_subscriber">
                                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($sub['email']); ?>">
                                                            <button type="submit" class="btn btn-primary btn-sm">Reactivate</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($isAdminUser): ?>
                                                        <form method="post" action="" class="d-inline ms-1" onsubmit="return confirm('⚠️ ADMIN ACTION: Are you sure you want to PERMANENTLY DELETE <?php echo htmlspecialchars($sub['email']); ?> from the database? This cannot be undone.')">
                                                            <?php csrfField(); ?>
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
            </div>

            <div class="col-lg-5 mb-4">
                <?php if (!empty($recentCampaigns)): ?>
                    <section class="card bg-light-subtle h-100">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Recent Newsletter Campaigns</h2>
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentCampaigns as $campaign): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong class="flex-grow-1"><?php echo htmlspecialchars($campaign['subject']); ?></strong>
                                            <?php
                                            $statusText = str_replace('_', ' ', $campaign['processing_status'] ?? $campaign['status']);
                                            $badgeClass = 'badge ';
                                            switch($campaign['processing_status'] ?? $campaign['status']) {
                                                case 'completed':
                                                case 'sent':
                                                    $badgeClass .= 'bg-success';
                                                    break;
                                                case 'failed':
                                                case 'cancelled':
                                                    $badgeClass .= 'bg-danger';
                                                    break;
                                                case 'processing':
                                                case 'sending':
                                                    $badgeClass .= 'bg-info';
                                                    break;
                                                case 'paused':
                                                    $badgeClass .= 'bg-warning text-dark';
                                                    break;
                                                case 'pending':
                                                case 'queued':
                                                    $badgeClass .= 'bg-secondary';
                                                    break;
                                                default:
                                                    $badgeClass .= 'bg-secondary';
                                            }
                                            ?>
                                            <span class="<?php echo $badgeClass; ?> ms-2">
                                                <?php echo ucfirst($statusText); ?>
                                            </span>
                                        </div>
                                        <small class="text-secondary d-block mb-2">
                                            <?php echo date('M j, Y g:i A', strtotime($campaign['sent_at'])); ?> •
                                            <?php echo $campaign['sent_to_count']; ?> subscribers

                                            <?php if ($campaign['status'] == 'queued' || $campaign['status'] == 'sending'): ?>
                                                <br>📊 <?php echo $campaign['sent_count']; ?> sent,
                                                <?php echo $campaign['pending_count']; ?> pending
                                                <?php if ($campaign['failed_count'] > 0): ?>
                                                    , <span class="text-danger"><?php echo $campaign['failed_count']; ?> failed</span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($campaign['status'] == 'sent' || $campaign['status'] == 'completed_with_errors'): ?>
                                                <br>✅ <?php echo $campaign['sent_count']; ?> sent
                                                <?php if ($campaign['failed_count'] > 0): ?>
                                                    , <span class="text-danger"><?php echo $campaign['failed_count']; ?> failed</span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($campaign['error_message']): ?>
                                                <br><span class="text-danger">Error: <?php echo htmlspecialchars($campaign['error_message']); ?></span>
                                            <?php endif; ?>
                                        </small>
                                        <?php if (in_array($campaign['processing_status'], ['pending', 'processing', 'paused'])): ?>
                                            <a href="campaign_monitor.php?campaign_id=<?php echo $campaign['id']; ?>"
                                               class="btn btn-sm btn-outline-warning">
                                                📊 Monitor
                                            </a>
                                        <?php else: ?>
                                            <a href="campaign_dashboard.php?campaign_id=<?php echo $campaign['id']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                📊 View Stats
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>

        <section class="card bg-light-subtle mb-4" role="region" aria-label="Manual Management">
            <div class="card-body">
                <h2 class="card-title">Manual Subscription Management</h2>
                <div class="row">
                    <div class="col-md-4">
                        <h3 class="h5">Add New Subscriber</h3>
                        <form method="post" action="">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="add_subscriber">
                            <div class="mb-3">
                                <label for="add-email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" id="add-email" name="email" class="form-control" placeholder="subscriber@example.com" required>
                                    <button type="submit" class="btn btn-primary">Add</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-md-4">
                        <h3 class="h5">Unsubscribe Email</h3>
                        <form method="post" action="" onsubmit="return confirm('Are you sure you want to unsubscribe this email address?')">
                            <?php csrfField(); ?>
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

                    <div class="col-md-4">
                        <h3 class="h5">Bulk Import</h3>
                        <p class="small text-secondary">Import multiple subscribers from a CSV file</p>
                        <a href="import_csv.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-success">
                            📁 Import from CSV
                        </a>
                    </div>
                </div>


            </div>
        </section>
    </div>
<?php include('../templates/javascript.php') ?>
<?php include('../templates/footer.php') ?>