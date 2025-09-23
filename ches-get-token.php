<?php
opcache_reset();
/**
 * Run this script on a machine that CAN reach dev.loginproxy.gov.bc.ca
 * Then copy the token to your production server
 */

// Set your credentials here or via environment
$clientId = 'CDACC9DF-F2BE5BC254A';
$clientSecret = '337e04f4-e6dd-4507-939d-b9cad486882f';

if (empty($clientId) || empty($clientSecret)) {
    die("Error: CHES_CLIENT_ID and CHES_CLIENT_SECRET required\n");
}

$tokenUrl = 'https://dev.loginproxy.gov.bc.ca/auth/realms/comsvcauth/protocol/openid-connect/token';

$postData = [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    die("cURL Error: $error\n");
}

if ($httpCode !== 200) {
    die("HTTP Error $httpCode: $response\n");
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    die("No access token in response\n");
}

// Display token information
echo "Token obtained successfully!\n";
echo "=====================================\n\n";

echo "ACCESS TOKEN:\n";
echo $tokenData['access_token'] . "\n\n";

echo "Token Type: " . ($tokenData['token_type'] ?? 'Bearer') . "\n";
echo "Expires In: " . ($tokenData['expires_in'] ?? 3600) . " seconds\n";
echo "Obtained At: " . date('Y-m-d H:i:s') . "\n";
echo "Expires At: " . date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600)) . "\n\n";

echo "To use this token on your production server:\n";
echo "---------------------------------------------\n";
echo "1. Copy the access token above\n";
echo "2. On your production server, run:\n";
echo "   php ches-manual-token.php set \"<TOKEN>\" " . ($tokenData['expires_in'] ?? 3600) . "\n";
echo "\nOr via web (if configured):\n";
echo "   /lsapp/ches-manual-token.php?auth_key=your-secret-key&action=set&token=<TOKEN>&expires=" . ($tokenData['expires_in'] ?? 3600) . "\n";
?>