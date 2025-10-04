<?php
/**
 * Email Open Tracking Pixel
 * Logs email opens to SQLite database and returns a 1x1 transparent pixel
 */

// Set headers for image response
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 1x1 transparent GIF (smallest possible)
$pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Get tracking parameters
$trackingId = $_GET['id'] ?? null;
$email = $_GET['e'] ?? null;
$newsletterId = $_GET['n'] ?? null;
$campaignId = $_GET['c'] ?? null;

// Validate tracking ID (required)
if (!$trackingId) {
    echo $pixel;
    exit;
}

// Validate tracking ID format (should be alphanumeric hash)
if (!preg_match('/^[a-f0-9]{32}$/', $trackingId)) {
    echo $pixel;
    exit;
}

// Validate newsletter ID if provided
if ($newsletterId !== null && (!is_numeric($newsletterId) || $newsletterId < 1)) {
    $newsletterId = null;
}

// Sanitize email if provided (basic validation)
if ($email !== null) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = null;
    }
}

// Get additional tracking data
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? null;
$timestamp = date('Y-m-d H:i:s');

// Rate limiting: max 10 requests per IP per second
$rateLimitDir = __DIR__ . '/data/rate_limit';
if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0755, true);
}

$rateLimitKey = md5($ipAddress);
$rateLimitFile = $rateLimitDir . '/' . $rateLimitKey;

if (file_exists($rateLimitFile)) {
    $lastRequest = (int)file_get_contents($rateLimitFile);
    if (time() - $lastRequest < 1) {
        // More than 1 request per second from same IP - likely bot/abuse
        http_response_code(429); // Too Many Requests
        echo $pixel;
        exit;
    }
}

file_put_contents($rateLimitFile, time());

// Clean up old rate limit files (older than 1 hour)
if (rand(1, 100) === 1) { // 1% chance on each request
    $files = glob($rateLimitDir . '/*');
    $oneHourAgo = time() - 3600;
    foreach ($files as $file) {
        if (filemtime($file) < $oneHourAgo) {
            @unlink($file);
        }
    }
}

// Initialize database connection
try {
    // Store database in a data directory
    $dbDir = __DIR__ . '/data';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $db = new PDO("sqlite:$dbDir/email_tracking.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_opens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tracking_id TEXT NOT NULL,
            email TEXT,
            newsletter_id INTEGER,
            campaign_id TEXT,
            opened_at TIMESTAMP NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            referer TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create index for faster lookups
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tracking_id ON email_opens(tracking_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_email ON email_opens(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_newsletter_id ON email_opens(newsletter_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_campaign_id ON email_opens(campaign_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_opened_at ON email_opens(opened_at)");
    
    // Check if this is a duplicate open (same tracking_id within 5 minutes)
    $checkStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM email_opens 
        WHERE tracking_id = :tracking_id 
        AND datetime(opened_at) > datetime('now', '-5 minutes')
    ");
    $checkStmt->execute([':tracking_id' => $trackingId]);
    $recentCount = $checkStmt->fetchColumn();
    
    // Only log if not a recent duplicate
    if ($recentCount == 0) {
        // Check database size - prevent DoS via storage exhaustion
        $dbSize = filesize("$dbDir/email_tracking.db");
        $maxDbSize = 100 * 1024 * 1024; // 100MB limit

        if ($dbSize < $maxDbSize) {
            // Sanitize user agent and referer to prevent injection
            $userAgent = substr($userAgent, 0, 500); // Limit length
            $referer = $referer ? substr($referer, 0, 500) : null;

            // Insert tracking record
            $stmt = $db->prepare("
                INSERT INTO email_opens (
                    tracking_id,
                    email,
                    newsletter_id,
                    campaign_id,
                    opened_at,
                    ip_address,
                    user_agent,
                    referer
                ) VALUES (
                    :tracking_id,
                    :email,
                    :newsletter_id,
                    :campaign_id,
                    :opened_at,
                    :ip_address,
                    :user_agent,
                    :referer
                )
            ");

            $stmt->execute([
                ':tracking_id' => $trackingId,
                ':email' => $email,
                ':newsletter_id' => $newsletterId,
                ':campaign_id' => $campaignId,
                ':opened_at' => $timestamp,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':referer' => $referer
            ]);

            // Periodic cleanup: delete records older than 90 days (1% chance)
            if (rand(1, 100) === 1) {
                $db->exec("DELETE FROM email_opens WHERE opened_at < datetime('now', '-90 days')");
            }
        } else {
            error_log("Email tracking database size limit reached: " . ($dbSize / 1024 / 1024) . "MB");
        }
    }
    
} catch (Exception $e) {
    // Silently fail - we don't want to break the image display
    // Optionally log to error file for debugging
    error_log("Email tracking error: " . $e->getMessage());
}

// Output the pixel
echo $pixel;
exit;