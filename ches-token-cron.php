#!/usr/bin/env php
<?php
/**
 * Cron job to refresh CHES token
 * Run this every 45 minutes on a machine that can reach the auth server
 * It will update a shared location that your production server can read
 */

$clientId = $_SERVER['CHES_CLIENT_ID'] ?? '';
$clientSecret = $_SERVER['CHES_CLIENT_SECRET'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    error_log("CHES Cron: Missing credentials");
    exit(1);
}

// Get fresh token
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
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("CHES Cron: Failed to get token - HTTP $httpCode");
    exit(1);
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    error_log("CHES Cron: No access token in response");
    exit(1);
}

// Save to multiple locations if needed
$locations = [
    // Local cache
    __DIR__ . '/inc/cache/ches_token.json',
    // Network share (Windows)
    // '\\\\fileserver\\shared\\ches_token.json',
    // Database (if you prefer)
    // Would need DB connection here
];

$cache = [
    'token' => $tokenData['access_token'],
    'expiry' => time() + $tokenData['expires_in'],
    'cached_at' => time(),
    'cached_date' => date('Y-m-d H:i:s'),
    'expires_in' => $tokenData['expires_in']
];

foreach ($locations as $location) {
    $dir = dirname($location);
    if (!file_exists($dir)) {
        mkdir($dir, 0750, true);
    }
    
    if (file_put_contents($location, json_encode($cache, JSON_PRETTY_PRINT))) {
        echo "Token saved to: $location\n";
    } else {
        error_log("CHES Cron: Failed to save to $location");
    }
}

echo "Token refreshed successfully at " . date('Y-m-d H:i:s') . "\n";
echo "Expires at " . date('Y-m-d H:i:s', $cache['expiry']) . "\n";

// Optionally, push to production via SSH/SCP
// exec('scp /path/to/token.json user@prodserver:/path/to/cache/');
?>