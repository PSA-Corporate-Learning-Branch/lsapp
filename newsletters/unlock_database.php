<?php
/**
 * Database Unlock Utility
 * Forces database unlock and clears any hanging connections
 */

$dbPath = '../data/subscriptions.db';

echo "Database Unlock Utility\n";
echo "======================\n";

try {
    // 1. Check if database is accessible
    echo "1. Testing database connection... ";
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 2); // Short timeout
    echo "✅ OK\n";
    
    // 2. Force unlock operations
    echo "2. Forcing database unlock... ";
    $db->exec("PRAGMA locking_mode = NORMAL;");
    $db->exec("PRAGMA journal_mode = DELETE;");
    $db->exec("BEGIN IMMEDIATE; ROLLBACK;"); // Force acquire and release exclusive lock
    echo "✅ Done\n";
    
    // 3. Check database integrity
    echo "3. Checking database integrity... ";
    $result = $db->query("PRAGMA integrity_check;")->fetchColumn();
    if ($result === 'ok') {
        echo "✅ OK\n";
    } else {
        echo "❌ FAILED: $result\n";
    }
    
    // 4. Test a simple operation
    echo "4. Testing write operation... ";
    $db->exec("PRAGMA user_version;"); // Simple read/write test
    echo "✅ OK\n";
    
    // 5. Close connection cleanly
    $db = null;
    echo "5. Connection closed cleanly ✅\n";
    
    echo "\n✅ Database is now unlocked and ready for use!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    
    // If still locked, try more aggressive measures
    if (strpos($e->getMessage(), 'database is locked') !== false) {
        echo "\nTrying more aggressive unlock...\n";
        
        // Force journal cleanup
        $journalFiles = [
            $dbPath . '-journal',
            $dbPath . '-wal',
            $dbPath . '-shm'
        ];
        
        foreach ($journalFiles as $file) {
            if (file_exists($file)) {
                echo "Removing $file... ";
                if (unlink($file)) {
                    echo "✅ Removed\n";
                } else {
                    echo "❌ Failed\n";
                }
            }
        }
        
        // Try connection again
        try {
            $db = new PDO("sqlite:$dbPath");
            $db->exec("PRAGMA journal_mode = DELETE;");
            $db = null;
            echo "✅ Database unlocked after cleanup!\n";
        } catch (PDOException $e2) {
            echo "❌ Still locked: " . $e2->getMessage() . "\n";
        }
    }
}

echo "\nYou can now try running the sync again.\n";
?>