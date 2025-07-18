<?php
// test_signup_login.php - Test signup and login with new credentials

echo "Testing Signup and Login with New Credentials\n";
echo "===========================================\n\n";

$timestamp = time();
$testUser = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => "john.doe.$timestamp@example.com",
    'password' => 'mypassword123',
    'company_name' => 'Doe Enterprises'
];

echo "Test User Data:\n";
foreach ($testUser as $key => $value) {
    if ($key !== 'password') {
        echo "- $key: $value\n";
    } else {
        echo "- $key: " . str_repeat('*', strlen($value)) . "\n";
    }
}
echo "\n";

// 1. Test Signup
echo "1. Testing Signup...\n";

$signupUrl = 'http://localhost/acrm/api/auth/register';
$signupData = json_encode($testUser);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $signupUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $signupData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($signupData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$signupResponse = curl_exec($ch);
$signupHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$signupError = curl_error($ch);
curl_close($ch);

echo "Signup URL: $signupUrl\n";
echo "HTTP Code: $signupHttpCode\n";

if ($signupResponse !== false) {
    echo "Response: $signupResponse\n";
    
    $signupJson = json_decode($signupResponse, true);
    if ($signupJson) {
        if ($signupJson['success']) {
            echo "✅ SIGNUP SUCCESSFUL!\n";
            echo "Message: " . $signupJson['message'] . "\n";
            $newUserId = $signupJson['data']['id'] ?? 'unknown';
            echo "New User ID: $newUserId\n";
        } else {
            echo "❌ Signup failed: " . $signupJson['message'] . "\n";
            exit(1);
        }
    } else {
        echo "❌ Invalid JSON response\n";
        exit(1);
    }
} else {
    echo "❌ Signup request failed: $signupError\n";
    exit(1);
}

// 2. Test Login with new credentials
echo "\n2. Testing Login with new credentials...\n";

$loginUrl = 'http://localhost/acrm/api/auth/login';
$loginData = json_encode([
    'email' => $testUser['email'],
    'password' => $testUser['password']
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($loginData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loginError = curl_error($ch);
curl_close($ch);

echo "Login URL: $loginUrl\n";
echo "HTTP Code: $loginHttpCode\n";

if ($loginResponse !== false) {
    echo "Response: $loginResponse\n";
    
    $loginJson = json_decode($loginResponse, true);
    if ($loginJson) {
        if ($loginJson['success']) {
            echo "✅ LOGIN SUCCESSFUL!\n";
            echo "Message: " . $loginJson['message'] . "\n";
            
            if (isset($loginJson['data']['user'])) {
                $user = $loginJson['data']['user'];
                echo "Logged in as: " . $user['first_name'] . " " . $user['last_name'] . "\n";
                echo "Role: " . ($user['role'] ?? 'not set') . "\n";
                echo "Company: " . ($user['company_name'] ?? 'not set') . "\n";
            }
            
            if (isset($loginJson['data']['redirect'])) {
                echo "Redirect URL: " . $loginJson['data']['redirect'] . "\n";
            }
        } else {
            echo "❌ Login failed: " . $loginJson['message'] . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
    }
} else {
    echo "❌ Login request failed: $loginError\n";
}

// 3. Check database to verify user was created
echo "\n3. Verifying user in database...\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, company_name, created_at FROM users WHERE email = ?");
    $stmt->execute([$testUser['email']]);
    $dbUser = $stmt->fetch();
    
    if ($dbUser) {
        echo "✅ User found in database:\n";
        foreach ($dbUser as $key => $value) {
            echo "  - $key: $value\n";
        }
    } else {
        echo "❌ User not found in database\n";
    }
    
    // Show total users
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "\nTotal users in database: $totalUsers\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "SIGNUP AND LOGIN TEST COMPLETE\n";
echo str_repeat("=", 50) . "\n";

echo "\n🎉 You can now signup and login with different credentials!\n\n";

echo "To test in browser:\n";
echo "1. Go to: http://localhost/acrm/signup\n";
echo "2. Fill out the form with your details\n";
echo "3. Click 'Create Account'\n";
echo "4. You'll be redirected to login\n";
echo "5. Login with your new credentials\n";
echo "6. Access the dashboard with your account\n\n";

echo "Current test user credentials:\n";
echo "Email: " . $testUser['email'] . "\n";
echo "Password: " . $testUser['password'] . "\n";
?>