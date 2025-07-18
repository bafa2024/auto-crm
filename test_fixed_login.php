<?php
// test_fixed_login.php - Test the fixed login URL

echo "Testing Fixed Login\n";
echo "==================\n\n";

// Test the corrected URL
$loginUrl = 'http://localhost/acrm/api/auth/login';
$loginData = [
    'email' => 'admin@autocrm.com',
    'password' => 'admin123'
];

echo "Testing URL: $loginUrl\n";
echo "Login data: " . json_encode($loginData) . "\n\n";

$postData = json_encode($loginData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($response !== false) {
    echo "Response: $response\n\n";
    
    $json = json_decode($response, true);
    if ($json) {
        if ($json['success']) {
            echo "✅ LOGIN SUCCESSFUL!\n";
            echo "Message: " . $json['message'] . "\n";
            if (isset($json['data']['redirect'])) {
                echo "Redirect to: " . $json['data']['redirect'] . "\n";
            }
        } else {
            echo "❌ Login failed: " . $json['message'] . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
    }
} else {
    echo "❌ Request failed: $error\n";
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "NETWORK ERROR SHOULD BE FIXED!\n";
echo str_repeat("=", 40) . "\n";
echo "\nFixed Issues:\n";
echo "- Changed /api/auth/login → /acrm/api/auth/login\n";
echo "- Changed /dashboard → /acrm/dashboard\n";
echo "- Updated signup page URLs too\n";
echo "\nTry logging in now with:\n";
echo "Email: admin@autocrm.com\n";
echo "Password: admin123\n";
?>