<?php
/**
 * Auto-detect base path configuration
 * Automatically detects the project folder name and creates base URL
 */

class BasePath {
    private static $basePath = null;
    private static $baseUrl = null;
    
    /**
     * Get the base path (folder name) of the project
     */
    public static function getBasePath() {
        if (self::$basePath === null) {
            // Get the current script path
            $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Extract the project folder from the path
            $pathParts = explode('/', trim($scriptPath, '/'));
            
            // Remove 'index.php' or other PHP files from the end
            $pathParts = array_filter($pathParts, function($part) {
                return !preg_match('/\.php$/', $part);
            });
            
            // Get the project folder name (usually the last part)
            $projectFolder = end($pathParts);
            
            // If we're in the root directory, use empty string
            if (empty($projectFolder) || $projectFolder === 'htdocs') {
                self::$basePath = '';
            } else {
                self::$basePath = '/' . $projectFolder;
            }
            
            // Special handling for common scenarios
            if (strpos($requestUri, '/acrm/') !== false) {
                self::$basePath = '/acrm';
            } elseif (strpos($requestUri, '/autocrm/') !== false) {
                self::$basePath = '/autocrm';
            }
        }
        
        return self::$basePath;
    }
    
    /**
     * Get the base URL for the project
     */
    public static function getBaseUrl() {
        if (self::$baseUrl === null) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = self::getBasePath();
            
            self::$baseUrl = $protocol . '://' . $host . $basePath;
        }
        
        return self::$baseUrl;
    }
    
    /**
     * Create a URL with the base path
     */
    public static function url($path = '') {
        $basePath = self::getBasePath();
        $path = ltrim($path, '/');
        return $basePath . '/' . $path;
    }
    
    /**
     * Create an absolute URL with the base path
     */
    public static function absoluteUrl($path = '') {
        $baseUrl = self::getBaseUrl();
        $path = ltrim($path, '/');
        return $baseUrl . '/' . $path;
    }
    
    /**
     * Get the current environment (local or live)
     */
    public static function getEnvironment() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (in_array($host, ['localhost', '127.0.0.1']) || strpos($host, 'localhost') !== false) {
            return 'local';
        } else {
            return 'live';
        }
    }
    
    /**
     * Debug function to show detected paths
     */
    public static function debug() {
        return [
            'base_path' => self::getBasePath(),
            'base_url' => self::getBaseUrl(),
            'environment' => self::getEnvironment(),
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'N/A'
        ];
    }
}

// Helper functions for easy access
function base_path($path = '') {
    return BasePath::url($path);
}

function base_url($path = '') {
    return BasePath::absoluteUrl($path);
}

function is_local() {
    return BasePath::getEnvironment() === 'local';
}

function is_live() {
    return BasePath::getEnvironment() === 'live';
}
?> 