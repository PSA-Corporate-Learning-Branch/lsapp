<?php
/**
 * CHES (Common Hosted Email Service) API Client for PHP
 * BC Government email service API implementation
 * 
 * This version uses environment variables for configuration
 */

class CHESClient {
    private $clientId;
    private $clientSecret;
    private $tokenEndpoint;
    private $apiBase = "https://ches-dev.api.gov.bc.ca/api/v1";
    private $accessToken = null;
    private $tokenExpires = null;
    
    public function __construct($clientId = null, $clientSecret = null, $tokenEndpoint = null) {
        // Use provided values or fall back to environment variables
        $this->clientId = $clientId ?: $_SERVER['CHES_CLIENT_ID'];
        $this->clientSecret = $clientSecret ?: $_SERVER['CHES_CLIENT_SECRET'];
        $this->tokenEndpoint = $tokenEndpoint ?: $_SERVER['CHES_TOKEN_ENDPOINT'] ?? 'https://dev.loginproxy.gov.bc.ca/auth/realms/comsvcauth/protocol/openid-connect/token';
        
        // Validate required configuration
        if (!$this->clientId || !$this->clientSecret || !$this->tokenEndpoint) {
            throw new Exception("CHES configuration missing. Please set CHES_CLIENT_ID, CHES_CLIENT_SECRET, and CHES_TOKEN_ENDPOINT environment variables.");
        }
    }
    
    /**
     * Get or refresh OAuth2 access token
     */
    public function getAccessToken() {
        // Check if we have a valid token
        if ($this->accessToken && $this->tokenExpires && time() < $this->tokenExpires) {
            return $this->accessToken;
        }
        
        error_log("Requesting new access token from CHES");
        
        // Request new token using client credentials
        $data = [
            'grant_type' => 'client_credentials'
        ];
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->tokenEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL error: $error");
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to get access token. HTTP $httpCode: $response");
        }
        
        $tokenData = json_decode($response, true);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception("Invalid token response: $response");
        }
        
        $this->accessToken = $tokenData['access_token'];
        
        // Calculate expiration (subtract 60 seconds for safety)
        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $this->tokenExpires = time() + $expiresIn - 60;
        
        error_log("Successfully obtained access token");
        return $this->accessToken;
    }
    
    /**
     * Send an email using CHES API
     * 
     * @param array $to List of recipient email addresses
     * @param string $subject Email subject
     * @param string $bodyText Plain text body
     * @param string $bodyHtml Optional HTML body
     * @param string $fromEmail Sender email address
     * @param array $cc List of CC recipients
     * @param array $bcc List of BCC recipients
     * @param string $priority Email priority (low, normal, high)
     * @param int $delayTs Unix timestamp to delay sending
     * @return array API response
     */
    public function sendEmail($to, $subject, $bodyText, $bodyHtml = null, $fromEmail = "LearningHUB.Notification@gov.bc.ca", $cc = null, $bcc = null, $priority = "normal", $delayTs = null) {
        // Get access token
        $accessToken = $this->getAccessToken();
        
        // Build request payload
        $payload = [
            "bodyType" => $bodyHtml ? "html" : "text",
            "body" => $bodyHtml ?: $bodyText,
            "from" => $fromEmail,
            "subject" => $subject,
            "to" => $to,
            "priority" => $priority
        ];
        
        if ($cc) {
            $payload["cc"] = $cc;
        }
        if ($bcc) {
            $payload["bcc"] = $bcc;
        }
        if ($delayTs) {
            $payload["delayTS"] = $delayTs;
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        error_log("Sending email to " . implode(', ', $to) . " with subject: $subject");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiBase . "/email");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL error: $error");
        }
        
        curl_close($ch);
        
        if ($httpCode !== 201 && $httpCode !== 200) {
            throw new Exception("Failed to send email. HTTP $httpCode: $response");
        }
        
        $result = json_decode($response, true);
        $txId = $result['txId'] ?? 'N/A';
        error_log("Email sent successfully. Transaction ID: $txId");
        
        return $result;
    }
    
    /**
     * Get the status of a sent email
     */
    public function getStatus($transactionId) {
        $accessToken = $this->getAccessToken();
        
        $headers = [
            'Authorization: Bearer ' . $accessToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiBase . "/status/$transactionId");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL error: $error");
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to get status. HTTP $httpCode: $response");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Check if CHES API is healthy
     */
    public function healthCheck() {
        try {
            $accessToken = $this->getAccessToken();
            
            $headers = [
                'Authorization: Bearer ' . $accessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiBase . "/health");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            error_log("Health check failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Load CHES credentials from environment or file fallback
 * This function provides backward compatibility with file-based credentials
 */
function loadCHESCredentials($credsFile = null) {
    // First try environment variables
    $creds = [
        'client_id' => $_ENV['CHES_CLIENT_ID'] ?? getenv('CHES_CLIENT_ID'),
        'client_secret' => $_ENV['CHES_CLIENT_SECRET'] ?? getenv('CHES_CLIENT_SECRET'),
        'token_endpoint' => $_ENV['CHES_TOKEN_ENDPOINT'] ?? getenv('CHES_TOKEN_ENDPOINT')
    ];
    
    // If environment variables are set, return them
    if ($creds['client_id'] && $creds['client_secret'] && $creds['token_endpoint']) {
        return $creds;
    }
    
    // Fall back to file-based credentials for backward compatibility
    if ($credsFile && file_exists($credsFile)) {
        $lines = file($credsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(str_replace(' ', '_', trim($key)));
                $value = trim($value);
                $creds[$key] = $value;
            }
        }
    }
    
    return $creds;
}
?>