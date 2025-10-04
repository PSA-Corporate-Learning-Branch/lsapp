<?php
/**
 * Email Open Tracking Pixel
 * Logs email opens to SQLite database and returns a 1x1 transparent pixel
 */

// Set headers for image response
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 1x1 transparent GIF (smallest possible)
$pixel = base64_decode('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c5HXKFv1dgxc3nXG5+OOyD/fdtdEN9N3mgwdeVjvXFx4PJn2aZHE4C2Wms1+pDXqp4U7bV+6vlm2f7fMxqvRcN+MfC79Dpt/j87A55hT1SvnR5+O152j+DY3i2u9vRtl0RoNhv2TBSwCnmtHGRsM5+oy70++pTO1C3U/v1yXB/3xqDrR6U31d1hUi0WrPZ7862J6HFNUB+3T6l9z3PHzHaXZBoGzILDTvd/5+fLWnwp3FlIkDQRmE1h+8PR68+x+tiOFJkCAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAG8nHcJAAAASUlEQVQCBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBAgQIECAAAECBP5PgUOOTpKldYrUTgAAAABJRU5ErkJggg==');

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

// Sanitize and validate email if provided
if ($email !== null) {
    // Check for dangerous characters BEFORE trimming
    if (strpos($email, "\0") !== false ||                 // No null bytes
        preg_match('/[\x00-\x1F\x7F]/', $email)) {       // No control chars
        $email = null;
    } else {
        $email = trim($email);

        // Comprehensive email validation
        if (strlen($email) > 254 ||                       // RFC max length
            $email === '' ||                              // Not empty
            !filter_var($email, FILTER_VALIDATE_EMAIL) || // Basic format
            substr_count($email, '@') !== 1) {            // Exactly one @
            $email = null;
        } else {
            // Additional checks for valid emails
            list($local, $domain) = explode('@', $email);
            if (strlen($local) > 64 || strlen($local) < 1 ||  // Local part length
                strlen($domain) > 255 || strlen($domain) < 1 || // Domain part length
                strpos($local, '..') !== false ||              // No consecutive dots
                strpos($domain, '..') !== false ||
                $local[0] === '.' ||                           // No leading dot
                $local[strlen($local) - 1] === '.' ||          // No trailing dot
                strpos($domain, '.') === false) {              // Domain must have dot
                $email = null;
            }
        }
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