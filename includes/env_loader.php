<?php
/**
 * Environment Variable Loader
 * Securely loads environment variables from .env file
 * 
 * @author AleppoGift Development Team
 * @version 1.0
 * @date August 12, 2025
 */

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * Load environment variables from .env file
     * 
     * @param string $path Path to .env file
     * @return bool Success status
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return true;
        }
        
        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }
        
        if (!file_exists($path)) {
            error_log("Environment file not found: " . $path);
            return false;
        }
        
        try {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^"(.*)"$/', $value, $matches)) {
                        $value = $matches[1];
                    } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                        $value = $matches[1];
                    }
                    
                    // Store in both $_ENV and our internal array
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                    self::$variables[$key] = $value;
                }
            }
            
            self::$loaded = true;
            return true;
            
        } catch (Exception $e) {
            error_log("Error loading environment file: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get environment variable with default fallback
     * 
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed Variable value or default
     */
    public static function get($key, $default = null) {
        // Try $_ENV first
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Try getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Try our internal array
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }
        
        return $default;
    }
    
    /**
     * Check if environment variable exists
     * 
     * @param string $key Variable name
     * @return bool
     */
    public static function has($key) {
        return isset($_ENV[$key]) || 
               getenv($key) !== false || 
               isset(self::$variables[$key]);
    }
    
    /**
     * Get boolean value from environment
     * 
     * @param string $key Variable name
     * @param bool $default Default value
     * @return bool
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        $value = strtolower($value);
        return in_array($value, ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Get integer value from environment
     * 
     * @param string $key Variable name
     * @param int $default Default value
     * @return int
     */
    public static function getInt($key, $default = 0) {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }
    
    /**
     * Get all loaded environment variables
     * 
     * @return array
     */
    public static function getAll() {
        return self::$variables;
    }
    
    /**
     * Check if environment is loaded
     * 
     * @return bool
     */
    public static function isLoaded() {
        return self::$loaded;
    }
    
    /**
     * Validate required environment variables
     * 
     * @param array $required Array of required variable names
     * @return array Array of missing variables
     */
    public static function validateRequired($required) {
        $missing = [];
        
        foreach ($required as $key) {
            if (!self::has($key)) {
                $missing[] = $key;
            }
        }
        
        return $missing;
    }
}

/**
 * Helper function to get environment variable
 * 
 * @param string $key Variable name
 * @param mixed $default Default value
 * @return mixed
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

/**
 * Helper function to get boolean environment variable
 * 
 * @param string $key Variable name
 * @param bool $default Default value
 * @return bool
 */
function env_bool($key, $default = false) {
    return EnvLoader::getBool($key, $default);
}

/**
 * Helper function to get integer environment variable
 * 
 * @param string $key Variable name
 * @param int $default Default value
 * @return int
 */
function env_int($key, $default = 0) {
    return EnvLoader::getInt($key, $default);
}
