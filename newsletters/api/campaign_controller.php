<?php
/**
 * API Controller for Campaign Management
 * Handles batch sending, status updates, and campaign control
 */

require_once('../../inc/lsapp.php');
require_once('../../inc/ches_client.php');

header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database connection
try {
    $db = new PDO("sqlite:../../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get action from request
$action = $_REQUEST['action'] ?? '';
$campaignId = isset($_REQUEST['campaign_id']) ? (int)$_REQUEST['campaign_id'] : 0;

// Route to appropriate handler
switch ($action) {
    case 'send_batch':
        handleSendBatch($db, $campaignId);
        break;
        
    case 'get_status':
        handleGetStatus($db, $campaignId);
        break;
        
    case 'pause':
        handlePauseCampaign($db, $campaignId);
        break;
        
    case 'resume':
        handleResumeCampaign($db, $campaignId);
        break;
        
    case 'cancel':
        handleCancelCampaign($db, $campaignId);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * Send a batch of emails for a campaign
 */
function handleSendBatch($db, $campaignId) {
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        return;
    }
    
    // Get campaign details
    $stmt = $db->prepare("
        SELECT c.*, n.id as newsletter_id
        FROM email_campaigns c
        LEFT JOIN newsletters n ON c.newsletter_id = n.id
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        return;
    }
    
    // Check if campaign is paused or completed
    if ($campaign['processing_status'] === 'completed') {
        echo json_encode([
            'status' => 'completed',
            'message' => 'Campaign already completed'
        ]);
        return;
    }
    
    if ($campaign['processing_status'] === 'paused') {
        echo json_encode([
            'status' => 'paused',
            'message' => 'Campaign is paused'
        ]);
        return;
    }
    
    // Update status to processing
    if ($campaign['processing_status'] === 'pending') {
        $updateStmt = $db->prepare("UPDATE email_campaigns SET processing_status = 'processing' WHERE id = ?");
        $updateStmt->execute([$campaignId]);
    }
    
    // Get batch of pending emails (30 per batch for rate limiting)
    $batchSize = 30;
    $stmt = $db->prepare("
        SELECT * FROM email_queue 
        WHERE campaign_id = ? AND status = 'pending'
        ORDER BY id
        LIMIT ?
    ");
    $stmt->execute([$campaignId, $batchSize]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emails)) {
        // No more emails to send, mark campaign as completed
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET processing_status = 'completed', completed_at = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([date('Y-m-d H:i:s'), $campaignId]);
        
        echo json_encode([
            'status' => 'completed',
            'processed' => 0,
            'message' => 'All emails sent'
        ]);
        return;
    }
    
    // Initialize CHES client
    try {
        $chesClient = new CHESClient();
        if (!$chesClient->healthCheck()) {
            throw new Exception("CHES API is not available");
        }
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Email service unavailable',
            'details' => $e->getMessage()
        ]);
        return;
    }
    
    $processed = 0;
    $failed = 0;
    $errors = [];
    
    // Send each email
    foreach ($emails as $email) {
        try {
            // Send email via CHES
            $result = $chesClient->sendEmail(
                [$email['recipient_email']],
                $email['subject'],
                $email['text_body'] ?? strip_tags($email['html_body']),
                $email['html_body'],
                $email['from_email']
            );
            
            // Update email status to sent
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET status = 'sent', sent_at = ?, ches_transaction_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                date('Y-m-d H:i:s'),
                $result['txId'] ?? null,
                $email['id']
            ]);
            
            $processed++;
            
        } catch (Exception $e) {
            // Update email status to failed
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET status = 'failed', attempts = attempts + 1, error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $e->getMessage(),
                $email['id']
            ]);
            
            $failed++;
            $errors[] = "Failed to send to {$email['recipient_email']}: {$e->getMessage()}";
            
            // If too many failures, pause the campaign
            if ($failed >= 5) {
                $updateStmt = $db->prepare("
                    UPDATE email_campaigns 
                    SET processing_status = 'paused', paused_at = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([date('Y-m-d H:i:s'), $campaignId]);
                
                echo json_encode([
                    'status' => 'paused',
                    'processed' => $processed,
                    'failed' => $failed,
                    'errors' => $errors,
                    'message' => 'Campaign paused due to multiple failures'
                ]);
                return;
            }
        }
        
        // Update last processed ID
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET last_processed_id = ?, 
                processed_count = processed_count + 1
            WHERE id = ?
        ");
        $updateStmt->execute([$email['id'], $campaignId]);
    }
    
    // Update failed count if any
    if ($failed > 0) {
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET failed_count = failed_count + ?
            WHERE id = ?
        ");
        $updateStmt->execute([$failed, $campaignId]);
    }
    
    // Check if all emails are processed
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM email_queue 
        WHERE campaign_id = ? AND status = 'pending'
    ");
    $stmt->execute([$campaignId]);
    $remaining = $stmt->fetchColumn();
    
    if ($remaining == 0) {
        // Mark campaign as completed
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET processing_status = 'completed', completed_at = ?
            WHERE id = ?
        ");
        $updateStmt->execute([date('Y-m-d H:i:s'), $campaignId]);
        
        $status = 'completed';
    } else {
        $status = 'processing';
    }
    
    echo json_encode([
        'status' => $status,
        'processed' => $processed,
        'failed' => $failed,
        'remaining' => $remaining,
        'errors' => $errors
    ]);
}

/**
 * Get campaign status
 */
function handleGetStatus($db, $campaignId) {
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        return;
    }
    
    // Get campaign with statistics
    $stmt = $db->prepare("
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id) as total_emails,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'sent') as sent_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM email_queue WHERE campaign_id = c.id AND status = 'failed') as failed_count
        FROM email_campaigns c
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        return;
    }
    
    // Calculate progress percentage
    $progress = 0;
    if ($campaign['total_emails'] > 0) {
        $progress = round(($campaign['sent_count'] + $campaign['failed_count']) / $campaign['total_emails'] * 100, 1);
    }
    
    // Calculate estimated time remaining
    $estimatedMinutes = 0;
    if ($campaign['pending_count'] > 0) {
        $estimatedMinutes = ceil($campaign['pending_count'] / 30); // 30 emails per minute
    }
    
    echo json_encode([
        'campaign_id' => $campaign['id'],
        'subject' => $campaign['subject'],
        'status' => $campaign['processing_status'],
        'total' => $campaign['total_emails'],
        'sent' => $campaign['sent_count'],
        'pending' => $campaign['pending_count'],
        'failed' => $campaign['failed_count'],
        'progress' => $progress,
        'estimated_minutes' => $estimatedMinutes,
        'created_at' => $campaign['created_at'],
        'completed_at' => $campaign['completed_at']
    ]);
}

/**
 * Pause a campaign
 */
function handlePauseCampaign($db, $campaignId) {
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE email_campaigns 
        SET processing_status = 'paused', paused_at = ?
        WHERE id = ? AND processing_status = 'processing'
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $campaignId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Campaign paused']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campaign not in processing state']);
    }
}

/**
 * Resume a paused campaign
 */
function handleResumeCampaign($db, $campaignId) {
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE email_campaigns 
        SET processing_status = 'processing', paused_at = NULL
        WHERE id = ? AND processing_status = 'paused'
    ");
    $stmt->execute([$campaignId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Campaign resumed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campaign not paused']);
    }
}

/**
 * Cancel a campaign
 */
function handleCancelCampaign($db, $campaignId) {
    if (!$campaignId) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign ID required']);
        return;
    }
    
    // Mark campaign as cancelled
    $stmt = $db->prepare("
        UPDATE email_campaigns 
        SET processing_status = 'cancelled', completed_at = ?
        WHERE id = ? AND processing_status IN ('pending', 'processing', 'paused')
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $campaignId]);
    
    if ($stmt->rowCount() > 0) {
        // Mark all pending emails as cancelled
        $stmt = $db->prepare("
            UPDATE email_queue 
            SET status = 'cancelled'
            WHERE campaign_id = ? AND status = 'pending'
        ");
        $stmt->execute([$campaignId]);
        
        echo json_encode(['success' => true, 'message' => 'Campaign cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campaign cannot be cancelled']);
    }
}