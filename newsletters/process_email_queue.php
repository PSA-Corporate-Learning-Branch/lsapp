<?php
/**
 * Email Queue Processor
 * Run this script via cron every minute to process queued emails
 * It respects the 30 emails/minute rate limit
 */

// Set timezone to PST/PDT (America/Vancouver covers BC)
date_default_timezone_set('America/Vancouver');

require_once '../inc/ches_client.php';

// Configuration
$BATCH_SIZE = 30; // Maximum emails to send per run (rate limit)
$MAX_ATTEMPTS = 3; // Maximum retry attempts for failed emails

// Database connection - use the database in data folder
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Queue processor: Database connection failed: " . $e->getMessage());
    exit(1);
}

// Create CHES client using environment variables
try {
    $chesClient = new CHESClient();
} catch (Exception $e) {
    error_log("Queue processor: CHES configuration error: " . $e->getMessage());
    exit(1);
}

// Test health check
if (!$chesClient->healthCheck()) {
    error_log("Queue processor: CHES API is not available");
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Email queue processor started\n";

// Get pending emails (limit to batch size)
try {
    $stmt = $db->prepare("
        SELECT id, campaign_id, recipient_email, subject, html_body, text_body, from_email, attempts
        FROM email_queue
        WHERE status = 'pending' AND attempts < ?
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$MAX_ATTEMPTS, $BATCH_SIZE]);
    $pendingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pendingEmails)) {
        echo "No pending emails to process\n";
        exit(0);
    }
    
    echo "Processing " . count($pendingEmails) . " emails\n";
    
    $successCount = 0;
    $failureCount = 0;
    $campaignUpdates = []; // Track which campaigns need status updates
    
    foreach ($pendingEmails as $email) {
        try {
            // Send the email
            $result = $chesClient->sendEmail(
                [$email['recipient_email']],
                $email['subject'],
                $email['text_body'],
                $email['html_body'],
                $email['from_email']
            );
            
            // Mark as sent
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET status = 'sent', 
                    sent_at = ?, 
                    ches_transaction_id = ?,
                    attempts = attempts + 1
                WHERE id = ?
            ");
            $updateStmt->execute([
                date('Y-m-d H:i:s'),
                $result['txId'] ?? null,
                $email['id']
            ]);
            
            $successCount++;
            $campaignUpdates[$email['campaign_id']] = true;
            
            echo "  ✓ Sent to " . $email['recipient_email'] . " (TX: " . ($result['txId'] ?? 'N/A') . ")\n";
            
        } catch (Exception $e) {
            $failureCount++;
            $errorMessage = $e->getMessage();
            
            // Update attempt count and error message
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET attempts = attempts + 1,
                    error_message = ?,
                    status = CASE 
                        WHEN attempts + 1 >= ? THEN 'failed'
                        ELSE 'pending'
                    END
                WHERE id = ?
            ");
            $updateStmt->execute([$errorMessage, $MAX_ATTEMPTS, $email['id']]);
            
            echo "  ✗ Failed to send to " . $email['recipient_email'] . ": " . $errorMessage . "\n";
        }
    }
    
    // Update campaign statuses
    foreach (array_keys($campaignUpdates) as $campaignId) {
        // Check if all emails for this campaign have been processed
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM email_queue
            WHERE campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update campaign processed count
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET processed_count = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$stats['sent'] + $stats['failed'], $campaignId]);
        
        // Update campaign status based on queue status
        $newStatus = 'sending';
        if ($stats['pending'] == 0) {
            // All emails processed
            $newStatus = $stats['failed'] > 0 ? 'completed_with_errors' : 'sent';
        }
        
        $updateStmt = $db->prepare("
            UPDATE email_campaigns 
            SET status = ?
            WHERE id = ? AND status IN ('queued', 'sending')
        ");
        $updateStmt->execute([$newStatus, $campaignId]);
        
        echo "Campaign $campaignId: " . $stats['sent'] . " sent, " . 
             $stats['pending'] . " pending, " . $stats['failed'] . " failed\n";
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Queue processing complete: $successCount sent, $failureCount failed\n";
    
} catch (PDOException $e) {
    error_log("Queue processor error: " . $e->getMessage());
    exit(1);
}