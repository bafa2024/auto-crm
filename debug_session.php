<?php
// Session debug tool for migrate.php login issues
session_start();

header('Content-Type: application/json');

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION ?? [],
    'server_info' => [
        'PHP_VERSION' => PHP_VERSION,
        'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
        'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
        'session.save_path' => ini_get('session.save_path'),
        'session.name' => ini_get('session.name'),
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'HTTPS' => isset($_SERVER['HTTPS']) ? 'yes' : 'no'
    ],
    'cookies' => $_COOKIE ?? [],
    'post_data' => $_POST ?? [],
    'get_data' => $_GET ?? []
];

// Test session write
if (isset($_GET['test_session'])) {
    $_SESSION['test_time'] = time();
    $_SESSION['test_data'] = 'Session test successful';
    $debug_info['session_test'] = 'Session write attempted';
}

// Test admin user lookup
if (isset($_GET['test_admin'])) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT id, email, role, status FROM users WHERE email = 'admin@autocrm.com'");
        $stmt->execute();
        $admin_user = $stmt->fetch();
        
        $debug_info['admin_test'] = [
            'found' => $admin_user ? true : false,
            'data' => $admin_user ? ['id' => $admin_user['id'], 'email' => $admin_user['email'], 'role' => $admin_user['role'], 'status' => $admin_user['status']] : null
        ];
    } catch (Exception $e) {
        $debug_info['admin_test'] = [
            'error' => $e->getMessage()
        ];
    }
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
