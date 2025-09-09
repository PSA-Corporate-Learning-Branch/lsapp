<?php
/**
 * Test script for the email queue processor
 * Run this manually to test queue processing without waiting for cron
 */

echo "Testing Email Queue Processor\n";
echo "=============================\n\n";

// Check if process_email_queue.php exists
if (!file_exists('process_email_queue.php')) {
    die("Error: process_email_queue.php not found\n");
}

// Check database
if (!file_exists('subscriptions.db')) {
    die("Error: subscriptions.db not found\n");
}

// Check for pending emails
try {
    $db = new PDO("sqlite:subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'");
    $pendingCount = $stmt->fetchColumn();
    
    echo "Pending emails in queue: $pendingCount\n";
    
    if ($pendingCount > 0) {
        echo "\nRunning queue processor...\n";
        echo "----------------------------\n";
        
        // Run the processor
        include 'process_email_queue.php';
        
    } else {
        echo "No emails to process. Queue is empty.\n";
        
        // Show recent campaigns
        $stmt = $db->query("
            SELECT subject, status, sent_to_count, sent_at 
            FROM email_campaigns 
            ORDER BY sent_at DESC 
            LIMIT 5
        ");
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($campaigns)) {
            echo "\nRecent campaigns:\n";
            foreach ($campaigns as $campaign) {
                echo "- " . $campaign['subject'] . " (" . $campaign['status'] . ", " . 
                     $campaign['sent_to_count'] . " recipients, " . 
                     $campaign['sent_at'] . ")\n";
            }
        }
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}