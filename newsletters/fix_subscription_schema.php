<?php
/**
 * Fix subscription table schema to support multiple newsletters per email
 * Changes email from PRIMARY KEY to allow same email across different newsletter_id
 */

// Database connection
try {
    $db = new PDO("sqlite:../data/subscriptions.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting subscription schema migration...\n";
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check current schema
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='subscriptions'");
    $currentSchema = $result->fetchColumn();
    echo "Current schema: $currentSchema\n";
    
    // Check if migration is needed
    if (strpos($currentSchema, 'email TEXT PRIMARY KEY') !== false) {
        echo "Migration needed: email is currently PRIMARY KEY\n";
        
        // Create new table with correct schema
        $db->exec("
            CREATE TABLE subscriptions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                newsletter_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL,
                source TEXT DEFAULT 'form',
                UNIQUE(email, newsletter_id),
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
            )
        ");
        echo "Created new table with correct schema\n";
        
        // Copy existing data
        $db->exec("
            INSERT INTO subscriptions_new (email, newsletter_id, status, created_at, updated_at, source)
            SELECT email, newsletter_id, status, created_at, updated_at, source
            FROM subscriptions
        ");
        echo "Copied existing data\n";
        
        // Drop old table and rename new one
        $db->exec("DROP TABLE subscriptions");
        $db->exec("ALTER TABLE subscriptions_new RENAME TO subscriptions");
        echo "Replaced old table with new schema\n";
        
        // Commit transaction
        $db->commit();
        echo "✅ Migration completed successfully!\n";
        echo "Email can now subscribe to multiple newsletters\n";
        
    } else {
        echo "✅ Schema is already correct - no migration needed\n";
        $db->rollback();
    }
    
    // Verify final schema
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='subscriptions'");
    $newSchema = $result->fetchColumn();
    echo "\nFinal schema: $newSchema\n";
    
    // Show current data
    echo "\nCurrent subscription data:\n";
    $stmt = $db->query("SELECT id, email, newsletter_id, status FROM subscriptions ORDER BY newsletter_id, email");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ID {$row['id']}: {$row['email']} -> Newsletter {$row['newsletter_id']} ({$row['status']})\n";
    }
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollback();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    die("Error: " . $e->getMessage() . "\n");
}

echo "\n🎉 You can now sync subscriptions for multiple newsletters!\n";
?>