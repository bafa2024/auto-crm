<?php
// Fix employee passwords on LIVE server
// This script updates existing employees to use plain text passwords

// Force environment to 'live' to use MySQL
$_ENV['DB_ENVIRONMENT'] = 'live';
$_SERVER['SERVER_NAME'] = 'acrm.regrowup.ca';
$_SERVER['HTTP_HOST'] = 'acrm.regrowup.ca';

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/models/User.php";

echo "<h2>Fix Employee Passwords for LIVE Server</h2>";
echo "<pre>";

$database = new Database();
$db = $database->getConnection();

echo "Database Type: " . $database->getDatabaseType() . "\n";
echo "Environment: " . $database->getEnvironment() . "\n\n";

if ($database->getDatabaseType() !== 'mysql') {
    die("ERROR: This script must be run on the live server with MySQL database!\n");
}

// Update test@employee.com password to plain text
echo "=== Updating Employee Passwords to Plain Text ===\n\n";

$employees = [
    ['email' => 'test@employee.com', 'password' => 'password123'],
    ['email' => 'agent1@test.com', 'password' => 'agent123'],
    ['email' => 'manager1@test.com', 'password' => 'manager123']
];

foreach ($employees as $emp) {
    // Check if employee exists
    $stmt = $db->prepare("SELECT id, role, password FROM users WHERE email = ?");
    $stmt->execute([$emp['email']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Found {$emp['email']} (ID: {$user['id']}, Role: {$user['role']})\n";
        
        // Check if password is already plain text
        $isHashed = strlen($user['password']) > 50;
        echo "  Current password is: " . ($isHashed ? "HASHED" : "PLAIN TEXT") . "\n";
        
        if ($isHashed || $user['password'] !== $emp['password']) {
            // Update to plain text password
            $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$emp['password'], $user['id']]);
            echo "  ✅ Updated password to: {$emp['password']}\n\n";
        } else {
            echo "  ✅ Password already correct\n\n";
        }
    } else {
        echo "❌ {$emp['email']} not found - Creating new employee...\n";
        
        // Create new employee
        $userModel = new User($db);
        $newData = [
            'email' => $emp['email'],
            'password' => $emp['password'],
            'first_name' => 'Test',
            'last_name' => ucfirst(explode('@', $emp['email'])[0]),
            'company_name' => 'Test Company',
            'phone' => '',
            'role' => strpos($emp['email'], 'manager') !== false ? 'manager' : 'agent',
            'status' => 'active'
        ];
        
        $newUser = $userModel->create($newData);
        if ($newUser) {
            echo "  ✅ Created new employee: {$emp['email']}\n\n";
        } else {
            echo "  ❌ Failed to create employee\n\n";
        }
    }
}

// List all employees to verify
echo "=== Current Employees in LIVE Database ===\n";
$stmt = $db->query("SELECT id, email, first_name, last_name, role, status, password FROM users WHERE role IN ('agent', 'manager') ORDER BY id DESC LIMIT 10");
$employees = $stmt->fetchAll();

echo "\n";
foreach ($employees as $emp) {
    $passwordType = strlen($emp['password']) > 50 ? "HASHED" : "PLAIN TEXT";
    echo "ID: {$emp['id']}, {$emp['first_name']} {$emp['last_name']}\n";
    echo "  Email: {$emp['email']}\n";
    echo "  Role: {$emp['role']}, Status: {$emp['status']}\n";
    echo "  Password Type: $passwordType\n";
    if ($passwordType === "PLAIN TEXT") {
        echo "  Password: {$emp['password']}\n";
    }
    echo "\n";
}

// Test authentication
echo "=== Testing Authentication ===\n";
$userModel = new User($db);

$testLogins = [
    ['email' => 'test@employee.com', 'password' => 'password123'],
    ['email' => 'agent1@test.com', 'password' => 'agent123'],
    ['email' => 'manager1@test.com', 'password' => 'manager123']
];

foreach ($testLogins as $test) {
    $result = $userModel->authenticate($test['email'], $test['password']);
    if ($result) {
        echo "✅ {$test['email']} - Login successful!\n";
    } else {
        echo "❌ {$test['email']} - Login failed\n";
        
        // Debug why it failed
        $stmt = $db->prepare("SELECT password, status, role FROM users WHERE email = ?");
        $stmt->execute([$test['email']]);
        $debug = $stmt->fetch();
        if ($debug) {
            echo "   Status: {$debug['status']}, Role: {$debug['role']}\n";
            echo "   Stored password: {$debug['password']}\n";
            echo "   Expected password: {$test['password']}\n";
        } else {
            echo "   User not found in database\n";
        }
    }
}

echo "\n=== Setup Complete ===\n";
echo "You can now login with:\n";
echo "- Email: test@employee.com\n";
echo "- Password: password123\n";
echo "- URL: https://acrm.regrowup.ca/employee/login\n";

echo "\n<strong>⚠️  IMPORTANT: Delete this file immediately after running!</strong>\n";
echo "</pre>";

// Auto-delete option
echo '<br><br>';
echo '<form method="post" action="">';
echo '<input type="hidden" name="delete_file" value="1">';
echo '<button type="submit" style="background:red;color:white;padding:10px;">Delete This File Now</button>';
echo '</form>';

if (isset($_POST['delete_file'])) {
    unlink(__FILE__);
    echo '<p style="color:green;">File deleted successfully!</p>';
    echo '<script>setTimeout(function(){ window.location.href = "/"; }, 2000);</script>';
}