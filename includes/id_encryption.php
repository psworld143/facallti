<?php
/**
 * ID Encryption Utility
 * 
 * This utility provides secure encryption and decryption of ID parameters
 * used in URL redirects to prevent direct manipulation of database IDs.
 */

class IDEncryption {
    private static $key = 'SEAIT_SECURE_KEY_2024'; // Change this to a secure key
    private static $cipher = 'AES-256-CBC';
    private static $options = OPENSSL_RAW_DATA;
    
    /**
     * Encrypt an ID for use in URLs
     * 
     * @param int $id The ID to encrypt
     * @return string The encrypted ID (URL-safe)
     */
    public static function encrypt($id) {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException('ID must be numeric');
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt(
            (string)$id,
            self::$cipher,
            self::$key,
            self::$options,
            $iv
        );
        
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }
        
        // Combine IV and encrypted data, then make URL-safe
        $combined = $iv . $encrypted;
        return strtr(base64_encode($combined), '+/', '-_');
    }
    
    /**
     * Decrypt an ID from URL parameter
     * 
     * @param string $encrypted_id The encrypted ID from URL
     * @return int The decrypted ID
     */
    public static function decrypt($encrypted_id) {
        if (empty($encrypted_id)) {
            throw new InvalidArgumentException('Encrypted ID cannot be empty');
        }
        
        // Restore URL-safe characters
        $combined = base64_decode(strtr($encrypted_id, '-_', '+/'));
        
        if ($combined === false) {
            throw new InvalidArgumentException('Invalid encrypted ID format');
        }
        
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($combined, 0, $iv_length);
        $encrypted = substr($combined, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            self::$cipher,
            self::$key,
            self::$options,
            $iv
        );
        
        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }
        
        return (int)$decrypted;
    }
    
    /**
     * Safely decrypt an ID with error handling
     * 
     * @param string $encrypted_id The encrypted ID from URL
     * @param int $default Default value if decryption fails
     * @return int The decrypted ID or default value
     */
    public static function safeDecrypt($encrypted_id, $default = 0) {
        try {
            return self::decrypt($encrypted_id);
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("ID decryption failed: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Check if a string is an encrypted ID
     * 
     * @param string $value The value to check
     * @return bool True if it appears to be an encrypted ID
     */
    public static function isEncrypted($value) {
        if (empty($value) || !is_string($value)) {
            return false;
        }
        
        // Check if it contains only valid characters for our encrypted format
        return preg_match('/^[A-Za-z0-9\-_]+$/', $value) && strlen($value) > 20;
    }
}

/**
 * Helper functions for backward compatibility and ease of use
 */

/**
 * Encrypt an ID for URL use
 * 
 * @param int $id The ID to encrypt
 * @return string The encrypted ID
 */
function encrypt_id($id) {
    return IDEncryption::encrypt($id);
}

/**
 * Decrypt an ID from URL
 * 
 * @param string $encrypted_id The encrypted ID
 * @return int The decrypted ID
 */
function decrypt_id($encrypted_id) {
    return IDEncryption::decrypt($encrypted_id);
}

/**
 * Safely decrypt an ID with fallback
 * 
 * @param string $encrypted_id The encrypted ID
 * @param int $default Default value if decryption fails
 * @return int The decrypted ID or default
 */
function safe_decrypt_id($encrypted_id, $default = 0) {
    return IDEncryption::safeDecrypt($encrypted_id, $default);
}

/**
 * Check if a value is an encrypted ID
 * 
 * @param string $value The value to check
 * @return bool True if it's an encrypted ID
 */
function is_encrypted_id($value) {
    return IDEncryption::isEncrypted($value);
}
?>
