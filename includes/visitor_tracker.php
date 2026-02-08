<?php
/**
 * Visitor Tracking System
 * Records visitor information for analytics purposes
 * 
 * @author AleppoGift Development Team
 * @version 1.0
 * @date January 27, 2026
 */

class VisitorTracker {
    private $db;
    
    // Paths that should not be tracked (admin, api, ajax, assets)
    private static $excludedPatterns = [
        '/admin/',
        '/ajax/',
        '/api/',
        '/assets/',
        '/uploads/',
        '/test_',
        '/debug_',
        '/check_',
        '/fix_',
        '/setup_',
        '/install_',
        '.css',
        '.js',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.ico',
        '.svg',
        '.woff',
        '.woff2',
        '.ttf',
        'robots.txt',
        'sitemap.xml'
    ];
    
    // Bot user agents to exclude
    private static $botPatterns = [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'googlebot',
        'bingbot',
        'yandex',
        'baidu',
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegram',
        'curl',
        'wget',
        'python-requests',
        'postman',
        'insomnia'
    ];
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Track a visitor's page view
     * 
     * @param string|null $page Optional page to track (defaults to current page)
     * @return bool Whether the visit was recorded
     */
    public function trackVisit($page = null) {
        // Skip if already tracked in this session within the last minute
        if ($this->recentlyTracked()) {
            return false;
        }
        
        // Get request info
        $requestUri = $page ?? ($_SERVER['REQUEST_URI'] ?? '/');
        
        // Skip excluded paths
        if ($this->isExcludedPath($requestUri)) {
            return false;
        }
        
        // Get user agent and skip bots
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($this->isBot($userAgent)) {
            return false;
        }
        
        // Get visitor IP
        $ip = $this->getClientIp();
        
        // Skip local IPs in production
        if ($this->isLocalIp($ip) && defined('IS_PRODUCTION') && IS_PRODUCTION) {
            return false;
        }
        
        // Get country from IP (async-friendly, cached)
        $country = $this->getCountryFromIp($ip);
        
        // Record the visit
        try {
            if ($this->db) {
                $this->db->query(
                    "INSERT INTO visitors (ip_address, visit_time, page_url, user_agent, referer, country, country_code) 
                     VALUES (?, NOW(), ?, ?, ?, ?, ?)",
                    [
                        $ip,
                        $this->sanitizeUrl($requestUri),
                        substr($userAgent, 0, 500),
                        substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
                        $country['country'] ?? null,
                        $country['code'] ?? null
                    ]
                );
                
                // Mark as tracked in session
                $_SESSION['last_tracked'] = time();
                
                return true;
            }
        } catch (Exception $e) {
            // Silently fail - don't interrupt user experience for tracking
            error_log("Visitor tracking error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get country information from IP address
     * Uses ip-api.com (free, 45 requests/minute limit)
     * 
     * @param string $ip IP address
     * @return array ['country' => string, 'code' => string]
     */
    private function getCountryFromIp($ip) {
        // Skip for local/private IPs
        if ($this->isLocalIp($ip)) {
            return ['country' => 'Local', 'code' => 'LO'];
        }
        
        // Clean IPv6 marker if present
        $cleanIp = str_replace(' (IPv6)', '', $ip);
        if (strpos($cleanIp, ':...:') !== false) {
            // Can't lookup shortened IP - would need original
            return ['country' => 'Unknown', 'code' => 'XX'];
        }
        
        try {
            // Use ip-api.com (free, no API key required)
            $url = "http://ip-api.com/json/" . urlencode($cleanIp) . "?fields=status,country,countryCode";
            
            // Use cURL with timeout to avoid slowing down page loads
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2, // 2 second timeout
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if ($data && isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'code' => $data['countryCode'] ?? 'XX'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("IP Geolocation error: " . $e->getMessage());
        }
        
        return ['country' => 'Unknown', 'code' => 'XX'];
    }
    
    /**
     * Check if a visit was recently tracked (within 60 seconds)
     */
    private function recentlyTracked() {
        if (isset($_SESSION['last_tracked'])) {
            $timeSinceLastTrack = time() - $_SESSION['last_tracked'];
            return $timeSinceLastTrack < 60; // 1 minute cooldown
        }
        return false;
    }
    
    /**
     * Check if path should be excluded from tracking
     */
    private function isExcludedPath($path) {
        $pathLower = strtolower($path);
        foreach (self::$excludedPatterns as $pattern) {
            if (strpos($pathLower, strtolower($pattern)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user agent is a bot
     */
    private function isBot($userAgent) {
        if (empty($userAgent)) {
            return true; // Empty user agent is likely a bot
        }
        
        $userAgentLower = strtolower($userAgent);
        foreach (self::$botPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get the real client IP address (prefers IPv4)
     */
    private function getClientIp() {
        // Check for proxy headers (in order of reliability)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'REMOTE_ADDR'                // Fallback
        ];
        
        $ip = null;
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs, take the first
                $rawIp = $_SERVER[$header];
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $rawIp);
                    $rawIp = trim($ips[0]);
                }
                
                // Validate IP format
                if (filter_var($rawIp, FILTER_VALIDATE_IP)) {
                    $ip = $rawIp;
                    break;
                }
            }
        }
        
        if ($ip === null) {
            return '0.0.0.0';
        }
        
        // Convert IPv6 to IPv4 if it's a mapped address, otherwise return full IP
        return $this->normalizeIp($ip);
    }
    
    /**
     * Normalize IP address - convert mapped IPv6 to IPv4, keep others as-is
     * @param string $ip IP address
     * @return string Normalized IP (full, not shortened)
     */
    private function normalizeIp($ip) {
        // Check if it's already IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        
        // Check for IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Check for IPv4-mapped IPv6 address (::ffff:192.168.1.1)
            if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches)) {
                return $matches[1];
            }
            
            // Check for IPv4-compatible IPv6 (::192.168.1.1)
            if (preg_match('/^::(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches)) {
                return $matches[1];
            }
            
            // For pure IPv6, normalize format but keep FULL address for geolocation
            return inet_ntop(inet_pton($ip));
        }
        
        return $ip;
    }
    
    /**
     * Check if IP is a local/private IP
     */
    private function isLocalIp($ip) {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }
        
        // Check for private IP ranges
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /**
     * Sanitize URL for storage
     */
    private function sanitizeUrl($url) {
        // Remove query strings with sensitive data
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Optionally keep safe query params
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            // Keep only non-sensitive params
            $safeParams = [];
            $allowedParams = ['page', 'category', 'brand', 'search', 's'];
            foreach ($allowedParams as $param) {
                if (isset($params[$param])) {
                    $safeParams[$param] = substr($params[$param], 0, 100);
                }
            }
            if (!empty($safeParams)) {
                $path .= '?' . http_build_query($safeParams);
            }
        }
        
        return substr($path, 0, 500);
    }
    
    /**
     * Create the visitors table if it doesn't exist
     */
    public static function ensureTableExists($db) {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS visitors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                visit_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                page_url VARCHAR(500) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                referer VARCHAR(500) DEFAULT NULL,
                INDEX idx_visit_time (visit_time),
                INDEX idx_ip_address (ip_address),
                INDEX idx_ip_time (ip_address, visit_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $db->query($sql);
            return true;
        } catch (Exception $e) {
            error_log("Failed to create visitors table: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Global function to track visits (convenience wrapper)
 */
function trackVisitor($db = null) {
    static $tracker = null;
    
    if ($tracker === null && $db !== null) {
        $tracker = new VisitorTracker($db);
    }
    
    if ($tracker !== null) {
        return $tracker->trackVisit();
    }
    
    return false;
}
