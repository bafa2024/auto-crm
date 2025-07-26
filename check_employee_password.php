<?php
// Simple diagnostic to check employee password on live server

// Force environment detection
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'regrowup.ca') !== false || 
    strpos($_SERVER['SERVER_NAME'] ?? '', 'regrowup.ca') !== false) {
    $_ENV['DB_ENVIRONMENT'] = 'live';
}

require_once __DIR__ . "/config/database.php";

$database = new Database();
$db = $database->getConnection();

header('Content-Type: text/plain');

echo "=== Employee Password Check ===\n\n";
echo "Environment: " . $database->getEnvironment() . "\n";
echo "Database Type: " . $database->getDatabaseType() . "\n";
echo "Host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n\n";

$email = 'test@employee.com';

// Check if employee exists
$stmt = $db->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "User found:\n";
    echo "- ID: " . $user['id'] . "\n";
    echo "- Email: " . $user['email'] . "\n";
    echo "- Role: " . $user['role'] . "\n";
    echo "- Status: " . $user['status'] . "\n";
    echo "- Password length: " . strlen($user['password']) . "\n";
    echo "- Password type: " . (strlen($user['password']) > 50 ? "HASHED" : "PLAIN TEXT") . "\n";
    
    if (strlen($user['password']) < 50) {
        echo "- Actual password: " . $user['password'] . "\n";
    } else {
        echo "- Password hash: " . substr($user['password'], 0, 20) . "...\n";
    }
    
    // Test authentication
    echo "\nTesting authentication:\n";
    echo "- Trying password 'password123'...\n";
    
    if (strlen($user['password']) > 50) {
        // It's hashed
        if (password_verify('password123', $user['password'])) {
            echo "  ✅ Password verification successful (hashed)\n";
        } else {
            echo "  ❌ Password verification failed (hashed)\n";
        }
    } else {
        // It's plain text
        if ($user['password'] === 'password123') {
            echo "  ✅ Password matches (plain text)\n";
        } else {
            echo "  ❌ Password does not match\n";
            echo "  Expected: password123\n";
            echo "  Actual: " . $user['password'] . "\n";
        }
    }
} else {
    echo "❌ User not found: $email\n";
}

echo "\n⚠️  Delete this file after checking!";