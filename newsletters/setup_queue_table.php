<?php
/**
 * Setup script to create email queue table
 * Run this once to create the necessary database structure
 */

try {
    $db = new PDO("sqlite:subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create email queue table
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
            ches_transaction_id TEXT,
            error_message TEXT,
            created_at TIMESTAMP NOT NULL,
            sent_at TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id)
        )
    ");
    
    // Create indexes for efficient querying
    $db->exec("CREATE INDEX IF NOT EXISTS idx_queue_status ON email_queue(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_queue_campaign ON email_queue(campaign_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_queue_created ON email_queue(created_at)");
    
    // Add a processed_count column to email_campaigns if it doesn't exist
    // This will track how many emails have been sent for progress tracking
    $db->exec("ALTER TABLE email_campaigns ADD COLUMN processed_count INTEGER DEFAULT 0");
    
    echo "Email queue table created successfully!\n";
    
} catch (PDOException $e) {
    // Check if column already exists (SQLite doesn't support IF NOT EXISTS for ALTER TABLE)
    if (strpos($e->getMessage(), 'duplicate column name') === false) {
        die("Error setting up queue table: " . $e->getMessage() . "\n");
    } else {
        echo "Email queue table already set up!\n";
    }
}