<?php
/**
 * Migration script to make API fields optional in newsletters table
 * Allows newsletters to be created without form API integration
 */

// Set timezone
date_default_timezone_set('America/Vancouver');

// Force CLI mode
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

echo "Newsletter API Fields Migration Script\n";
echo str_repeat("=", 80) . "\n";

$dbPath = '../data/subscriptions.db';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Force unlock and cleanup
    $db->exec("PRAGMA locking_mode=EXCLUSIVE;");
    $db->exec("BEGIN IMMEDIATE;");
    $db->exec("ROLLBACK;");
    $db->exec("PRAGMA locking_mode=NORMAL;");
    $db->exec("DROP TABLE IF EXISTS newsletters_new;");

    echo "Connected to database: $dbPath\n\n";

    // Get current schema
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='newsletters'");
    $currentSchema = $result->fetchColumn();

    echo "Current schema:\n";
    echo $currentSchema . "\n\n";

    // Check if migration is needed
    if (strpos($currentSchema, 'form_id TEXT NOT NULL') !== false) {
        echo "Migration needed: form_id, api_username, api_password are currently NOT NULL\n\n";

        // Begin transaction
        $db->beginTransaction();

        try {
            echo "Step 1: Creating new newsletters table with optional API fields...\n";

            // Create new table with optional fields
            $db->exec("
                CREATE TABLE newsletters_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    form_id TEXT UNIQUE,
                    api_username TEXT,
                    api_password TEXT,
                    api_url TEXT DEFAULT 'https://submit.digital.gov.bc.ca/app/api/v1/forms',
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NOT NULL,
                    is_active INTEGER DEFAULT 1
                )
            ");
            echo "  ✓ New table created\n\n";

            echo "Step 2: Copying data from old table to new table...\n";
            $db->exec("
                INSERT INTO newsletters_new
                SELECT * FROM newsletters
            ");
            echo "  ✓ Data copied\n\n";

            echo "Step 3: Dropping old table...\n";
            $db->exec("DROP TABLE newsletters");
            echo "  ✓ Old table dropped\n\n";

            echo "Step 4: Renaming new table to newsletters...\n";
            $db->exec("ALTER TABLE newsletters_new RENAME TO newsletters");
            echo "  ✓ Table renamed\n\n";

            // Commit transaction
            $db->commit();

            // Verify new schema
            $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='newsletters'");
            $newSchema = $result->fetchColumn();

            echo "New schema:\n";
            echo $newSchema . "\n\n";

            echo str_repeat("=", 80) . "\n";
            echo "✓ Migration completed successfully!\n";
            echo "API fields (form_id, api_username, api_password) are now optional.\n";

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } else {
        echo "No migration needed - API fields are already optional.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
