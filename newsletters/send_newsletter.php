<?php
/**
 * Newsletter Sending Interface
 * Web UI for composing and sending newsletters to active subscribers
 */
require('../inc/lsapp.php');
require_once '../inc/ches_client.php';

// Get newsletter ID from query string
$newsletterId = isset($_GET['newsletter_id']) ? (int)$_GET['newsletter_id'] : 1;

// Database connection - use the database in data folder
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

// Initialize email history table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject TEXT NOT NULL,
            html_body TEXT NOT NULL,
            text_body TEXT,
            from_email TEXT NOT NULL,
            sent_to_count INTEGER DEFAULT 0,
            sent_at TIMESTAMP NOT NULL,
            ches_transaction_id TEXT,
            status TEXT DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP NOT NULL
        )
    ");
} catch (PDOException $e) {
    error_log("Failed to create email_campaigns table: " . $e->getMessage());
}

// Handle form submission
$message = '';
$messageType = '';
$isPreview = false;
$previewData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCsrfToken();

    try {
        $action = $_POST['action'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $htmlBody = trim($_POST['html_body'] ?? '');
        $fromEmail = trim($_POST['from_email'] ?? 'LearningHUB.Notification@gov.bc.ca');
        // Always use individual email sending for privacy
        
        // Validate inputs
        if (empty($subject)) {
            throw new Exception("Subject is required");
        }
        
        if (empty($htmlBody)) {
            throw new Exception("Message content is required");
        }
        
        if (!validateEmail($fromEmail)) {
            throw new Exception("Invalid sender email address");
        }
        
        // Generate plain text version from HTML
        $textBody = strip_tags($htmlBody);
        $textBody = html_entity_decode($textBody);
        $textBody = preg_replace('/\s+/', ' ', $textBody);
        $textBody = trim($textBody);
        
        if ($action === 'preview') {
            // Preview mode - show what will be sent
            $isPreview = true;
            
            // Get active subscribers for this specific newsletter
            $stmt = $db->prepare("SELECT email FROM subscriptions WHERE status = 'active' AND newsletter_id = ? ORDER BY email");
            $stmt->execute([$newsletterId]);
            $activeSubscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $previewData = [
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
                'from_email' => $fromEmail,
                'recipient_count' => count($activeSubscribers),
                'recipients' => $activeSubscribers
            ];
            
            $message = "Preview generated successfully. " . count($activeSubscribers) . " active subscribers will receive this email.";
            $messageType = 'success';
            
        } elseif ($action === 'send') {
            // Send the email
            
            // Get active subscribers for this specific newsletter
            $stmt = $db->prepare("SELECT email FROM subscriptions WHERE status = 'active' AND newsletter_id = ? ORDER BY email");
            $stmt->execute([$newsletterId]);
            $activeSubscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($activeSubscribers)) {
                throw new Exception("No active subscribers found");
            }
            
            // Create CHES client using environment variables
            try {
                $chesClient = new CHESClient();
            } catch (Exception $e) {
                throw new Exception("CHES configuration error: " . $e->getMessage());
            }
            
            // Test health check
            if (!$chesClient->healthCheck()) {
                throw new Exception("CHES API is not available");
            }
            
            $now = date('Y-m-d H:i:s');
            
            // Create campaign record
            $campaignStmt = $db->prepare("
                INSERT INTO email_campaigns (subject, html_body, text_body, from_email, sent_to_count, sent_at, processing_status, status, created_at, newsletter_id)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', 'queued', ?, ?)
            ");
            $campaignStmt->execute([$subject, $htmlBody, $textBody, $fromEmail, count($activeSubscribers), $now, $now, $newsletterId]);
            $campaignId = $db->lastInsertId();
            
            try {
                // Queue emails for background processing instead of sending directly
                $db->beginTransaction();

                try {
                    // Insert all emails into the queue
                    $queueStmt = $db->prepare("
                        INSERT INTO email_queue (campaign_id, recipient_email, subject, html_body, text_body, from_email, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
                    ");

                    $queuedCount = 0;
                    foreach ($activeSubscribers as $subscriber) {
                        // Inject tracking pixel for this recipient
                        $htmlBodyWithTracking = injectTrackingPixel(
                            $htmlBody,
                            $newsletter['tracking_url'] ?? null,
                            $subscriber,
                            $newsletterId,
                            $campaignId
                        );

                        $queueStmt->execute([
                            $campaignId,
                            $subscriber,
                            $subject,
                            $htmlBodyWithTracking,
                            $textBody,
                            $fromEmail,
                            $now
                        ]);
                        $queuedCount++;
                    }
                    
                    $db->commit();
                    
                    // Redirect to campaign monitor page
                    header('Location: campaign_monitor.php?campaign_id=' . $campaignId . '&start=1');
                    exit();
                
                    // Clear form data after successful queuing
                    $subject = '';
                    $htmlBody = '';
                    $fromEmail = 'LearningHUB.Notification@gov.bc.ca';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                
            } catch (Exception $e) {
                // Update campaign with error
                $updateStmt = $db->prepare("
                    UPDATE email_campaigns 
                    SET status = 'failed', error_message = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$e->getMessage(), $campaignId]);
                
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        $message = getUserFriendlyError($e);
        $messageType = 'error';
    }
}

// Get recent campaigns with queue progress
$recentCampaigns = [];
$incompleteCampaigns = [];
try {
    // Get incomplete campaigns first
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
        WHERE c.newsletter_id = ? AND c.processing_status IN ('pending', 'processing', 'paused')
        ORDER BY c.sent_at DESC
    ");
    $stmt->execute([$newsletterId]);
    $incompleteCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all recent campaigns
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

// Get subscriber count for this specific newsletter
$subscriberCount = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND newsletter_id = ?");
    $stmt->execute([$newsletterId]);
    $subscriberCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to get subscriber count: " . $e->getMessage());
}
?>
<?php getHeader() ?>
<title>Send Newsletter</title>
<?php getScripts() ?>
</head>
<body>
<?php getNavigation() ?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1>Send <?php echo htmlspecialchars($newsletter['name']); ?></h1>
            <p class="text-secondary">Compose and send newsletters to your active subscribers</p>
            <div class="mb-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">← All Newsletters</a>
                <a href="newsletter_dashboard.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary me-2">Dashboard</a>
                <a href="sync_subscriptions.php?newsletter_id=<?php echo $newsletterId; ?>" class="btn btn-sm btn-outline-primary">🔄 Sync Subscriptions</a>
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
                elseif($messageType === 'warning') $alertClass .= ' alert-warning';
                else $alertClass .= ' alert-info';
            ?>
            <div class="<?php echo $alertClass; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($isPreview && $previewData): ?>
            <section class="card border-warning mb-4">
                <div class="card-header bg-warning-subtle">
                    <h2 class="card-title h4 mb-0">📧 Email Preview</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Will be sent to <strong><?php echo $previewData['recipient_count']; ?></strong> active subscribers via individual emails (privacy protected)
                        <?php 
                        $estimatedBatches = ceil($previewData['recipient_count'] / 30);
                        if ($estimatedBatches > 1): 
                            $estimatedTime = ($estimatedBatches - 1) * 60;
                            $minutes = floor($estimatedTime / 60);
                            $seconds = $estimatedTime % 60;
                        ?>
                            <br><strong>⏱️ Rate Limiting:</strong> Emails will be sent in <?php echo $estimatedBatches; ?> batches (30 emails/minute).
                            Estimated sending time: <?php echo $minutes > 0 ? "$minutes min $seconds sec" : "$seconds seconds"; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-light-subtle p-3 rounded mb-3">
                        <h3 class="h5">Subject: <?php echo htmlspecialchars($previewData['subject']); ?></h3>
                        <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($previewData['from_email']); ?></p>
                        <p class="mb-1"><strong>Delivery:</strong> Individual emails to each subscriber</p>
                        <p class="small text-secondary mb-0">Note: Each subscriber receives a separate email - they won't see other recipients.</p>
                    </div>
                    
                    <h4 class="h6">HTML Content:</h4>
                    <div class="border p-3 mb-3 bg-white text-dark rounded">
                        <?php echo $previewData['html_body']; ?>
                    </div>
                    
                    <h4 class="h6">Plain Text Version:</h4>
                    <pre class="bg-light-subtle p-3 rounded"><?php echo htmlspecialchars($previewData['text_body']); ?></pre>
                    
                    <details class="mt-3">
                        <summary class="fw-bold">View Recipients (<?php echo count($previewData['recipients']); ?>)</summary>
                        <div class="bg-light-subtle p-3 mt-2 rounded" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.9rem;">
                            <?php foreach ($previewData['recipients'] as $email): ?>
                                <?php echo htmlspecialchars($email); ?><br>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    
                    <form method="post" class="mt-4" role="group" aria-labelledby="final-send-label">
                        <div id="final-send-label" class="visually-hidden">Final newsletter sending confirmation</div>

                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($previewData['subject']); ?>">
                        <input type="hidden" name="html_body" value="<?php echo htmlspecialchars($previewData['html_body']); ?>">
                        <input type="hidden" name="from_email" value="<?php echo htmlspecialchars($previewData['from_email']); ?>">
                        
                        <button type="submit" class="btn btn-success me-2" 
                                onclick="return confirm('Send newsletter to <?php echo $previewData['recipient_count']; ?> subscribers via individual emails? This action cannot be undone.')"
                                aria-describedby="final-send-description">
                            <span aria-hidden="true">✉️</span> Send Newsletter Now
                            <span class="visually-hidden"> to <?php echo $previewData['recipient_count']; ?> subscribers</span>
                        </button>
                        
                        <div id="final-send-description" class="visually-hidden">
                            Send newsletter "<?php echo htmlspecialchars($previewData['subject']); ?>" to <?php echo $previewData['recipient_count']; ?> active subscribers via individual emails
                        </div>
                        
                        <a href="send_newsletter.php?newsletter_id=<?php echo $newsletterId; ?>" 
                           class="btn btn-secondary"
                           aria-label="Cancel sending and return to compose form">Cancel</a>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($incompleteCampaigns)): ?>
            <section class="alert alert-warning mb-4">
                <h4 class="alert-heading">⚠️ Incomplete Campaigns</h4>
                <p>You have campaigns that haven't finished sending. Resume or monitor them below:</p>
                <div class="list-group">
                    <?php foreach ($incompleteCampaigns as $campaign): ?>
                        <?php 
                            $progress = 0;
                            if ($campaign['total_count'] > 0) {
                                $progress = round(($campaign['sent_count'] / $campaign['total_count']) * 100, 1);
                            }
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($campaign['subject']); ?></h6>
                                    <small class="text-muted">
                                        Status: <strong><?php echo ucfirst($campaign['processing_status']); ?></strong> | 
                                        Progress: <?php echo $campaign['sent_count']; ?>/<?php echo $campaign['total_count']; ?> 
                                        (<?php echo $progress; ?>%)
                                    </small>
                                </div>
                                <a href="campaign_monitor.php?campaign_id=<?php echo $campaign['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <?php if ($campaign['processing_status'] === 'paused'): ?>
                                        ▶️ Resume
                                    <?php else: ?>
                                        📊 Monitor
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card bg-light-subtle mb-4">
            <div class="card-body">
                <h2 class="card-title">Compose Newsletter</h2>
                
                <div class="alert alert-info">
                    📊 You have <strong><?php echo $subscriberCount; ?></strong> active subscribers
                    <?php 
                    $estimatedBatches = ceil($subscriberCount / 30);
                    if ($estimatedBatches > 1): 
                        $estimatedTime = ($estimatedBatches - 1) * 60;
                        $minutes = floor($estimatedTime / 60);
                        $seconds = $estimatedTime % 60;
                    ?>
                        <br><small>⏱️ Rate limiting: Emails will be sent in <?php echo $estimatedBatches; ?> batches (30/minute, ~<?php echo $minutes > 0 ? "$minutes min $seconds sec" : "$seconds sec"; ?> total)</small>
                    <?php endif; ?>
                </div>
                
                <form method="post">
                    <?php csrfField(); ?>
                    <div class="mb-3">
                        <label for="from_email" class="form-label">From Email Address</label>
                        <input type="email" id="from_email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($fromEmail ?? 'donotreply_psa@gov.bc.ca'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject Line</label>
                        <input type="text" id="subject" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject ?? ''); ?>" placeholder="Enter your newsletter subject..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="html_body" class="form-label">
                            HTML Message Content <span class="text-danger" aria-label="required">*</span>
                        </label>
                        <textarea id="html_body" name="html_body" class="form-control" 
                                  style="min-height: 300px; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;" 
                                  placeholder="Enter your HTML newsletter content here..." 
                                  aria-describedby="html-body-help html-body-tips"
                                  required><?php echo htmlspecialchars($htmlBody ?? ''); ?></textarea>
                        <div id="html-body-help" class="form-text text-secondary">
                            Enter HTML markup for your newsletter. A plain text version will be automatically generated.
                        </div>
                        <div id="html-body-tips" class="form-text text-info">
                            <small>For better accessibility, use semantic HTML: headings (&lt;h1&gt;-&lt;h6&gt;), paragraphs (&lt;p&gt;), lists (&lt;ul&gt;, &lt;ol&gt;), and meaningful alt text for images.</small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2" role="group" aria-labelledby="send-actions-label">
                        <div id="send-actions-label" class="visually-hidden">Newsletter sending options</div>
                        
                        <button type="submit" name="action" value="preview" class="btn btn-primary"
                                aria-describedby="preview-help">
                            <span aria-hidden="true">👀</span> Preview Newsletter
                        </button>
                        <div id="preview-help" class="visually-hidden">Review newsletter content and recipient list before sending</div>
                        
                        <?php if ($subscriberCount > 0): ?>
                            <button type="submit" name="action" value="send" class="btn btn-success" 
                                    onclick="return confirm('Send newsletter to <?php echo $subscriberCount; ?> subscribers without previewing? This action cannot be undone.')"
                                    aria-describedby="send-immediately-help">
                                <span aria-hidden="true">✉️</span> Send Immediately
                                <span class="visually-hidden"> to <?php echo $subscriberCount; ?> subscribers</span>
                            </button>
                            <div id="send-immediately-help" class="visually-hidden">Send newsletter directly to all <?php echo $subscriberCount; ?> active subscribers without preview</div>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled 
                                    aria-label="Cannot send newsletter - no active subscribers found">
                                <span aria-hidden="true">✉️</span> No Active Subscribers
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <?php if (!empty($recentCampaigns)): ?>
            <section class="card bg-light-subtle">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Recent Newsletter Campaigns</h2>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentCampaigns as $campaign): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($campaign['subject']); ?></strong><br>
                                    <small class="text-secondary">
                                        Created <?php echo date('M j, Y g:i A', strtotime($campaign['sent_at'])); ?> 
                                        for <?php echo $campaign['sent_to_count']; ?> subscribers
                                        
                                        <?php if ($campaign['status'] == 'queued' || $campaign['status'] == 'sending'): ?>
                                            <br>📊 Progress: <?php echo $campaign['sent_count']; ?> sent, 
                                            <?php echo $campaign['pending_count']; ?> pending
                                            <?php if ($campaign['failed_count'] > 0): ?>
                                                , <span class="text-danger"><?php echo $campaign['failed_count']; ?> failed</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($campaign['status'] == 'sent' || $campaign['status'] == 'completed_with_errors'): ?>
                                            <br>✅ Completed: <?php echo $campaign['sent_count']; ?> sent
                                            <?php if ($campaign['failed_count'] > 0): ?>
                                                , <span class="text-danger"><?php echo $campaign['failed_count']; ?> failed</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($campaign['error_message']): ?>
                                            <br><span class="text-danger">Error: <?php echo htmlspecialchars($campaign['error_message']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
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
                                    <span class="<?php echo $badgeClass; ?> mb-2">
                                        <?php echo ucfirst($statusText); ?>
                                    </span>
                                    <br>
                                    <?php if (in_array($campaign['processing_status'], ['pending', 'processing', 'paused'])): ?>
                                        <a href="campaign_monitor.php?campaign_id=<?php echo $campaign['id']; ?>"
                                           class="btn btn-sm btn-outline-warning mt-1">
                                            📊 Monitor
                                        </a>
                                    <?php else: ?>
                                        <a href="campaign_dashboard.php?campaign_id=<?php echo $campaign['id']; ?>"
                                           class="btn btn-sm btn-outline-primary mt-1">
                                            📊 View Stats
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php include('../templates/footer.php') ?>