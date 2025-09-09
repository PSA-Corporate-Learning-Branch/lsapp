<?php
/**
 * Encryption Helper for securing sensitive data
 * Uses OpenSSL with AES-256-GCM for authenticated encryption
 */

class EncryptionHelper {
    private static $cipher = 'aes-256-gcm';
    private static $keyDerivationIterations = 10000;
    
    /**
     * Get or generate encryption key
     * Store this in environment variable or secure configuration file
     */
    private static function getEncryptionKey() {
        // Try to get from environment variable first
        $key = getenv('APP_ENCRYPTION_KEY');
        
        if (!$key) {
            // Fall back to a file-based key (should be outside web root)
            $keyFile = dirname(__DIR__) . '/.encryption_key';
            
            if (file_exists($keyFile)) {
                $key = trim(file_get_contents($keyFile));
            } else {
                // Generate a new key if none exists
                $key = base64_encode(random_bytes(32));
                
                // Try to save it (may fail due to permissions)
                @file_put_contents($keyFile, $key);
                @chmod($keyFile, 0600);
                
                error_log("WARNING: New encryption key generated. Please secure this key: " . $key);
            }
        }
        
        return base64_decode($key);
    }
    
    /**
     * Encrypt data using AES-256-GCM
     * 
     * @param string $plaintext The data to encrypt
     * @return string Base64 encoded encrypted data with IV and tag
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) {
            return '';
        }
        
        try {
            $key = self::getEncryptionKey();
            
            // Generate random IV
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            $iv = random_bytes($ivLength);
            
            // Encrypt the data
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                self::$cipher,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($ciphertext === false) {
                throw new Exception('Encryption failed');
            }
            
            // Combine IV, tag, and ciphertext for storage
            $combined = base64_encode($iv . $tag . $ciphertext);
            
            return $combined;
            
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            throw new Exception('Failed to encrypt data');
        }
    }
    
    /**
     * Decrypt data encrypted with encrypt()
     * 
     * @param string $encryptedData Base64 encoded encrypted data
     * @return string The decrypted plaintext
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            $key = self::getEncryptionKey();
            
            // Decode the combined data
            $combined = base64_decode($encryptedData);
            
            if ($combined === false) {
                throw new Exception('Invalid encrypted data format');
            }
            
            // Extract IV, tag, and ciphertext
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            $tagLength = 16; // GCM tag is always 16 bytes
            
            if (strlen($combined) < $ivLength + $tagLength) {
                throw new Exception('Invalid encrypted data');
            }
            
            $iv = substr($combined, 0, $ivLength);
            $tag = substr($combined, $ivLength, $tagLength);
            $ciphertext = substr($combined, $ivLength + $tagLength);
            
            // Decrypt the data
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::$cipher,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($plaintext === false) {
                throw new Exception('Decryption failed - data may be corrupted');
            }
            
            return $plaintext;
            
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            
            // For backward compatibility, check if this might be plaintext
            // This allows gradual migration from plaintext to encrypted
            if (self::mightBePlaintext($encryptedData)) {
                error_log('WARNING: Detected possible plaintext password. Please re-save to encrypt.');
                return $encryptedData;
            }
            
            throw new Exception('Failed to decrypt data');
        }
    }
    
    /**
     * Check if data might be plaintext (for migration purposes)
     */
    private static function mightBePlaintext($data) {
        // Check if it's valid base64
        if (base64_encode(base64_decode($data, true)) !== $data) {
            return true; // Not base64, probably plaintext
        }
        
        // Check if decoded length makes sense for encrypted data
        $decoded = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16;
        
        if (strlen($decoded) < $ivLength + $tagLength + 1) {
            return true; // Too short to be encrypted data
        }
        
        return false;
    }
    
    /**
     * Generate a secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash a password (for user passwords, not API keys)
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify a hashed password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Test function to verify encryption is working
function testEncryption() {
    try {
        $testData = "test_password_123";
        $encrypted = EncryptionHelper::encrypt($testData);
        $decrypted = EncryptionHelper::decrypt($encrypted);
        
        if ($decrypted === $testData) {
            return true;
        } else {
            error_log("Encryption test failed: decrypted data doesn't match original");
            return false;
        }
    } catch (Exception $e) {
        error_log("Encryption test failed: " . $e->getMessage());
        return false;
    }
}