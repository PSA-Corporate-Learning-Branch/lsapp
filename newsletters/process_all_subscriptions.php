<?php
/**
 * Automated script to process subscriptions for ALL active newsletters
 * Designed to be run as a cron job
 * Iterates through all active newsletters and processes their subscription data
 */

// Set timezone to PST/PDT (America/Vancouver covers BC)
date_default_timezone_set('America/Vancouver');

// Include encryption helper for decrypting API passwords
require_once(dirname(__DIR__) . '/inc/encryption_helper.php');

// Force CLI mode
// if (php_sapi_name() !== 'cli') {
//     die("This script must be run from the command line\n");
// }

class AllNewslettersProcessor {
    private $db;
    private $dbPath;

    public function __construct($dbPath = '../data/subscriptions.db') {
        $this->dbPath = $dbPath;
        $this->initDatabase();
    }

    public function __destruct() {
        $this->closeDatabase();
    }

    public function closeDatabase() {
        if ($this->db) {
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

    private function initDatabase() {
        try {
            $dsn = "sqlite:{$this->dbPath}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 60,
                PDO::ATTR_PERSISTENT => false,
            ];

            $this->db = new PDO($dsn, '', '', $options);

            // WAL mode allows for better concurrency
            $this->db->exec("PRAGMA journal_mode=WAL;");
            $this->db->exec("PRAGMA synchronous=NORMAL;");
            $this->db->exec("PRAGMA busy_timeout=30000;");
            $this->db->exec("PRAGMA wal_autocheckpoint=1000;");
            $this->db->exec("PRAGMA locking_mode=NORMAL;");
            $this->db->exec("PRAGMA temp_store=MEMORY;");
            $this->db->exec("PRAGMA cache_size=10000;");

            // Ensure tables exist
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

    public function getActiveNewsletters() {
        $stmt = $this->db->prepare("SELECT * FROM newsletters WHERE is_active = 1 ORDER BY id");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getLastSyncTime($newsletterId) {
        $stmt = $this->db->prepare("
            SELECT last_sync_timestamp
            FROM last_sync
            WHERE sync_type = 'submissions' AND newsletter_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$newsletterId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['last_sync_timestamp'] : null;
    }

    private function updateLastSyncTime($newsletterId, $recordsProcessed = 0) {
        $timestamp = date('c'); // ISO 8601 format
        $now = date('Y-m-d H:i:s');

        $maxRetries = 5;
        $retryDelay = 200000; // 200ms in microseconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 1) {
                    echo "  Reconnecting to database for sync time update (attempt $attempt)...\n";
                    $this->reconnectDatabase();
                }

                $stmt = $this->db->prepare("
                    INSERT INTO last_sync (last_sync_timestamp, sync_type, records_processed, created_at, newsletter_id)
                    VALUES (?, 'submissions', ?, ?, ?)
                ");
                $stmt->execute([$timestamp, $recordsProcessed, $now, $newsletterId]);
                return;

            } catch (PDOException $e) {
                if ($e->getCode() == 'HY000' && strpos($e->getMessage(), 'database is locked') !== false) {
                    if ($attempt < $maxRetries) {
                        echo "  Database locked (attempt $attempt/$maxRetries), retrying...\n";
                        usleep($retryDelay);
                        $retryDelay *= 2;
                    } else {
                        echo "  Database remained locked after $maxRetries attempts\n";
                        return;
                    }
                } else {
                    throw $e;
                }
            }
        }
    }

    private function fetchSubmissions($newsletter, $sinceDate = null) {
        $url = $newsletter['api_url'] . '/' . $newsletter['form_id'] . '/export';

        $params = [
            'format' => 'json',
            'type' => 'submissions'
        ];

        if ($sinceDate) {
            $preference = json_encode([
                'updatedMinDate' => $sinceDate
            ]);
            $params['preference'] = $preference;
            echo "  Fetching submissions updated since: $sinceDate\n";
        } else {
            echo "  Fetching all submissions (initial sync)\n";
        }

        $queryString = http_build_query($params);

        $username = $newsletter['api_username'];
        $encryptedPassword = $newsletter['api_password'];

        // Decrypt the password
        try {
            $password = EncryptionHelper::decrypt($encryptedPassword);
        } catch (Exception $e) {
            $password = $encryptedPassword;
            echo "  Warning: Using potentially unencrypted password\n";
        }

        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Basic " . base64_encode("$username:$password")
            ]
        ]);

        $response = @file_get_contents("$url?$queryString", false, $context);

        if ($response === false) {
            echo "  Error fetching data from API\n";
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  Error parsing JSON response: " . json_last_error_msg() . "\n";
            return null;
        }

        $count = is_array($data) ? count($data) : 1;
        if ($count > 0) {
            echo "  Successfully fetched $count submission(s)\n";
        } else {
            echo "  No new submissions found\n";
        }

        return $data;
    }

    private function processSubmission($submission, $newsletterId) {
        if (!is_array($submission)) {
            return false;
        }

        $submissionId = $submission['form']['submissionId'] ?? $submission['_id'] ?? 'unknown';

        $email = null;
        $options = null;

        if (isset($submission['email'])) {
            $email = $submission['email'];
        }
        if (isset($submission['options'])) {
            $options = $submission['options'];
        }

        if (!$email) {
            echo "    Skipping submission $submissionId: No email found\n";
            return false;
        }

        $email = strtolower(trim($email));

        // Determine action
        $action = null;
        if ($options) {
            $optionsLower = strtolower($options);
            if (strpos($optionsLower, 'unsubscribe') !== false) {
                $action = 'unsubscribe';
            } elseif (strpos($optionsLower, 'subscribe') !== false) {
                $action = 'subscribe';
            }
        }

        if (!$action) {
            echo "    Skipping submission $submissionId: No clear action found\n";
            return false;
        }

        // Check if already processed
        $checkStmt = $this->db->prepare("
            SELECT id FROM subscription_history
            WHERE submission_id = ? AND newsletter_id = ?
            LIMIT 1
        ");
        $checkStmt->execute([$submissionId, $newsletterId]);

        if ($checkStmt->fetch()) {
            echo "    Skipping submission $submissionId: Already processed\n";
            return false;
        }

        echo "    Processing: $action for $email (submission: $submissionId)\n";

        $now = date('Y-m-d H:i:s');

        $maxRetries = 5;
        $retryDelay = 200000;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 1) {
                    echo "    Reconnecting to database (attempt $attempt)...\n";
                    $this->reconnectDatabase();
                }

                $this->db->beginTransaction();

                // Record in history
                $stmt = $this->db->prepare("
                    INSERT INTO subscription_history (email, action, timestamp, submission_id, raw_data, newsletter_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$email, $action, $now, $submissionId, json_encode($submission), $newsletterId]);

                // Update subscription status
                if ($action == 'subscribe') {
                    $checkStmt = $this->db->prepare("SELECT email FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                    $checkStmt->execute([$email, $newsletterId]);

                    if ($checkStmt->fetch()) {
                        $updateStmt = $this->db->prepare("
                            UPDATE subscriptions
                            SET status = 'active', updated_at = ?
                            WHERE email = ? AND newsletter_id = ?
                        ");
                        $updateStmt->execute([$now, $email, $newsletterId]);
                    } else {
                        $insertStmt = $this->db->prepare("
                            INSERT INTO subscriptions (email, status, created_at, updated_at, newsletter_id)
                            VALUES (?, 'active', ?, ?, ?)
                        ");
                        $insertStmt->execute([$email, $now, $now, $newsletterId]);
                    }

                } elseif ($action == 'unsubscribe') {
                    $checkStmt = $this->db->prepare("SELECT email FROM subscriptions WHERE email = ? AND newsletter_id = ?");
                    $checkStmt->execute([$email, $newsletterId]);

                    if ($checkStmt->fetch()) {
                        $updateStmt = $this->db->prepare("
                            UPDATE subscriptions
                            SET status = 'unsubscribed', updated_at = ?
                            WHERE email = ? AND newsletter_id = ?
                        ");
                        $updateStmt->execute([$now, $email, $newsletterId]);
                    } else {
                        $insertStmt = $this->db->prepare("
                            INSERT INTO subscriptions (email, status, created_at, updated_at, newsletter_id)
                            VALUES (?, 'unsubscribed', ?, ?, ?)
                        ");
                        $insertStmt->execute([$email, $now, $now, $newsletterId]);
                    }
                }

                $this->db->commit();
                return true;

            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                if ($e->getCode() == 'HY000' && strpos($e->getMessage(), 'database is locked') !== false) {
                    if ($attempt < $maxRetries) {
                        echo "    Database locked (attempt $attempt/$maxRetries), retrying...\n";

                        if ($attempt == 3) {
                            echo "    Attempting force unlock...\n";
                            $this->forceUnlockDatabase();
                        }

                        usleep($retryDelay);
                        $retryDelay *= 2;
                    } else {
                        echo "    Database remained locked after $maxRetries attempts\n";
                        return false;
                    }
                } else {
                    echo "    Database error: " . $e->getMessage() . "\n";
                    return false;
                }
            }
        }

        return false;
    }

    private function processNewsletterSubmissions($newsletter) {
        $newsletterId = $newsletter['id'];
        $lastSync = $this->getLastSyncTime($newsletterId);

        $submissions = $this->fetchSubmissions($newsletter, $lastSync);

        if (!$submissions) {
            echo "  No submissions to process\n";
            $this->updateLastSyncTime($newsletterId, 0);
            return 0;
        }

        if (!is_array($submissions)) {
            $submissions = [$submissions];
        }

        // Sort by creation date
        usort($submissions, function($a, $b) {
            $timeA = $a['form']['createdAt'] ?? $a['form']['submittedAt'] ?? '1970-01-01T00:00:00Z';
            $timeB = $b['form']['createdAt'] ?? $b['form']['submittedAt'] ?? '1970-01-01T00:00:00Z';
            return strcmp($timeA, $timeB);
        });

        echo "  Processing " . count($submissions) . " submissions in chronological order...\n";

        $processed = 0;
        foreach ($submissions as $submission) {
            if ($this->processSubmission($submission, $newsletterId)) {
                $processed++;
            }
        }

        echo "  Processed $processed out of " . count($submissions) . " submissions\n";

        $this->updateLastSyncTime($newsletterId, $processed);

        return $processed;
    }

    public function processAllNewsletters() {
        $newsletters = $this->getActiveNewsletters();

        if (empty($newsletters)) {
            echo "No active newsletters found\n";
            return;
        }

        echo "Found " . count($newsletters) . " active newsletter(s)\n\n";

        $totalProcessed = 0;

        foreach ($newsletters as $newsletter) {
            echo str_repeat("=", 80) . "\n";
            echo "Processing: {$newsletter['name']} (ID: {$newsletter['id']})\n";
            echo str_repeat("=", 80) . "\n";

            $processed = $this->processNewsletterSubmissions($newsletter);
            $totalProcessed += $processed;

            echo "\n";
        }

        echo str_repeat("=", 80) . "\n";
        echo "SUMMARY\n";
        echo str_repeat("=", 80) . "\n";
        echo "Total newsletters processed: " . count($newsletters) . "\n";
        echo "Total subscriptions processed: $totalProcessed\n";
    }
}

// Main execution
function main() {
    echo str_repeat("=", 80) . "\n";
    echo "Automated Newsletter Subscription Processor\n";
    echo str_repeat("=", 80) . "\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

    $processor = new AllNewslettersProcessor();
    $processor->processAllNewsletters();

    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";

    $processor->closeDatabase();
}

main();
?>
