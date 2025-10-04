<?php
/**
 * Main Newsletters Dashboard
 * Lists all configured newsletters and allows management
 */
require('../inc/lsapp.php');

// Check if user is admin
$isAdminUser = isAdmin();

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdminUser) {
    // Validate CSRF token
    requireCsrfToken();

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'toggle_active') {
                $newsletterId = (int)$_POST['newsletter_id'];
                $stmt = $db->prepare("UPDATE newsletters SET is_active = NOT is_active, updated_at = ? WHERE id = ?");
                $stmt->execute([date('Y-m-d H:i:s'), $newsletterId]);
                $message = "Newsletter status updated successfully";
                $messageType = 'success';
                
            } elseif ($action === 'delete') {
                $newsletterId = (int)$_POST['newsletter_id'];
                $confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';

                // Check if newsletter has any subscriptions
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE newsletter_id = ?");
                $checkStmt->execute([$newsletterId]);
                $count = $checkStmt->fetchColumn();

                if ($count > 0 && !$confirmed) {
                    throw new Exception("Newsletter has $count subscribers. Use 'Delete with Subscribers' to permanently delete everything.");
                }

                // Begin transaction for complete deletion
                $db->beginTransaction();

                try {
                    // Delete subscription history
                    $historyStmt = $db->prepare("DELETE FROM subscription_history WHERE newsletter_id = ?");
                    $historyStmt->execute([$newsletterId]);

                    // Delete subscriptions
                    $subsStmt = $db->prepare("DELETE FROM subscriptions WHERE newsletter_id = ?");
                    $subsStmt->execute([$newsletterId]);

                    // Delete sync history
                    $syncStmt = $db->prepare("DELETE FROM last_sync WHERE newsletter_id = ?");
                    $syncStmt->execute([$newsletterId]);

                    // Delete newsletter
                    $newsStmt = $db->prepare("DELETE FROM newsletters WHERE id = ?");
                    $newsStmt->execute([$newsletterId]);

                    $db->commit();

                    if ($count > 0) {
                        $message = "Newsletter and $count subscribers permanently deleted";
                    } else {
                        $message = "Newsletter deleted successfully";
                    }
                    $messageType = 'success';

                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all newsletters with statistics
$query = "
    SELECT 
        n.*,
        COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.email END) as active_count,
        COUNT(DISTINCT CASE WHEN s.status = 'unsubscribed' THEN s.email END) as unsubscribed_count,
        COUNT(DISTINCT s.email) as total_count,
        MAX(ls.created_at) as last_sync
    FROM newsletters n
    LEFT JOIN subscriptions s ON n.id = s.newsletter_id
    LEFT JOIN last_sync ls ON n.id = ls.newsletter_id
    GROUP BY n.id
    ORDER BY n.created_at DESC
";

$newsletters = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<?php getHeader() ?>
<title>Newsletters Management Dashboard</title>
<style>
    .newsletter-card {
        border-left: 4px solid transparent;
    }
    .newsletter-card.active {
        border-left-color: #28a745;
    }
    .newsletter-card.inactive {
        border-left-color: #dc3545;
        opacity: 0.8;
    }
    .stats-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
</style>
<?php getScripts() ?>
</head>
<body>
<?php getNavigation() ?>

<!-- Skip Links for Accessibility -->
<div class="visually-hidden-focusable">
    <a href="#main-content" class="btn btn-primary btn-sm">Skip to main content</a>
    <a href="#newsletter-list" class="btn btn-secondary btn-sm">Skip to newsletter list</a>
</div>

<div class="container">
    <main id="main-content">
    <div class="row">
        <div class="col-md-12">
            <h1>Newsletters</h1>
            <p class="text-secondary">Manage multiple newsletter configurations and subscriptions</p>
            
            <?php if ($isAdminUser): ?>
            <div class="mb-3">
                <a href="newsletter_edit.php" class="btn btn-primary">
                    ➕ Add New Newsletter
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="row" id="newsletter-list">
        <div class="visually-hidden">
            <h2>Newsletter List</h2>
        </div>
        <?php if (empty($newsletters)): ?>
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    No newsletters configured yet. 
                    <?php if ($isAdminUser): ?>
                        <a href="newsletter_edit.php">Add your first newsletter</a>.
                    <?php else: ?>
                        Please contact an administrator to set up newsletters.
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($newsletters as $newsletter): ?>
                <div class="col-md-6 mb-4">
                    <div class="card newsletter-card <?php echo $newsletter['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title">
                                    <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletter['id']; ?>" 
                                       class="text-decoration-none"
                                       aria-label="View dashboard for <?php echo htmlspecialchars($newsletter['name']); ?><?php if (!$newsletter['is_active']): ?> (currently inactive)<?php endif; ?>">
                                        <?php echo htmlspecialchars($newsletter['name']); ?>
                                    </a>
                                    <?php if (!$newsletter['is_active']): ?>
                                        <span class="badge bg-danger ms-2" aria-label="Newsletter status: inactive">Inactive</span>
                                    <?php endif; ?>
                                </h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            aria-label="Actions for <?php echo htmlspecialchars($newsletter['name']); ?> newsletter"
                                            id="actions-<?php echo $newsletter['id']; ?>">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="actions-<?php echo $newsletter['id']; ?>">
                                        <li>
                                            <a class="dropdown-item" href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletter['id']; ?>">
                                                📊 View Dashboard
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="sync_subscriptions.php?newsletter_id=<?php echo $newsletter['id']; ?>">
                                                🔄 Sync Subscriptions
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="send_newsletter.php?newsletter_id=<?php echo $newsletter['id']; ?>">
                                                ✉️ Send Newsletter
                                            </a>
                                        </li>
                                        <?php if (!empty($newsletter['form_id'])): ?>
                                        <li>
                                            <a class="dropdown-item" 
                                               href="https://submit.digital.gov.bc.ca/app/form/submit?f=<?php echo htmlspecialchars($newsletter['form_id']); ?>"
                                               target="_blank"
                                               rel="noopener noreferrer">
                                                📝 Open Subscription Form
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($isAdminUser): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="newsletter_edit.php?id=<?php echo $newsletter['id']; ?>">
                                                    ✏️ Edit Configuration
                                                </a>
                                            </li>
                                            <li>
                                                <form method="post" action="" class="d-inline">
                                                    <?php csrfField(); ?>
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="newsletter_id" value="<?php echo $newsletter['id']; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <?php echo $newsletter['is_active'] ? '⏸️ Deactivate' : '▶️ Activate'; ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <?php if ($newsletter['total_count'] == 0): ?>
                                                    <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this newsletter?')">
                                                        <?php csrfField(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="newsletter_id" value="<?php echo $newsletter['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            🗑️ Delete Newsletter
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" action="" onsubmit="return confirm('⚠️ WARNING: This will PERMANENTLY delete this newsletter and ALL <?php echo $newsletter['total_count']; ?> subscribers!\n\nThis includes:\n- <?php echo $newsletter['active_count']; ?> active subscribers\n- <?php echo $newsletter['unsubscribed_count']; ?> unsubscribed records\n- All subscription history\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')">
                                                        <?php csrfField(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="newsletter_id" value="<?php echo $newsletter['id']; ?>">
                                                        <input type="hidden" name="confirm_delete" value="yes">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            🗑️ Delete with Subscribers
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($newsletter['description']): ?>
                                <p class="card-text text-muted small">
                                    <?php echo htmlspecialchars($newsletter['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-3" role="group" aria-labelledby="stats-<?php echo $newsletter['id']; ?>">
                                <div id="stats-<?php echo $newsletter['id']; ?>" class="visually-hidden">Subscription statistics for <?php echo htmlspecialchars($newsletter['name']); ?></div>
                                
                                <span class="stats-badge bg-secondary-subtle text-white" 
                                      aria-label="<?php echo $newsletter['active_count']; ?> active subscribers">
                                    <span aria-hidden="true"><?php echo $newsletter['active_count']; ?> subscribers</span>
                                </span>
                            </div>
                            
                            <div class="small text-muted">
                                <div>
                                    <strong>Created:</strong> 
                                    <?php echo date('Y-m-d', strtotime($newsletter['created_at'])); ?>

                                    <strong>Last Sync:</strong> 
                                    <?php echo $newsletter['last_sync'] ? date('Y-m-d H:i', strtotime($newsletter['last_sync'])) : 'Never'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!$isAdminUser): ?>
    <div class="alert alert-info mt-4" role="alert">
        <strong>Note:</strong> You have read-only access. Contact an administrator to add or modify newsletter configurations.
    </div>
    <?php endif; ?>
    </main>
</div>
<?php require('../templates/javascript.php') ?>
<?php include('../templates/footer.php') ?>