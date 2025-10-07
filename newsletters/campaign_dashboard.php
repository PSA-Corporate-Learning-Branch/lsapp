<?php
/**
 * Campaign Dashboard - Detailed statistics for a specific email campaign
 * Shows delivery stats, open rates, and engagement metrics
 */
require('../inc/lsapp.php');

// Check if user is admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

// Get campaign ID
$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
if (!$campaignId) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    handleDatabaseError($e);
}

// Get campaign details
try {
    $stmt = $db->prepare("
        SELECT
            c.*,
            n.name as newsletter_name,
            n.tracking_url,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id) as total_queued,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'sent') as sent_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'failed') as failed_count
        FROM email_campaigns c
        JOIN newsletters n ON c.newsletter_id = n.id
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    handleDatabaseError($e);
}

// Get email open tracking statistics from remote database
$trackingStats = [
    'total_opens' => 0,
    'unique_opens' => 0,
    'open_rate' => 0,
    'first_open' => null,
    'last_open' => null,
    'opens_by_hour' => [],
    'top_openers' => [],
    'user_agents' => []
];

$trackingDbPath = 'E:\WebSites\data\email_tracking.db';
if (file_exists($trackingDbPath)) {
    try {
        $trackingDb = new PDO("sqlite:$trackingDbPath");
        $trackingDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Total opens
        $stmt = $trackingDb->prepare("SELECT COUNT(*) FROM email_opens WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $trackingStats['total_opens'] = $stmt->fetchColumn();

        // Unique opens (by email address)
        $stmt = $trackingDb->prepare("SELECT COUNT(DISTINCT email) FROM email_opens WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $trackingStats['unique_opens'] = $stmt->fetchColumn();

        // Calculate open rate
        if ($campaign['sent_count'] > 0) {
            $trackingStats['open_rate'] = round(($trackingStats['unique_opens'] / $campaign['sent_count']) * 100, 2);
        }

        // First and last open times
        $stmt = $trackingDb->prepare("
            SELECT MIN(opened_at) as first_open, MAX(opened_at) as last_open
            FROM email_opens
            WHERE campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $openTimes = $stmt->fetch(PDO::FETCH_ASSOC);
        $trackingStats['first_open'] = $openTimes['first_open'];
        $trackingStats['last_open'] = $openTimes['last_open'];

        // Opens by hour (for graphing)
        $stmt = $trackingDb->prepare("
            SELECT
                strftime('%Y-%m-%d %H:00:00', opened_at) as hour,
                COUNT(*) as count
            FROM email_opens
            WHERE campaign_id = ?
            GROUP BY hour
            ORDER BY hour
        ");
        $stmt->execute([$campaignId]);
        $trackingStats['opens_by_hour'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top openers (people who opened multiple times)
        $stmt = $trackingDb->prepare("
            SELECT
                email,
                COUNT(*) as open_count,
                MIN(opened_at) as first_opened,
                MAX(opened_at) as last_opened
            FROM email_opens
            WHERE campaign_id = ? AND email IS NOT NULL
            GROUP BY email
            ORDER BY open_count DESC
            LIMIT 10
        ");
        $stmt->execute([$campaignId]);
        $trackingStats['top_openers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // User agent breakdown (email clients)
        $stmt = $trackingDb->prepare("
            SELECT
                user_agent,
                COUNT(*) as count
            FROM email_opens
            WHERE campaign_id = ?
            GROUP BY user_agent
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$campaignId]);
        $trackingStats['user_agents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Log error but continue - tracking stats are optional
        error_log("Failed to load tracking stats: " . $e->getMessage());
    }
}

// Get failed emails details
$failedEmails = [];
try {
    $stmt = $db->prepare("
        SELECT email, error_message, failed_at
        FROM email_queue
        WHERE campaign_id = ? AND status = 'failed'
        ORDER BY failed_at DESC
        LIMIT 20
    ");
    $stmt->execute([$campaignId]);
    $failedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch failed emails: " . $e->getMessage());
}

// Helper function to parse user agent into readable email client
function parseUserAgent($ua) {
    if (empty($ua) || $ua === 'unknown') return 'Unknown';

    // Common email clients
    if (stripos($ua, 'Outlook') !== false) return 'Outlook';
    if (stripos($ua, 'Gmail') !== false) return 'Gmail';
    if (stripos($ua, 'Apple Mail') !== false || stripos($ua, 'Mail/') !== false) return 'Apple Mail';
    if (stripos($ua, 'Thunderbird') !== false) return 'Thunderbird';
    if (stripos($ua, 'Yahoo') !== false) return 'Yahoo Mail';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS Mail';
    if (stripos($ua, 'Android') !== false) return 'Android Mail';

    // Browsers
    if (stripos($ua, 'Chrome') !== false) return 'Chrome';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'Safari') !== false) return 'Safari';
    if (stripos($ua, 'Edge') !== false) return 'Edge';

    return 'Other';
}
?>
<?php getHeader() ?>
<title>Campaign Dashboard - <?php echo htmlspecialchars($campaign['subject']); ?></title>
<?php getScripts() ?>
<style>
.stat-card {
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}
.stat-value {
    font-size: 2rem;
    font-weight: bold;
}
.stat-label {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
</head>
<body>
<?php getNavigation() ?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>📊 Campaign Dashboard</h1>
            <h2 class="h4 text-secondary"><?php echo htmlspecialchars($campaign['subject']); ?></h2>
            <div class="mb-3">
                <a href="send_newsletter.php?newsletter_id=<?php echo $campaign['newsletter_id']; ?>" class="btn btn-sm btn-outline-secondary">
                    ← Back to Newsletter
                </a>
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $campaign['newsletter_id']; ?>" class="btn btn-sm btn-outline-primary">
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Campaign Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="h5 mb-0">Campaign Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Newsletter:</strong> <?php echo htmlspecialchars($campaign['newsletter_name']); ?></p>
                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($campaign['subject']); ?></p>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($campaign['from_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($campaign['sent_at'])); ?></p>
                            <p><strong>Status:</strong>
                                <span class="badge bg-<?php
                                    echo match($campaign['processing_status']) {
                                        'completed' => 'success',
                                        'processing' => 'info',
                                        'pending' => 'secondary',
                                        'paused' => 'warning',
                                        'failed' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($campaign['processing_status']); ?>
                                </span>
                            </p>
                            <p><strong>Total Recipients:</strong> <?php echo $campaign['sent_to_count']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center bg-success-subtle border border-success">
                <div class="stat-value text-success"><?php echo $campaign['sent_count']; ?></div>
                <div class="stat-label text-body-secondary">Emails Sent</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center bg-warning-subtle border border-warning">
                <div class="stat-value text-warning"><?php echo $campaign['pending_count']; ?></div>
                <div class="stat-label text-body-secondary">Pending</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center bg-danger-subtle border border-danger">
                <div class="stat-value text-danger"><?php echo $campaign['failed_count']; ?></div>
                <div class="stat-label text-body-secondary">Failed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center bg-body-secondary border">
                <div class="stat-value text-body-emphasis"><?php echo $campaign['total_queued']; ?></div>
                <div class="stat-label text-body-secondary">Total Queued</div>
            </div>
        </div>
    </div>

    <!-- Sending Progress -->
    <?php if ($campaign['total_queued'] > 0): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="h5 mb-0">Sending Progress</h3>
                </div>
                <div class="card-body">
                    <?php
                    $sentPercent = round(($campaign['sent_count'] / $campaign['total_queued']) * 100, 1);
                    $failedPercent = round(($campaign['failed_count'] / $campaign['total_queued']) * 100, 1);
                    $pendingPercent = round(($campaign['pending_count'] / $campaign['total_queued']) * 100, 1);
                    ?>
                    <div class="progress progress-bar-custom" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar"
                             style="width: <?php echo $sentPercent; ?>%"
                             aria-valuenow="<?php echo $sentPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php if ($sentPercent > 5): ?>Sent: <?php echo $sentPercent; ?>%<?php endif; ?>
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar"
                             style="width: <?php echo $pendingPercent; ?>%"
                             aria-valuenow="<?php echo $pendingPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php if ($pendingPercent > 5): ?>Pending: <?php echo $pendingPercent; ?>%<?php endif; ?>
                        </div>
                        <div class="progress-bar bg-danger" role="progressbar"
                             style="width: <?php echo $failedPercent; ?>%"
                             aria-valuenow="<?php echo $failedPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php if ($failedPercent > 5): ?>Failed: <?php echo $failedPercent; ?>%<?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 small text-body-secondary text-center">
                        <?php echo $campaign['sent_count']; ?> sent /
                        <?php echo $campaign['pending_count']; ?> pending /
                        <?php echo $campaign['failed_count']; ?> failed /
                        <?php echo $campaign['total_queued']; ?> total
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Email Open Tracking Statistics -->
    <?php if (!empty($campaign['tracking_url'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h3 class="h5 mb-0">📬 Email Open Tracking</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="stat-card text-center bg-info-subtle border border-info">
                                <div class="stat-value text-info"><?php echo $trackingStats['unique_opens']; ?></div>
                                <div class="stat-label text-body-secondary">Unique Opens</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center bg-info-subtle border border-info">
                                <div class="stat-value text-info"><?php echo $trackingStats['total_opens']; ?></div>
                                <div class="stat-label text-body-secondary">Total Opens</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center bg-primary-subtle border border-primary">
                                <div class="stat-value text-primary"><?php echo $trackingStats['open_rate']; ?>%</div>
                                <div class="stat-label text-body-secondary">Open Rate</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center bg-body-secondary border">
                                <div class="stat-value text-body-emphasis">
                                    <?php
                                    if ($trackingStats['unique_opens'] > 0 && $trackingStats['total_opens'] > 0) {
                                        echo number_format($trackingStats['total_opens'] / $trackingStats['unique_opens'], 1);
                                    } else {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label text-body-secondary">Avg Opens/Person</div>
                            </div>
                        </div>
                    </div>

                    <?php if ($trackingStats['unique_opens'] > 0): ?>
                        <div class="alert alert-info">
                            <strong>⏰ Timeline:</strong>
                            <?php if ($trackingStats['first_open']): ?>
                                First opened <?php echo date('M j, Y g:i A', strtotime($trackingStats['first_open'])); ?>
                            <?php endif; ?>
                            <?php if ($trackingStats['last_open']): ?>
                                • Most recent open <?php echo date('M j, Y g:i A', strtotime($trackingStats['last_open'])); ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ No opens tracked yet.</strong>
                            <?php if ($campaign['sent_count'] > 0): ?>
                                Note: Most email clients block images by default. Actual open rates are typically 20-40% higher than tracked rates.
                            <?php else: ?>
                                Emails are still being sent.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Opens Timeline -->
                    <?php if (!empty($trackingStats['opens_by_hour']) && count($trackingStats['opens_by_hour']) > 0): ?>
                    <div class="mt-4">
                        <h4 class="h6">Opens Over Time</h4>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Time Period</th>
                                        <th>Opens</th>
                                        <th>Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $maxOpens = max(array_column($trackingStats['opens_by_hour'], 'count'));
                                    foreach ($trackingStats['opens_by_hour'] as $hour):
                                        $barWidth = $maxOpens > 0 ? ($hour['count'] / $maxOpens) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo date('M j, g A', strtotime($hour['hour'])); ?></td>
                                        <td><?php echo $hour['count']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-info" role="progressbar"
                                                     style="width: <?php echo $barWidth; ?>%"
                                                     aria-valuenow="<?php echo $barWidth; ?>"
                                                     aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Top Openers -->
                    <?php if (!empty($trackingStats['top_openers']) && count($trackingStats['top_openers']) > 0): ?>
                    <div class="mt-4">
                        <h4 class="h6">Most Engaged Recipients</h4>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Opens</th>
                                        <th>First Opened</th>
                                        <th>Last Opened</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trackingStats['top_openers'] as $opener): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($opener['email']); ?></td>
                                        <td><span class="badge bg-info"><?php echo $opener['open_count']; ?></span></td>
                                        <td><?php echo date('M j, g:i A', strtotime($opener['first_opened'])); ?></td>
                                        <td><?php echo date('M j, g:i A', strtotime($opener['last_opened'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Email Client Breakdown -->
                    <?php if (!empty($trackingStats['user_agents']) && count($trackingStats['user_agents']) > 0): ?>
                    <div class="mt-4">
                        <h4 class="h6">Email Clients Used</h4>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Email Client</th>
                                        <th>Opens</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalUaOpens = array_sum(array_column($trackingStats['user_agents'], 'count'));
                                    foreach ($trackingStats['user_agents'] as $ua):
                                        $percent = $totalUaOpens > 0 ? round(($ua['count'] / $totalUaOpens) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(parseUserAgent($ua['user_agent'])); ?></td>
                                        <td><?php echo $ua['count']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px; min-width: 100px;">
                                                <div class="progress-bar" role="progressbar"
                                                     style="width: <?php echo $percent; ?>%"
                                                     aria-valuenow="<?php echo $percent; ?>"
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $percent; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-secondary">
                <strong>ℹ️ Email tracking not configured</strong> for this campaign.
                <a href="newsletter_edit.php?id=<?php echo $campaign['newsletter_id']; ?>">Configure tracking URL</a>
                in newsletter settings to track email opens.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Failed Emails -->
    <?php if (!empty($failedEmails)): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h3 class="h5 mb-0">❌ Failed Emails (<?php echo count($failedEmails); ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Error</th>
                                    <th>Failed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failedEmails as $failed): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($failed['email']); ?></td>
                                    <td class="text-danger"><?php echo htmlspecialchars($failed['error_message'] ?? 'Unknown error'); ?></td>
                                    <td><?php echo $failed['failed_at'] ? date('M j, g:i A', strtotime($failed['failed_at'])) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($failedEmails) >= 20): ?>
                        <p class="small text-secondary mb-0">Showing first 20 failed emails. Check the email_queue table for complete list.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Campaign Preview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="h5 mb-0">📝 Email Content</h3>
                </div>
                <div class="card-body">
                    <h4 class="h6">HTML Preview:</h4>
                    <div class="border p-3 bg-body-secondary" style="max-height: 400px; overflow-y: auto;">
                        <?php echo $campaign['html_body']; ?>
                    </div>

                    <details class="mt-3">
                        <summary class="fw-bold">View Plain Text Version</summary>
                        <pre class="bg-body-tertiary text-body p-3 mt-2 rounded border"><?php echo htmlspecialchars($campaign['text_body']); ?></pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../templates/footer.php') ?>
