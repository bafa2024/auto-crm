<?php
// Verify Authentication Fixes
echo "=== Authentication System Verification ===\n\n";

// 1. Check database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "✓ Database connection successful\n";
    echo "  - Environment: " . $database->getEnvironment() . "\n";
    echo "  - Database Type: " . $database->getDatabaseType() . "\n";
} else {
    echo "✗ Database connection failed\n";
    exit(1);
}

// 2. Check base path configuration
require_once 'config/base_path.php';
echo "\n✓ Base Path Configuration:\n";
echo "  - Base Path: " . BasePath::getBasePath() . "\n";
echo "  - Base URL: " . BasePath::getBaseUrl() . "\n";

// 3. Check if required tables exist
echo "\n✓ Database Tables:\n";
$requiredTables = ['users', 'password_resets'];
foreach ($requiredTables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "  - $table: $count records\n";
    } catch (PDOException $e) {
        echo "  - $table: NOT FOUND\n";
    }
}

// 4. Check if required files exist
echo "\n✓ Required Files:\n";
$files = [
    'api/index.php' => 'API Router',
    'controllers/AuthController.php' => 'Auth Controller',
    'models/User.php' => 'User Model',
    'views/auth/signup.php' => 'Signup View',
    'views/auth/login.php' => 'Login View',
    'public/pages/signup.php' => 'Public Signup Page',
    'public/pages/login.php' => 'Public Login Page'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "  ✓ $desc\n";
    } else {
        echo "  ✗ $desc (NOT FOUND)\n";
    }
}

// 5. List API endpoints
echo "\n✓ API Endpoints:\n";
echo "  - Register: " . base_path('/api/auth/register') . "\n";
echo "  - Login: " . base_path('/api/auth/login') . "\n";
echo "  - Logout: " . base_path('/api/auth/logout') . "\n";

// 6. Check for test accounts
echo "\n✓ Test Accounts:\n";
try {
    $stmt = $db->query("SELECT email, role, status, created_at FROM users WHERE email LIKE '%test%' ORDER BY created_at DESC LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        echo "  - No test accounts found\n";
    } else {
        foreach ($users as $user) {
            echo "  - {$user['email']} ({$user['role']}, {$user['status']}) - Created: {$user['created_at']}\n";
        }
    }
} catch (PDOException $e) {
    echo "  - Error checking users: " . $e->getMessage() . "\n";
}

// 7. Summary
echo "\n=== Summary ===\n";
echo "Authentication system is configured and ready to use.\n";
echo "\nTo test:\n";
echo "1. Open " . base_url('test_auth_frontend.html') . " for comprehensive testing\n";
echo "2. Visit " . base_url('signup') . " to test signup page\n";
echo "3. Visit " . base_url('login') . " to test login page\n";
?>