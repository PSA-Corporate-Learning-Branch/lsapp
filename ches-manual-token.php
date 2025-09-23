<?php
require('inc/ches-client-cached.php');

// Security: Only allow this script to run from command line or with proper auth
if (php_sapi_name() !== 'cli' && !isset($_GET['auth_key'])) {
    die('Access denied. This script requires authentication.');
}

// For web access, you could use a secret key
$authKey = 'your-secret-key-here'; // Change this!
if (php_sapi_name() !== 'cli' && $_GET['auth_key'] !== $authKey) {
    die('Invalid auth key');
}

$action = $_GET['action'] ?? $argv[1] ?? 'info';

$client = new CHESClient();

switch($action) {
    case 'set':
        // Get token from command line or GET parameter
        $token = $_GET['token'] ?? $argv[2] ?? null;
        $expires = $_GET['expires'] ?? $argv[3] ?? 3600;
        
        if (!$token) {
            echo "Error: Token required\n";
            echo "Usage: php ches-manual-token.php set <token> [expires_in_seconds]\n";
            echo "Or: ches-manual-token.php?auth_key=xxx&action=set&token=xxx&expires=3600\n";
            exit(1);
        }
        
        $client->setManualToken($token, (int)$expires);
        echo "Token set successfully. Expires in {$expires} seconds.\n";
        break;
        
    case 'info':
        $info = $client->getTokenInfo();
        echo "Token Cache Information:\n";
        echo "======================\n";
        echo "Has Token: " . ($info['has_token'] ? 'Yes' : 'No') . "\n";
        echo "Is Valid: " . ($info['is_valid'] ? 'Yes' : 'No') . "\n";
        echo "Expires At: " . ($info['expires_at'] ?? 'N/A') . "\n";
        echo "Cache File: " . $info['cache_file'] . "\n";
        echo "Cache Exists: " . ($info['cache_exists'] ? 'Yes' : 'No') . "\n";
        
        if ($info['cache_exists']) {
            $cache = json_decode(file_get_contents($info['cache_file']), true);
            echo "Cached Date: " . ($cache['cached_date'] ?? 'Unknown') . "\n";
        }
        break;
        
    case 'clear':
        $info = $client->getTokenInfo();
        if (file_exists($info['cache_file'])) {
            unlink($info['cache_file']);
            echo "Token cache cleared.\n";
        } else {
            echo "No cache file exists.\n";
        }
        break;
        
    case 'test':
        // Test sending an email
        $result = $client->sendEmail(
            'allan.haggett@gov.bc.ca',
            'CHES Test Email',
            '<p>This is a test email from the CHES client with cached token.</p>'
        );
        
        if ($result) {
            echo "Test email sent successfully! Message ID: " . (is_string($result) ? $result : 'sent') . "\n";
        } else {
            echo "Failed to send test email. Check error logs.\n";
        }
        break;
        
    default:
        echo "Usage:\n";
        echo "  php ches-manual-token.php info              - Show token cache info\n";
        echo "  php ches-manual-token.php set <token> [exp] - Set manual token\n";
        echo "  php ches-manual-token.php clear             - Clear cached token\n";
        echo "  php ches-manual-token.php test              - Send test email\n";
}

echo "\n";
?>