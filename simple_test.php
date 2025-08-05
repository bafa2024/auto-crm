<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Simple test starting...\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = new PDO("mysql:host=localhost;dbname=autocrm", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful.\n";
    
    // Test if we can query the table
    echo "Testing table query...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
    $count = $stmt->fetch()['count'];
    echo "Found $count contacts in database.\n";
    
    // Test ContactController
    echo "Testing ContactController...\n";
    require_once 'controllers/BaseController.php';
    require_once 'controllers/ContactController.php';
    
    $controller = new ContactController($pdo);
    echo "ContactController created successfully.\n";
    
    // Test list_all method
    echo "Testing list_all method...\n";
    $result = $controller->list_all(1, 5, '', '', '');
    echo "list_all method completed.\n";
    
    if ($result['success']) {
        echo "Success! Found " . count($result['data']) . " contacts.\n";
        echo "First contact: " . json_encode($result['data'][0]) . "\n";
    } else {
        echo "Error: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
?>