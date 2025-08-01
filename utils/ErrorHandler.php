<?php
/**
 * Error Handler Utility
 * Provides consistent error handling across the application
 */

class ErrorHandler {
    
    /**
     * Log error with context
     */
    public static function logError($message, $context = [], $level = 'ERROR') {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Log to file
        $logFile = __DIR__ . '/../logs/error.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log if in development
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log($logMessage);
        }
    }
    
    /**
     * Handle database errors
     */
    public static function handleDatabaseError($e, $operation = '') {
        $context = [
            'operation' => $operation,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        
        self::logError("Database error: " . $e->getMessage(), $context);
        
        return [
            'success' => false,
            'message' => 'Database operation failed',
            'error' => defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? $e->getMessage() : 'Internal server error'
        ];
    }
    
    /**
     * Handle API errors
     */
    public static function handleApiError($e, $endpoint = '') {
        $context = [
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
        ];
        
        self::logError("API error: " . $e->getMessage(), $context);
        
        return [
            'success' => false,
            'message' => 'API operation failed',
            'error' => defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? $e->getMessage() : 'Internal server error'
        ];
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $requiredFields) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Send JSON response
     */
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Handle file upload errors
     */
    public static function handleFileUploadError($file) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = $errors[$file['error']] ?? 'Unknown upload error';
        
        self::logError("File upload error: {$errorMessage}", [
            'file' => $file['name'],
            'size' => $file['size'],
            'error_code' => $file['error']
        ]);
        
        return $errorMessage;
    }
    
    /**
     * Validate file type and size
     */
    public static function validateFile($file, $allowedTypes = [], $maxSize = 10485760) {
        $errors = [];
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size (" . number_format($maxSize / 1024 / 1024, 1) . "MB)";
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedTypes)) {
                $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowedTypes);
            }
        }
        
        return $errors;
    }
    
    /**
     * Set error reporting based on environment
     */
    public static function setErrorReporting($environment = 'production') {
        if ($environment === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }
    }
    
    /**
     * Create a standardized error response
     */
    public static function createErrorResponse($message, $code = 400, $details = []) {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'details' => $details,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Create a standardized success response
     */
    public static function createSuccessResponse($data = [], $message = 'Success') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
    }
}
?> 