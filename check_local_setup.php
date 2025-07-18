<?php
require_once 'config/database.php';

echo "Local Setup Check\n";
echo "=================\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✓ Database connection: OK\n";
    
    $info = $db->getDatabaseInfo();
    echo "Database type: " . $info['type'] . "\n";
    echo "Database path: " . $info['path'] . "\n";
    echo "Database size: " . $info['size'] . " bytes\n";
    echo "Tables: " . implode(', ', array_keys($info['tables'])) . "\n\n";
    
    // Check for required tables
    $requiredTables = ['users', 'contacts', 'email_campaigns', 'email_recipients'];
    $existingTables = array_keys($info['tables']);
    
    echo "Required Tables Check:\n";
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "✓ $table: EXISTS (" . $info['tables'][$table] . " records)\n";
        } else {
            echo "✗ $table: MISSING\n";
        }
    }
    
    // Check web server access
    echo "\nWeb Server Check:\n";
    echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' . "\n";
    echo "Current directory: " . __DIR__ . "\n";
    
    // Check if XAMPP is running
    echo "\nXAMPP Status:\n";
    if (function_exists('apache_get_version')) {
        echo "✓ Apache: " . apache_get_version() . "\n";
    } else {
        echo "? Apache version check not available\n";
    }
    
    echo "PHP Version: " . phpversion() . "\n";
    
    // Check required PHP extensions
    echo "\nPHP Extensions:\n";
    $extensions = ['pdo', 'pdo_sqlite', 'json', 'mbstring'];
    foreach ($extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "✓ $ext: OK\n";
        } else {
            echo "✗ $ext: MISSING\n";
        }
    }
    
    // Check write permissions
    echo "\nDirectory Permissions:\n";
    $dirs = ['uploads', 'logs', 'sessions', 'temp'];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            if (is_writable($dir)) {
                echo "✓ $dir: WRITABLE\n";
            } else {
                echo "✗ $dir: NOT WRITABLE\n";
            }
        } else {
            echo "? $dir: DIRECTORY NOT FOUND\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Local access URL: http://localhost/acrm/\n";
echo "Email upload test: http://localhost/acrm/test_email_upload_form.php\n";
echo str_repeat("=", 50) . "\n";
?>