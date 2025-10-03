<?php
/**
 * Safe migration using backup/restore approach
 * This avoids the table locking issues with in-place schema changes
 */

date_default_timezone_set('America/Vancouver');

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

echo "Safe Newsletter API Fields Migration\n";
echo str_repeat("=", 80) . "\n";

$dbPath = '../data/subscriptions.db';
$backupPath = '../data/subscriptions_backup_' . date('Ymd_His') . '.db';

try {
    // Step 1: Create a backup
    echo "Step 1: Creating backup at $backupPath\n";
    if (!copy($dbPath, $backupPath)) {
        throw new Exception("Failed to create backup");
    }
    echo "  ✓ Backup created\n\n";

    // Step 2: Check current schema
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='newsletters'");
    $currentSchema = $result->fetchColumn();

    echo "Current schema:\n";
    echo $currentSchema . "\n\n";

    if (strpos($currentSchema, 'form_id TEXT NOT NULL') === false) {
        echo "No migration needed - API fields are already optional.\n";
        unlink($backupPath);
        exit(0);
    }

    echo "Migration needed.\n\n";

    // Step 3: Export all data
    echo "Step 2: Exporting newsletter data...\n";
    $stmt = $db->query("SELECT * FROM newsletters ORDER BY id");
    $newsletters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  ✓ Exported " . count($newsletters) . " newsletters\n\n";

    // Step 4: Close connection completely
    echo "Step 3: Closing database connection...\n";
    $stmt = null;
    $db = null;
    sleep(2);
    echo "  ✓ Connection closed\n\n";

    // Step 5: Switch to DELETE mode in new connection
    echo "Step 4: Switching to DELETE journal mode...\n";
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA wal_checkpoint(TRUNCATE);");
    $db->exec("PRAGMA journal_mode=DELETE;");
    echo "  ✓ Switched to DELETE mode\n\n";

    $db = null; // Close connection again
    sleep(2);

    // Step 6: Reopen and drop/recreate
    echo "Step 5: Recreating newsletters table...\n";
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("DROP TABLE IF EXISTS newsletters");
    echo "  ✓ Old table dropped\n";

    $db->exec("
        CREATE TABLE newsletters (
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
    echo "  ✓ New table created with optional API fields\n\n";

    // Step 7: Restore data
    echo "Step 6: Restoring newsletter data...\n";
    $stmt = $db->prepare("
        INSERT INTO newsletters (id, name, description, form_id, api_username, api_password, api_url, created_at, updated_at, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($newsletters as $newsletter) {
        $stmt->execute([
            $newsletter['id'],
            $newsletter['name'],
            $newsletter['description'],
            $newsletter['form_id'],
            $newsletter['api_username'],
            $newsletter['api_password'],
            $newsletter['api_url'],
            $newsletter['created_at'],
            $newsletter['updated_at'],
            $newsletter['is_active']
        ]);
    }
    echo "  ✓ Restored " . count($newsletters) . " newsletters\n\n";

    // Step 8: Switch back to WAL mode
    echo "Step 7: Switching back to WAL mode...\n";
    $db->exec("PRAGMA journal_mode=WAL;");
    echo "  ✓ Switched back to WAL mode\n\n";

    // Verify
    $result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='newsletters'");
    $newSchema = $result->fetchColumn();

    echo "New schema:\n";
    echo $newSchema . "\n\n";

    echo str_repeat("=", 80) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo "Backup saved at: $backupPath\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    if (file_exists($backupPath)) {
        echo "\nRestoring from backup...\n";
        copy($backupPath, $dbPath);
        echo "Database restored from backup.\n";
    }
    exit(1);
}
?>
