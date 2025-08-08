<?php
// Debug script for live server
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Capture all server variables and environment info
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'not set',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'not set'
    ]
];

// Test database detection
try {
    require_once 'config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debug_info['database'] = [
        'status' => 'connected',
        'contact_count' => $result['count'],
        'environment_detected' => 'working'
    ];
    
} catch (Exception $e) {
    $debug_info['database'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// Test ContactController
try {
    require_once 'controllers/ContactController.php';
    $controller = new ContactController($pdo);
    $result = $controller->list_all(1, 5); // Get first 5 contacts
    
    $debug_info['controller'] = [
        'status' => 'working',
        'contacts_returned' => count($result['data'] ?? []),
        'success' => $result['success'] ?? false
    ];
    
} catch (Exception $e) {
    $debug_info['controller'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// Test specific contact ID (from the screenshot - we can see contact IDs)
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM email_recipients WHERE id = ? LIMIT 1");
        $stmt->execute([1818]); // Using ID from screenshot
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug_info['direct_query'] = [
            'status' => 'success',
            'contact_found' => $contact ? true : false,
            'contact_data' => $contact ? array_keys($contact) : []
        ];
    }
} catch (Exception $e) {
    $debug_info['direct_query'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
