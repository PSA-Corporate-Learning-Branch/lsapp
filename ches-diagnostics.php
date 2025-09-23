<?php
header('Content-Type: text/plain');

echo "CHES API Diagnostics\n";
echo "====================\n\n";

// 1. Check PHP and cURL info
echo "1. PHP Version: " . phpversion() . "\n";
echo "2. cURL Extension: " . (extension_loaded('curl') ? 'Loaded' : 'NOT LOADED') . "\n\n";

if (extension_loaded('curl')) {
    $curlVersion = curl_version();
    echo "   cURL Version: " . $curlVersion['version'] . "\n";
    echo "   SSL Version: " . $curlVersion['ssl_version'] . "\n";
    echo "   Protocols: " . implode(', ', $curlVersion['protocols']) . "\n\n";
}

// 2. Check environment variables
echo "3. Environment Variables:\n";
echo "   CHES_CLIENT_ID: " . (isset($_SERVER['CHES_CLIENT_ID']) ? 'SET (length: ' . strlen($_SERVER['CHES_CLIENT_ID']) . ')' : 'NOT SET') . "\n";
echo "   CHES_CLIENT_SECRET: " . (isset($_SERVER['CHES_CLIENT_SECRET']) ? 'SET (length: ' . strlen($_SERVER['CHES_CLIENT_SECRET']) . ')' : 'NOT SET') . "\n\n";

// 3. Test basic HTTPS connectivity
echo "4. Testing HTTPS Connectivity:\n\n";

// Test Google (should always work)
echo "   Testing https://www.google.com:\n";
testUrl('https://www.google.com', false);

// Test BC Gov login proxy
echo "\n   Testing BC Gov Auth (https://dev.loginproxy.gov.bc.ca):\n";
testUrl('https://dev.loginproxy.gov.bc.ca/auth/realms/comsvcauth/.well-known/openid-configuration', false);

// Test CHES API
echo "\n   Testing CHES API (https://ches-dev.api.gov.bc.ca):\n";
testUrl('https://ches-dev.api.gov.bc.ca/api/v1/health', false);

// 4. Test token acquisition with detailed error reporting
echo "\n5. Testing Token Acquisition (with detailed debugging):\n";
testTokenAcquisition();

function testUrl($url, $postData = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, !$postData); // HEAD request unless posting
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Capture verbose output
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    curl_close($ch);
    
    echo "     HTTP Code: $httpCode\n";
    echo "     cURL Error: " . ($error ? "$error (errno: $errno)" : "None") . "\n";
    echo "     Connect Time: " . $info['connect_time'] . " seconds\n";
    echo "     Total Time: " . $info['total_time'] . " seconds\n";
    
    if ($error && $errno) {
        echo "     Error Details:\n";
        switch($errno) {
            case 6:
                echo "       - Could not resolve host (DNS issue or network restriction)\n";
                break;
            case 7:
                echo "       - Failed to connect to host (firewall/proxy blocking)\n";
                break;
            case 28:
                echo "       - Operation timed out\n";
                break;
            case 35:
                echo "       - SSL/TLS handshake failed\n";
                break;
            case 60:
                echo "       - SSL certificate problem (CA bundle may be missing)\n";
                echo "       - Try downloading cacert.pem from https://curl.se/ca/cacert.pem\n";
                echo "       - Place it on your server and set curl.cainfo in php.ini\n";
                break;
            case 77:
                echo "       - Error setting certificate verify locations\n";
                echo "       - CA bundle path may be incorrect in php.ini\n";
                break;
        }
    }
    
    // Show first few lines of verbose output if there was an error
    if ($error && $verboseLog) {
        $lines = explode("\n", $verboseLog);
        echo "     Verbose Output (first 10 lines):\n";
        for ($i = 0; $i < min(10, count($lines)); $i++) {
            echo "       " . $lines[$i] . "\n";
        }
    }
}

function testTokenAcquisition() {
    if (!isset($_SERVER['CHES_CLIENT_ID']) || !isset($_SERVER['CHES_CLIENT_SECRET'])) {
        echo "   SKIPPED: Environment variables not set\n";
        return;
    }
    
    $tokenUrl = 'https://dev.loginproxy.gov.bc.ca/auth/realms/comsvcauth/protocol/openid-connect/token';
    
    $postData = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $_SERVER['CHES_CLIENT_ID'],
        'client_secret' => $_SERVER['CHES_CLIENT_SECRET']
    ]);
    
    echo "   Token URL: $tokenUrl\n";
    echo "   Testing with credentials...\n\n";
    
    $ch = curl_init($tokenUrl);
    
    // Try different SSL configurations
    $sslConfigs = [
        ['name' => 'Default (verify peer)', 'verify_peer' => true, 'verify_host' => 2],
        ['name' => 'No peer verification', 'verify_peer' => false, 'verify_host' => 0],
    ];
    
    foreach ($sslConfigs as $config) {
        echo "   Trying: " . $config['name'] . "\n";
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $config['verify_peer']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $config['verify_host']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        echo "     HTTP Code: $httpCode\n";
        echo "     cURL Error: " . ($error ? "$error (errno: $errno)" : "None") . "\n";
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                echo "     SUCCESS: Token obtained!\n";
                echo "     Token Type: " . ($data['token_type'] ?? 'unknown') . "\n";
                echo "     Expires In: " . ($data['expires_in'] ?? 'unknown') . " seconds\n";
            } else {
                echo "     ERROR: Got 200 response but no access_token\n";
            }
        } elseif ($httpCode > 0) {
            echo "     Response: " . substr($response, 0, 200) . "\n";
        }
        
        echo "\n";
    }
    
    curl_close($ch);
}

// 6. Check php.ini settings
echo "\n6. Relevant PHP Settings:\n";
echo "   allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "\n";
echo "   openssl.cafile: " . (ini_get('openssl.cafile') ?: 'not set') . "\n";
echo "   curl.cainfo: " . (ini_get('curl.cainfo') ?: 'not set') . "\n";

// 7. Check for proxy settings
echo "\n7. Proxy Settings:\n";
$proxyVars = ['HTTP_PROXY', 'HTTPS_PROXY', 'http_proxy', 'https_proxy', 'NO_PROXY', 'no_proxy'];
foreach ($proxyVars as $var) {
    echo "   $var: " . (getenv($var) ?: 'not set') . "\n";
}

echo "\n\nDiagnostics complete.\n";
echo "Common solutions for Windows Server/IIS:\n";
echo "1. Download CA bundle: https://curl.se/ca/cacert.pem\n";
echo "2. Save to C:\\php\\extras\\ssl\\cacert.pem (or similar)\n";
echo "3. In php.ini, set: curl.cainfo = \"C:\\php\\extras\\ssl\\cacert.pem\"\n";
echo "4. Restart IIS after changes\n";
echo "5. If behind corporate proxy, may need to configure proxy settings\n";
?>