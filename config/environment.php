<?php
/**
 * Environment Configuration
 * Manages environment-specific settings and constants
 */

// Detect environment
function detectEnvironment() {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (strpos($hostname, 'localhost') !== false || strpos($hostname, '127.0.0.1') !== false) {
        return 'development';
    }
    
    return 'production';
}

// Set environment
define('ENVIRONMENT', detectEnvironment());

// Environment-specific settings
switch (ENVIRONMENT) {
    case 'development':
        // Development settings
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
        
        // Database settings for development
        if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
        if (!defined('DB_NAME')) define('DB_NAME', 'acrm');
        if (!defined('DB_USER')) define('DB_USER', 'root');
        if (!defined('DB_PASS')) define('DB_PASS', '');
        if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
        
        // File upload settings
        if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
        if (!defined('ALLOWED_UPLOAD_TYPES')) define('ALLOWED_UPLOAD_TYPES', ['csv', 'xlsx', 'xls']);
        if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');
        
        // API settings
        define('API_RATE_LIMIT', 1000); // requests per hour
        define('API_TIMEOUT', 30); // seconds
        
        // Security settings
        define('SESSION_TIMEOUT', 3600); // 1 hour
        define('PASSWORD_MIN_LENGTH', 6);
        
        // CORS settings
        define('CORS_ALLOWED_ORIGINS', ['http://localhost:8000', 'http://127.0.0.1:8000']);
        define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        define('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Requested-With']);
        
        break;
        
    case 'production':
        // Production settings
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
        
        // Database settings for production
        if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
        if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'acrm');
        if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
        if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
        if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
        
        // File upload settings
        if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
        if (!defined('ALLOWED_UPLOAD_TYPES')) define('ALLOWED_UPLOAD_TYPES', ['csv', 'xlsx', 'xls']);
        if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');
        
        // API settings
        define('API_RATE_LIMIT', 100); // requests per hour
        define('API_TIMEOUT', 15); // seconds
        
        // Security settings
        define('SESSION_TIMEOUT', 1800); // 30 minutes
        define('PASSWORD_MIN_LENGTH', 8);
        
        // CORS settings
        define('CORS_ALLOWED_ORIGINS', [getenv('ALLOWED_ORIGIN') ?: 'https://yourdomain.com']);
        define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        define('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Requested-With']);
        
        break;
}

// Common settings
if (!defined('APP_NAME')) define('APP_NAME', 'ACRM');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Create necessary directories
$directories = [
    __DIR__ . '/../logs',
    __DIR__ . '/../uploads',
    __DIR__ . '/../cache',
    __DIR__ . '/../sessions'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Security headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (ENVIRONMENT === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Initialize security headers
setSecurityHeaders();

// Load environment-specific configuration
$envConfigFile = __DIR__ . '/environment.' . ENVIRONMENT . '.php';
if (file_exists($envConfigFile)) {
    require_once $envConfigFile;
}

// Validate required constants
$requiredConstants = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'MAX_UPLOAD_SIZE', 'ALLOWED_UPLOAD_TYPES', 'UPLOAD_PATH'
];

foreach ($requiredConstants as $constant) {
    if (!defined($constant)) {
        die("Required constant {$constant} is not defined");
    }
}

// Log environment initialization
if (ENVIRONMENT === 'development') {
    error_log("ACRM initialized in " . ENVIRONMENT . " mode");
}
?> 