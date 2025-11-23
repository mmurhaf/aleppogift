<?php
/**
 * Security Helper Functions
 * Comprehensive security utilities for AleppoGift platform
 * 
 * @author AleppoGift Development Team
 * @version 1.0
 * @date August 12, 2025
 */

/**
 * Security class with static methods for common security operations
 */
class Security {
    
    /**
     * Generate secure CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = time() + (int)env('CSRF_TOKEN_EXPIRY', 3600);
        
        $_SESSION['csrf_tokens'][$token] = $expires;
        
        // Clean up expired tokens
        self::cleanExpiredCSRFTokens();
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool Validation result
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        $expires = $_SESSION['csrf_tokens'][$token];
        
        if (time() > $expires) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Clean expired CSRF tokens
     */
    private static function cleanExpiredCSRFTokens() {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $expires) {
            if ($now > $expires) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data Data to sanitize
     * @param string $type Type of sanitization
     * @return mixed Sanitized data
     */
    public static function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'string':
            default:
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     * 
     * @param mixed $data Data to validate
     * @param string $type Type of validation
     * @param array $options Additional validation options
     * @return bool Validation result
     */
    public static function validate($data, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL) !== false;
                
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $flags = 0;
                $filter_options = [];
                
                if ($min !== null) {
                    $filter_options['min_range'] = $min;
                }
                if ($max !== null) {
                    $filter_options['max_range'] = $max;
                }
                
                if (!empty($filter_options)) {
                    return filter_var($data, FILTER_VALIDATE_INT, [
                        'options' => $filter_options
                    ]) !== false;
                }
                
                return filter_var($data, FILTER_VALIDATE_INT) !== false;
                
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
                
            case 'phone':
                return preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data);
                
            case 'required':
                return !empty(trim($data));
                
            case 'min_length':
                return strlen($data) >= ($options['length'] ?? 1);
                
            case 'max_length':
                return strlen($data) <= ($options['length'] ?? 255);
                
            case 'regex':
                return isset($options['pattern']) && preg_match($options['pattern'], $data);
                
            default:
                return true;
        }
    }
    
    /**
     * Hash password securely
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored hash
     * @return bool Verification result
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     * 
     * @param int $length String length
     * @return string Random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Encrypt sensitive data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public static function encrypt($data) {
        $key = env('ENCRYPTION_KEY');
        if (!$key) {
            throw new Exception('Encryption key not configured');
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     * 
     * @param string $encryptedData Encrypted data
     * @return string Decrypted data
     */
    public static function decrypt($encryptedData) {
        $key = env('ENCRYPTION_KEY');
        if (!$key) {
            throw new Exception('Encryption key not configured');
        }
        
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Check for rate limiting
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    public static function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 3600) {
        if (empty($identifier)) {
            $identifier = 'unknown';
        }
        
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        $now = time();
        $attempts = &$_SESSION[$key];
        
        // Clean old attempts
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        $attempts[] = $now;
        
        return true;
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event description
     * @param string $level Log level (info, warning, error)
     * @param array $context Additional context
     */
    public static function logSecurityEvent($event, $level = 'info', $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'level' => $level,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'context' => $context
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log(json_encode($logEntry) . "\n", 3, $logFile);
    }
    
    /**
     * Generate secure session ID
     * 
     * @return string Session ID
     */
    public static function generateSecureSessionId() {
        return hash('sha256', random_bytes(32) . time() . $_SERVER['REMOTE_ADDR']);
    }
    
    /**
     * Validate session security
     * 
     * @return bool Session is valid
     */
    public static function validateSession() {
        if (!isset($_SESSION['created_at'])) {
            return false;
        }
        
        $sessionTimeout = env_int('SESSION_TIMEOUT', 7200);
        if (time() - $_SESSION['created_at'] > $sessionTimeout) {
            return false;
        }
        
        // Check for session hijacking
        if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
            return false;
        }
        
        return true;
    }
}

/**
 * Helper function to generate CSRF token
 */
function csrf_token() {
    return Security::generateCSRFToken();
}

/**
 * Helper function to validate CSRF token
 */
function validate_csrf($token) {
    return Security::validateCSRFToken($token);
}

/**
 * Helper function to sanitize input
 */
function sanitize($data, $type = 'string') {
    return Security::sanitize($data, $type);
}

/**
 * Helper function to validate input
 */
function validate_input($data, $type, $options = []) {
    return Security::validate($data, $type, $options);
}
