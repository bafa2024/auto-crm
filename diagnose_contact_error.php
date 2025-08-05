<?php
// Diagnostic script to identify contact creation issues
echo "=== Contact Creation Error Diagnosis ===\n\n";

// 1. Check if the API file exists
echo "1. Checking API file existence...\n";
if (file_exists('api/contacts_api.php')) {
    echo "✅ api/contacts_api.php exists\n";
} else {
    echo "❌ api/contacts_api.php not found\n";
    exit;
}

// 2. Check API file permissions
echo "\n2. Checking API file permissions...\n";
$perms = fileperms('api/contacts_api.php');
echo "Permissions: " . substr(sprintf('%o', $perms), -4) . "\n";

// 3. Test API accessibility
echo "\n3. Testing API accessibility...\n";
$url = 'http://localhost/acrm/api/contacts_api.php?action=list_all';
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($url, false, $context);
if ($response !== false) {
    echo "✅ API is accessible\n";
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['success'])) {
        echo "✅ API returns valid JSON\n";
    } else {
        echo "❌ API does not return valid JSON\n";
    }
} else {
    echo "❌ API is not accessible\n";
}

// 4. Test database connection
echo "\n4. Testing database connection...\n";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=autocrm", 'root', '');
    echo "✅ Database connection successful\n";
    
    // Test table access
    $stmt = $pdo->query("SELECT COUNT(*) FROM email_recipients");
    $count = $stmt->fetchColumn();
    echo "✅ email_recipients table accessible (count: $count)\n";
} catch(Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// 5. Test create_contact endpoint specifically
echo "\n5. Testing create_contact endpoint...\n";
$testData = [
    'name' => 'Diagnostic Test User',
    'email' => 'diagnostic.test.' . time() . '@example.com',
    'company' => 'Diagnostic Test Company',
    'dot' => '999888'
];

$url = 'http://localhost/acrm/api/contacts_api.php?action=create_contact';
$jsonData = json_encode($testData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ],
        'content' => $jsonData,
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($url, false, $context);
if ($response !== false) {
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['success'])) {
        if ($decoded['success']) {
            echo "✅ create_contact endpoint working - Contact ID: " . $decoded['data']['id'] . "\n";
        } else {
            echo "❌ create_contact endpoint error: " . ($decoded['error'] ?? $decoded['message']) . "\n";
        }
    } else {
        echo "❌ create_contact endpoint returns invalid JSON\n";
        echo "Raw response: " . substr($response, 0, 200) . "...\n";
    }
} else {
    echo "❌ create_contact endpoint not accessible\n";
}

// 6. Check for common issues
echo "\n6. Checking for common issues...\n";

// Check if session is required
echo "Checking session requirements...\n";
$apiContent = file_get_contents('api/contacts_api.php');
if (strpos($apiContent, 'session_start()') !== false) {
    echo "⚠️  API uses sessions\n";
} else {
    echo "✅ API does not require sessions\n";
}

// Check for CORS headers
if (strpos($apiContent, 'Access-Control-Allow-Origin') !== false) {
    echo "✅ API has CORS headers\n";
} else {
    echo "⚠️  API missing CORS headers\n";
}

// Check for error reporting
if (strpos($apiContent, 'error_reporting') !== false) {
    echo "✅ API has error reporting\n";
} else {
    echo "⚠️  API missing error reporting\n";
}

echo "\n=== Diagnosis Complete ===\n";
echo "If the API tests above show ✅ SUCCESS, the issue is likely:\n";
echo "1. Browser JavaScript errors (check browser console)\n";
echo "2. Network connectivity issues\n";
echo "3. CORS policy restrictions\n";
echo "4. Session/authentication issues\n";
echo "\nTo debug further:\n";
echo "1. Open browser developer tools (F12)\n";
echo "2. Go to Console tab\n";
echo "3. Try to create a contact\n";
echo "4. Look for any error messages\n";
?> 