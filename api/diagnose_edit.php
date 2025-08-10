<?php
// Diagnostic endpoint for campaign edit issues
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session' => [
        'id' => session_id(),
        'status' => session_status(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'data' => $_SESSION
    ],
    'php' => [
        'version' => PHP_VERSION,
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'error_reporting' => error_reporting()
    ],
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'None'
    ]
];

// Test database connection
try {
    require_once '../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    $diagnostics['database'] = [
        'connected' => true,
        'type' => $database->getDatabaseType()
    ];
    
    // Test campaign table
    $stmt = $conn->query("SELECT COUNT(*) as count FROM email_campaigns");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $diagnostics['database']['campaign_count'] = $result['count'];
    
} catch (Exception $e) {
    $diagnostics['database'] = [
        'connected' => false,
        'error' => $e->getMessage()
    ];
}

// Check for common issues
$issues = [];

if (!isset($_SESSION['user_id'])) {
    $issues[] = 'No user_id in session - user not logged in';
}

if (!extension_loaded('pdo')) {
    $issues[] = 'PDO extension not loaded';
}

if (!extension_loaded('pdo_mysql')) {
    $issues[] = 'PDO MySQL extension not loaded';
}

$diagnostics['issues'] = $issues;
$diagnostics['has_issues'] = count($issues) > 0;

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>