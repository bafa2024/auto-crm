<?php
abstract class BaseController {
    protected $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    protected function sendJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }
    
    protected function sendError($message, $statusCode = 400, $errors = []) {
        $this->sendJson([
            "success" => false,
            "message" => $message,
            "errors" => $errors
        ], $statusCode);
    }
    
    protected function sendSuccess($data = [], $message = "Success") {
        $this->sendJson([
            "success" => true,
            "message" => $message,
            "data" => $data
        ]);
    }
    
    protected function validateRequired($data, $required) {
        $errors = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . " is required";
            }
        }
        return $errors;
    }
    
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, "sanitizeInput"], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, "UTF-8");
    }
    
    protected function getPaginationParams() {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;
        return [$page, $perPage];
    }
    
    protected function getSearchParams() {
        return [
            'search' => $_GET['search'] ?? '',
            'filter' => [
                'status' => $_GET['status'] ?? '',
                'assigned_agent_id' => $_GET['assigned_agent_id'] ?? ''
            ]
        ];
    }
}