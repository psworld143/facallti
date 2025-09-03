<?php
/**
 * POS Module Error Handler
 * Extends the main PMS error handler for POS-specific error handling
 */

// Include the main PMS error handler
require_once __DIR__ . '/../../includes/error_handler.php';

// POS-specific error handling functions
class POSErrorHandler {
    
    /**
     * Handle POS-specific errors
     */
    public static function handlePOSError($error, $context = '') {
        $error_message = "POS Error: " . $error;
        if ($context) {
            $error_message .= " (Context: $context)";
        }
        
        error_log($error_message);
        
        // For POS errors, we might want to show user-friendly messages
        // instead of redirecting to 505.php
        if (headers_sent()) {
            return false;
        }
        
        // Only redirect for critical errors
        if (strpos($error, 'Database') !== false || strpos($error, 'Fatal') !== false) {
            header("Location: /seait/505.php");
            exit();
        }
        
        return true;
    }
    
    /**
     * Log POS activity with error context
     */
    public static function logPOSActivity($user_id, $action, $description, $error_context = '') {
        try {
            global $pdo;
            $stmt = $pdo->prepare("INSERT INTO pos_activity_log (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            if ($error_context) {
                error_log("POS Activity logged with context: $error_context");
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log POS activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate POS input data
     */
    public static function validatePOSInput($data, $required_fields = []) {
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        if (!empty($errors)) {
            self::handlePOSError("Validation failed: " . implode(', ', $errors));
            return false;
        }
        
        return true;
    }
}

// Initialize POS error handler
$pos_error_handler = new POSErrorHandler();
?>
