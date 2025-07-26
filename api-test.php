<?php
// Direct API test without routing
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

// Simple response
echo json_encode([
    'status' => 'success',
    'message' => 'API test endpoint working',
    'request_uri' => $requestUri,
    'request_method' => $requestMethod,
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'none',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'none'
]);