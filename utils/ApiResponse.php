<?php
/**
 * API Response Utility
 * Provides standardized API responses across the application
 */

class ApiResponse {
    
    /**
     * Send success response
     */
    public static function success($data = [], $message = 'Success', $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        self::sendResponse($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'Error', $statusCode = 400, $details = []) {
        $response = [
            'success' => false,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('c')
        ];
        
        self::sendResponse($response, $statusCode);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors = [], $message = 'Validation failed') {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ];
        
        self::sendResponse($response, 422);
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }
    
    /**
     * Send internal server error response
     */
    public static function internalError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $pagination, $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
            'timestamp' => date('c')
        ];
        
        self::sendResponse($response, 200);
    }
    
    /**
     * Send the actual response
     */
    private static function sendResponse($data, $statusCode) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Handle CORS preflight requests
     */
    public static function handleCors() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Validate required request method
     */
    public static function validateMethod($allowedMethods) {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if (!in_array($method, $allowedMethods)) {
            self::methodNotAllowed("Method {$method} not allowed. Allowed methods: " . implode(', ', $allowedMethods));
        }
    }
    
    /**
     * Get request body as JSON
     */
    public static function getRequestBody() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON in request body', 400);
        }
        
        return $data;
    }
    
    /**
     * Validate required fields in request data
     */
    public static function validateRequired($data, $requiredFields) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        if (!empty($errors)) {
            self::validationError($errors);
        }
        
        return $data;
    }
    
    /**
     * Sanitize and validate email
     */
    public static function validateEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::validationError(['email' => 'Invalid email format']);
        }
        
        return $email;
    }
    
    /**
     * Log API request for debugging
     */
    public static function logRequest($endpoint, $data = []) {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $logData = [
                'timestamp' => date('c'),
                'endpoint' => $endpoint,
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
                'data' => $data
            ];
            
            $logFile = __DIR__ . '/../logs/api_requests.log';
            $logDir = dirname($logFile);
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
?> 