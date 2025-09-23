<?php
/**
 * Migration script to update campaign schema for progressive sending
 * Adds tracking fields for browser-based batch processing
 */

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Campaign Schema Migration\n";
    echo "=========================\n\n";
    
    // Add new columns to email_campaigns table
    echo "Updating email_campaigns table... ";
    
    // Check if columns already exist
    $stmt = $db->query("PRAGMA table_info(email_campaigns)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    $alterations = [];
    
    if (!in_array('last_processed_id', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN last_processed_id INTEGER DEFAULT 0";
    }
    
    if (!in_array('processing_status', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN processing_status TEXT DEFAULT 'pending'";
    }
    
    if (!in_array('processed_count', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN processed_count INTEGER DEFAULT 0";
    }
    
    if (!in_array('failed_count', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN failed_count INTEGER DEFAULT 0";
    }
    
    if (!in_array('paused_at', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN paused_at TIMESTAMP";
    }
    
    if (!in_array('completed_at', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN completed_at TIMESTAMP";
    }
    
    if (!in_array('newsletter_id', $columns)) {
        $alterations[] = "ALTER TABLE email_campaigns ADD COLUMN newsletter_id INTEGER";
    }
    
    foreach ($alterations as $sql) {
        $db->exec($sql);
    }
    
    echo "OK\n";
    
    // Create email_queue table if it doesn't exist
    echo "Creating/updating email_queue table... ";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            recipient_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            html_body TEXT NOT NULL,
            text_body TEXT,
            from_email TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            attempts INTEGER DEFAULT 0,
            sent_at TIMESTAMP,
            error_message TEXT,
            ches_transaction_id TEXT,
            created_at TIMESTAMP NOT NULL,
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id)
        )
    ");
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_queue_campaign_status ON email_queue(campaign_id, status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_queue_status ON email_queue(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_campaigns_status ON email_campaigns(processing_status)");
    
    echo "OK\n";
    
    // Update existing campaign statuses
    echo "Updating existing campaign statuses... ";
    
    // Map old statuses to new processing_status values
    $statusMapping = [
        'sent' => 'completed',
        'failed' => 'failed',
        'sending' => 'paused',
        'queued' => 'pending',
        'pending' => 'pending'
    ];
    
    foreach ($statusMapping as $old => $new) {
        $stmt = $db->prepare("UPDATE email_campaigns SET processing_status = ? WHERE status = ?");
        $stmt->execute([$new, $old]);
    }
    
    echo "OK\n";
    
    echo "\nMigration complete!\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}