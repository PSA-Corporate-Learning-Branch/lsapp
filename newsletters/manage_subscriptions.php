<?php
/**
 * Script to manage email subscriptions from BC Gov Digital Forms API
 * Stores subscribe/unsubscribe actions in a SQLite database
 * Can be run via CLI or triggered from web interface
 */

// Set timezone to PST/PDT (America/Vancouver covers BC)
date_default_timezone_set('America/Vancouver');

// Include encryption helper for decrypting API passwords
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

// Check if we're running from web or CLI
$isWeb = (php_sapi_name() !== 'cli');

class SubscriptionManager {
    private $db;
    private $dbPath;
    private $newsletter;
    private $newsletterId;
    
    public function __construct($newsletterId = 1, $dbPath = '../data/subscriptions.db') {
        $this->dbPath = $dbPath;
        $this->newsletterId = $newsletterId;
        $this->initDatabase();
        $this->loadNewsletter();
    }
    
    public function __destruct() {
        $this->closeDatabase();
    }
    
    public function closeDatabase() {
        if ($this->db) {
            // Ensure any pending transaction is closed
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->db = null;
        }
    }
    
    private function reconnectDatabase() {
        $this->closeDatabase();
        $this->initDatabase();
    }
    
    private function forceUnlockDatabase() {
        // Last resort: try to force unlock by connecting with immediate mode
        try {
            $tempDb = new PDO("sqlite:{$this->dbPath}");
            $tempDb->exec("PRAGMA locking_mode=EXCLUSIVE;");
            $tempDb->exec("BEGIN IMMEDIATE;");
            $tempDb->exec("ROLLBACK;");
            $tempDb->exec("PRAGMA locking_mode=NORMAL;");
            $tempDb = null;
            echo "  Forced database unlock attempt completed\n";
        } catch (Exception $e) {
            echo "  Force unlock failed: " . $e->getMessage() . "\n";
        }
    }
    
    private function loadNewsletter() {
        $stmt = $this->db->prepare("SELECT * FROM newsletters WHERE id = ? AND is_active = 1");
        $stmt->execute([$this->newsletterId]);
        $this->newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->newsletter) {
            die("Newsletter not found or inactive: {$this->newsletterId}\n");
        }
        
        echo "Using newsletter: {$this->newsletter['name']} (ID: {$this->newsletterId})\n";
    }
    
    private function initDatabase() {
        try {
            // Use more aggressive timeout settings for SQLite
            $dsn = "sqlite:{$this->dbPath}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 60, // Increased from 30 seconds
                PDO::ATTR_PERSISTENT => false, // Don't use persistent connections
            ];
            
            $this->db = new PDO($dsn, '', '', $options);
            
            // WAL mode allows for better concurrency - readers don't block writers
            $this->db->exec("PRAGMA journal_mode=WAL;");
            $this->db->exec("PRAGMA synchronous=NORMAL;");
            $this->db->exec("PRAGMA busy_timeout=30000;"); // 30 second timeout for locks
            $this->db->exec("PRAGMA wal_autocheckpoint=1000;"); // Checkpoint WAL after 1000 pages
            $this->db->exec("PRAGMA locking_mode=NORMAL;"); // Ensure normal locking mode
            $this->db->exec("PRAGMA temp_store=MEMORY;"); // Store temp data in memory
            $this->db->exec("PRAGMA cache_size=10000;"); // Larger cache
            
            // Create subscriptions table (matches current schema)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS subscriptions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL,
                    newsletter_id INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NOT NULL,
                    source TEXT DEFAULT 'form',
                    UNIQUE(email, newsletter_id)
                )
            ");
            
            // Create subscription history table (matches current schema)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS subscription_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL,
                    newsletter_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    timestamp TIMESTAMP NOT NULL,
                    submission_id TEXT,
                    raw_data TEXT
                )
            ");
            
            // Create last sync tracking table (matches current schema)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS last_sync (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    newsletter_id INTEGER NOT NULL,
                    last_sync_timestamp TEXT NOT NULL,
                    sync_type TEXT DEFAULT 'submissions',
                    records_processed INTEGER DEFAULT 0,
                    created_at TIMESTAMP NOT NULL
                )
            ");
            
        } catch (PDOException $e) {
            die("Database initialization failed: " . $e->getMessage() . "\n");
        }
    }
    
    private function getLastSyncTime() {
        $stmt = $this->db->prepare("SELECT last_sync_timestamp FROM last_sync WHERE sync_type = 'submissions' AND newsletter_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->newsletterId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['last_sync_timestamp'] : null;
    }
    
    private function updateLastSyncTime($recordsProcessed = 0) {
        $timestamp = date('c'); // ISO 8601 format
        $now = date('Y-m-d H:i:s');
        
        $maxRetries = 5;
        $retryDelay = 200000; // 200ms in microseconds (increased from 100ms)
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // On retry attempts after the first, reconnect to database
                if ($attempt > 1) {
                    echo "Reconnecting to database for sync time update (attempt $attempt)...\n";
                    $this->reconnectDatabase();
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO last_sync (last_sync_timestamp, sync_type, records_processed, created_at, newsletter_id)
                    VALUES (?, 'submissions', ?, ?, ?)
                ");
                $stmt->execute([$timestamp, $recordsProcessed, $now, $this->newsletterId]);
                return; // Success, exit retry loop
                
            } catch (PDOException $e) {
                if ($e->getCode() == 'HY000' && strpos($e->getMessage(), 'database is locked') !== false) {
                    if ($attempt < $maxRetries) {
                        echo "Database locked (attempt $attempt/$maxRetries), retrying in " . ($retryDelay/1000) . "ms...\n";
                        usleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                    } else {
                        echo "Database remained locked after $maxRetries attempts, skipping sync time update\n";
                        return;
                    }
                } else {
                    throw $e; // Re-throw if it's not a lock error
                }
            }
        }
    }
    
    public function fetchSubmissions($sinceDate = null) {
        $url = $this->newsletter['api_url'] . '/' . $this->newsletter['form_id'] . '/export';
        
        $params = [
            'format' => 'json',
            'type' => 'submissions'
        ];
        
        // Add preference parameter to fetch only new/updated submissions
        if ($sinceDate) {
            $preference = json_encode([
                'updatedMinDate' => $sinceDate
            ]);
            $params['preference'] = $preference;
            echo "Fetching submissions updated since: $sinceDate\n";
        } else {
            echo "Fetching all submissions (initial sync)\n";
        }
        
        $queryString = http_build_query($params);
        
        $username = $this->newsletter['api_username'];
        $encryptedPassword = $this->newsletter['api_password'];
        
        // Decrypt the password
        try {
            $password = EncryptionHelper::decrypt($encryptedPassword);
        } catch (Exception $e) {
            // If decryption fails, it might be plaintext (for backward compatibility)
            $password = $encryptedPassword;
            echo "Warning: Using potentially unencrypted password. Please re-save newsletter configuration.\n";
        }
        
        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Basic " . base64_encode("$username:$password")
            ]
        ]);
        
        $response = @file_get_contents("$url?$queryString", false, $context);
        
        if ($response === false) {
            echo "Error fetching data from API\n";
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Error parsing JSON response: " . json_last_error_msg() . "\n";
            return null;
        }
        
        $count = is_array($data) ? count($data) : 1;
        if ($count > 0) {
            echo "Successfully fetched $count submission(s)\n";
        } else {
            echo "No new submissions found\n";
        }
        
        return $data;
    }
    
    public function processSubmission($submission) {
        if (!is_array($submission)) {
            return false;
        }
        
        // Extract relevant fields
        $submissionId = $submission['form']['submissionId'] ?? $submission['_id'] ?? 'unknown';
        
        $email = null;
        $options = null;
        
        if (!$email && isset($submission['email'])) {
            $email = $submission['email'];
        }
        if (!$options && isset($submission['options'])) {
            $options = $submission['options'];
        }
        
        
        if (!$email) {
            echo "  Skipping submission $submissionId: No email found\n";
            return false;
        }
        
        // Normalize email
        $email = strtolower(trim($email));
        
        // Determine action
        $action = null;
        if ($options) {
            $optionsLower = strtolower($options);
            // Check for unsubscribe first since it contains "subscribe"
            if (strpos($optionsLower, 'unsubscribe') !== false) {
                $action = 'unsubscribe';
            } elseif (strpos($optionsLower, 'subscribe') !== false) {
                $action = 'subscribe';
            }
        }
        
        if (!$action) {
            echo "  Skipping submission $submissionId: No clear action found (options: $options)\n";
            return false;
        }
        
        // Check if this submission has already been processed for this newsletter
        $checkStmt = $this->db->prepare("
            SELECT id FROM subscription_history 
            WHERE submission_id = ? AND newsletter_id = ?
            LIMIT 1
        ");
        $checkStmt->execute([$submissionId, $this->newsletterId]);
        
        if ($checkStmt->fetch()) {
            echo "  Skipping submission $submissionId: Already processed\n";
            return false;
        }
        
        echo "  Processing: $action for $email (submission: $submissionId)\n";
        
        $now = date('Y-m-d H:i:s');
        
        $maxRetries = 5;
        $retryDelay = 200000; // 200ms in microseconds (increased from 100ms)
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // On retry attempts after the first, reconnect to database
                if ($attempt > 1) {
                    echo "  Reconnecting to database (attempt $attempt)...\n";
                    $this->reconnectDatabase();
                }
                
                // Begin transaction for atomicity
                $this->db->beginTransaction();
                
                // Record in history
                $stmt = $this->db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$email, $action, $now, $submissionId, json_encode($submission), $this->newsletterId]);
                
                // Update subscription status
                if ($action == 'subscribe') {
                    // Check if email exists for this newsletter
                    $checkStmt = $this->db->prepare("SELECT email FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                    $checkStmt->execute([$email, $this->newsletterId]);
                    
                    if ($checkStmt->fetch()) {
                        // Update existing
                        $updateStmt = $this->db->prepare("
                            UPDATE subscriptions 
                            SET status = 'active', updated_at = ?
                            WHERE email = ? AND newsletter_id = ?
                        ");
                        $updateStmt->execute([$now, $email, $this->newsletterId]);
                    } else {
                        // Insert new
                        $insertStmt = $this->db->prepare("
                            INSERT INTO subscriptions (email, status, created_at, updated_at, newsletter_id)
                            VALUES (?, 'active', ?, ?, ?)
                        ");
                        $insertStmt->execute([$email, $now, $now, $this->newsletterId]);
                    }
                    
                } elseif ($action == 'unsubscribe') {
                    // Check if email exists for this newsletter
                    $checkStmt = $this->db->prepare("SELECT email FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                    $checkStmt->execute([$email, $this->newsletterId]);
                    
                    if ($checkStmt->fetch()) {
                        // Update existing
                        $updateStmt = $this->db->prepare("
                            UPDATE subscriptions 
                            SET status = 'unsubscribed', updated_at = ?
                            WHERE email = ? AND newsletter_id = ?
                        ");
                        $updateStmt->execute([$now, $email, $this->newsletterId]);
                    } else {
                        // Insert new as unsubscribed
                        $insertStmt = $this->db->prepare("
                            INSERT INTO subscriptions (email, status, created_at, updated_at, newsletter_id)
                            VALUES (?, 'unsubscribed', ?, ?, ?)
                        ");
                        $insertStmt->execute([$email, $now, $now, $this->newsletterId]);
                    }
                }
                
                // Commit transaction
                $this->db->commit();
                return true;
                
            } catch (PDOException $e) {
                // Rollback transaction on any error
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                
                if ($e->getCode() == 'HY000' && strpos($e->getMessage(), 'database is locked') !== false) {
                    if ($attempt < $maxRetries) {
                        echo "  Database locked (attempt $attempt/$maxRetries), retrying in " . ($retryDelay/1000) . "ms...\n";
                        
                        // On the 3rd attempt, try force unlock
                        if ($attempt == 3) {
                            echo "  Attempting force unlock...\n";
                            $this->forceUnlockDatabase();
                        }
                        
                        usleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                    } else {
                        echo "  Database remained locked after $maxRetries attempts for $email\n";
                        return false;
                    }
                } else {
                    echo "  Database error: " . $e->getMessage() . "\n";
                    return false;
                }
            }
        }
        
        return false;
    }
    
    public function processAllSubmissions() {
        // Get last sync time
        $lastSync = $this->getLastSyncTime();
        // $lastSync = '2020-01-01T00:00:00Z'; // For testing, always fetch all
        
        // Fetch submissions (new ones only if we have a last sync time)
        $submissions = $this->fetchSubmissions($lastSync);
        
        if (!$submissions) {
            echo "No submissions to process\n";
            // Still update sync time to show sync was attempted
            $this->updateLastSyncTime(0);
            echo "Updated last sync timestamp\n";
            return;
        }
        
        if (!is_array($submissions)) {
            $submissions = [$submissions];
        }
        
        // Sort submissions by creation date to process in chronological order
        usort($submissions, function($a, $b) {
            $timeA = $a['form']['createdAt'] ?? $a['form']['submittedAt'] ?? '1970-01-01T00:00:00Z';
            $timeB = $b['form']['createdAt'] ?? $b['form']['submittedAt'] ?? '1970-01-01T00:00:00Z';
            return strcmp($timeA, $timeB);
        });
        
        echo "Processing submissions in chronological order...\n";
        
        $processed = 0;
        foreach ($submissions as $submission) {
            if ($this->processSubmission($submission)) {
                $processed++;
            }
        }
        
        echo "\nProcessed $processed out of " . count($submissions) . " submissions\n";
        
        // Always update last sync time after any sync attempt (even if no new submissions)
        $this->updateLastSyncTime($processed);
        echo "Updated last sync timestamp\n";
    }
    
    public function getActiveSubscriptions() {
        $stmt = $this->db->prepare("
            SELECT email, created_at, updated_at 
            FROM subscriptions 
            WHERE status = 'active' AND newsletter_id = ?
            ORDER BY email
        ");
        $stmt->execute([$this->newsletterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllSubscriptions() {
        $stmt = $this->db->prepare("
            SELECT email, status, created_at, updated_at 
            FROM subscriptions 
            WHERE newsletter_id = ?
            ORDER BY status, email
        ");
        $stmt->execute([$this->newsletterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function displayStatistics() {
        global $isWeb;
        
        if ($isWeb) {
            echo "\n=== SUBSCRIPTION STATISTICS ===\n";
        } else {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "SUBSCRIPTION STATISTICS\n";
            echo str_repeat("=", 80) . "\n";
        }
        
        // Count by status for this newsletter
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count 
            FROM subscriptions 
            WHERE newsletter_id = ?
            GROUP BY status
        ");
        $stmt->execute([$this->newsletterId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo ucfirst($row['status']) . ": " . $row['count'] . "\n";
        }
        
        // Show recent activity
        if ($isWeb) {
            echo "\n=== RECENT ACTIVITY (Last 10) ===\n";
        } else {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "RECENT ACTIVITY (Last 10)\n";
            echo str_repeat("=", 80) . "\n";
        }
        
        $stmt = $this->db->prepare("
            SELECT email, action, timestamp 
            FROM subscription_history 
            WHERE newsletter_id = ?
            ORDER BY timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute([$this->newsletterId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['timestamp'] . ": " . $row['action'] . " - " . $row['email'] . "\n";
        }
    }
    
}

// Main execution
function main($newsletterId = null) {
    global $isWeb, $argv;
    
    // Get newsletter ID from parameter, query string, or command line args
    if ($newsletterId === null) {
        if ($isWeb && isset($_GET['newsletter_id'])) {
            $newsletterId = (int)$_GET['newsletter_id'];
        } elseif (!$isWeb && isset($argv[1])) {
            $newsletterId = (int)$argv[1];
        } else {
            $newsletterId = 1; // Default to first newsletter
        }
    }
    
    
    if ($isWeb) {
        echo "BC Gov Digital Forms - Subscription Manager\n";
        echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    } else {
        echo "BC Gov Digital Forms - Subscription Manager (PHP)\n";
        echo str_repeat("=", 80) . "\n";
        echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    }
    
    // Initialize manager with newsletter ID
    $manager = new SubscriptionManager($newsletterId);
    
    // Process all submissions
    $manager->processAllSubmissions();
    
    // Display statistics
    $manager->displayStatistics();
    
    // Show active subscriptions (only in CLI mode)
    if (!$isWeb) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "ACTIVE SUBSCRIPTIONS\n";
        echo str_repeat("=", 80) . "\n";
        
        $active = $manager->getActiveSubscriptions();
        if ($active) {
            foreach ($active as $subscriber) {
                echo $subscriber['email'] . " (created: " . $subscriber['created_at'] . 
                     ", updated: " . $subscriber['updated_at'] . ")\n";
            }
        } else {
            echo "No active subscriptions\n";
        }
    }
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    
    // Explicitly close database connection
    $manager->closeDatabase();
}

// Run main function regardless of execution context
main();
?>