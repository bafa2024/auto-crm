
<?php
// Application Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u946493694_autocrm');
define('DB_USER', 'u946493694_autocrmu');
define('DB_PASS', 'CDExzsawq123@#$');

// Application settings
define('APP_NAME', 'AutoDial Pro CRM');
define('APP_URL', 'https://autocrm.regrowup.ca/');
define('APP_VERSION', '1.0.0');

// Email settings (these should come from database settings)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');

// File upload settings
define('MAX_UPLOAD_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_UPLOAD_TYPES', ['csv', 'xlsx', 'xls']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Security settings
define('JWT_SECRET', 'your-secret-key-change-this');
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 3600); // 1 hour

// Email rate limiting
define('EMAIL_RATE_LIMIT', 100); // emails per hour
define('EMAIL_BATCH_SIZE', 50); // emails per batch

// Timezone
define('DEFAULT_TIMEZONE', 'UTC');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS settings for API
define('CORS_ALLOWED_ORIGINS', ['http://localhost:3000', 'http://localhost:8080']);
define('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
define('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization', 'X-Requested-With']);

// Pagination defaults
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 100);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour

// API rate limiting
define('API_RATE_LIMIT', 1000); // requests per hour per IP
define('API_RATE_WINDOW', 3600); // 1 hour in seconds

// File paths
define('LOG_PATH', __DIR__ . '/../logs/');
define('TEMP_PATH', __DIR__ . '/../temp/');
define('BACKUP_PATH', __DIR__ . '/../backups/');

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
    
    error_log(json_encode($error), 3, LOG_PATH . 'errors.log');
    
    if (defined('API_MODE') && API_MODE) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
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
    
    error_log(json_encode($error), 3, LOG_PATH . 'exceptions.log');
    
    if (defined('API_MODE') && API_MODE) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
});
