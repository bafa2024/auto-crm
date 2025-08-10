<?php
// Application Configuration
//if local
$http_host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($http_host, 'localhost') !== false) {
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'autocrm');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
} else {
  

    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'u946493694_autocrm');
    if (!defined('DB_USER')) define('DB_USER', 'u946493694_autocrmu');
    if (!defined('DB_PASS')) define('DB_PASS', 'CDExzsawq123@#$');
}

//

// Application settings
if (!defined('APP_NAME')) define('APP_NAME', 'AutoDial Pro CRM');
if (!defined('APP_URL')) define('APP_URL', 'https://autocrm.regrowup.ca/');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Email settings (these should come from database settings)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');

// File upload settings
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 10485760); // 10MB in bytes
if (!defined('ALLOWED_UPLOAD_TYPES')) define('ALLOWED_UPLOAD_TYPES', ['csv', 'xlsx', 'xls']);
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Security settings
if (!defined('JWT_SECRET')) define('JWT_SECRET', 'your-secret-key-change-this');
if (!defined('BCRYPT_COST')) define('BCRYPT_COST', 12);
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 hour

// Email rate limiting
if (!defined('EMAIL_RATE_LIMIT')) define('EMAIL_RATE_LIMIT', 100); // emails per hour
if (!defined('EMAIL_BATCH_SIZE')) define('EMAIL_BATCH_SIZE', 50); // emails per batch

// Timezone - Set your preferred timezone here
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'America/New_York'); // Change this to your timezone
if (!defined('DEFAULT_TIMEZONE')) define('DEFAULT_TIMEZONE', APP_TIMEZONE);
date_default_timezone_set(APP_TIMEZONE);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If running in API mode, force JSON-safe error output (no HTML)
if (defined('API_MODE') && API_MODE) {
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
}

// CORS settings for API
if (!defined('CORS_ALLOWED_ORIGINS')) define('CORS_ALLOWED_ORIGINS', ['http://localhost', 'http://localhost:3000', 'http://localhost:8080', '*']);
if (!defined('CORS_ALLOWED_METHODS')) define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
if (!defined('CORS_ALLOWED_HEADERS')) define('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Requested-With']);

// Pagination defaults
if (!defined('DEFAULT_PAGE_SIZE')) define('DEFAULT_PAGE_SIZE', 25);
if (!defined('MAX_PAGE_SIZE')) define('MAX_PAGE_SIZE', 100);

// Cache settings
if (!defined('CACHE_ENABLED')) define('CACHE_ENABLED', true);
if (!defined('CACHE_DURATION')) define('CACHE_DURATION', 3600); // 1 hour

// API rate limiting
if (!defined('API_RATE_LIMIT')) define('API_RATE_LIMIT', 1000); // requests per hour per IP
if (!defined('API_RATE_WINDOW')) define('API_RATE_WINDOW', 3600); // 1 hour in seconds

// File paths
if (!defined('LOG_PATH')) define('LOG_PATH', __DIR__ . '/../logs/');
if (!defined('TEMP_PATH')) define('TEMP_PATH', __DIR__ . '/../temp/');
if (!defined('BACKUP_PATH')) define('BACKUP_PATH', __DIR__ . '/../backups/');

// Create necessary directories if they don't exist
$dirs = [UPLOAD_PATH, LOG_PATH, TEMP_PATH, BACKUP_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Autoloader for classes
spl_autoload_register(function($class) {
    $prefixes = [
        'Models\\' => __DIR__ . '/../models/',
        'Controllers\\' => __DIR__ . '/../controllers/',
        'Services\\' => __DIR__ . '/../services/',
        'Utils\\' => __DIR__ . '/../utils/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Global error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $error = [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'timestamp' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    
    // Only log to file if LOG_PATH exists
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        error_log(json_encode($error) . "\n", 3, LOG_PATH . 'errors.log');
    }
    
    if (defined('API_MODE') && API_MODE) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
        exit;
    }
    
    return false; // Let PHP handle the error
});

// Global exception handler
set_exception_handler(function($exception) {
    $error = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Only log to file if LOG_PATH exists
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        error_log(json_encode($error) . "\n", 3, LOG_PATH . 'exceptions.log');
    }
    
    if (defined('API_MODE') && API_MODE) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
        exit;
    }
});
