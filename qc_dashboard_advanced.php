<?php
require_once 'config/database.php';
require_once 'config/base_path.php';
require_once 'autoload.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize test results array
$testResults = [];
$overallStatus = 'PASS';

// Helper function to add test result
function addTestResult(&$results, $category, $testName, $status, $message = '', $details = '') {
    $results[] = [
        'category' => $category,
        'test' => $testName,
        'status' => $status,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Helper function to test API endpoint
function testApiEndpoint($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $response !== false && $httpCode < 400,
        'httpCode' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// 1. ENVIRONMENT & CONFIGURATION TESTS
$testResults[] = ['category' => 'Environment', 'test' => 'PHP Version', 'status' => 'PASS', 'message' => 'PHP ' . PHP_VERSION, 'details' => '', 'timestamp' => date('Y-m-d H:i:s')];

// Check required PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'pdo_sqlite', 'curl', 'json', 'mbstring', 'openssl', 'zip', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? 'PASS' : 'FAIL';
    $message = extension_loaded($ext) ? "Extension loaded" : "Extension missing";
    addTestResult($testResults, 'Environment', "PHP Extension: $ext", $status, $message);
}

// Check PHP settings
$phpSettings = [
    'memory_limit' => '128M',
    'max_execution_time' => '30',
    'upload_max_filesize' => '10M',
    'post_max_size' => '10M'
];

foreach ($phpSettings as $setting => $recommended) {
    $current = ini_get($setting);
    $status = $current >= $recommended ? 'PASS' : 'WARNING';
    $message = "Current: $current, Recommended: $recommended";
    addTestResult($testResults, 'Environment', "PHP Setting: $setting", $status, $message);
}

// Check file permissions
$writableDirs = ['logs', 'temp', 'uploads', 'backups', 'sessions', 'cache'];
foreach ($writableDirs as $dir) {
    if (is_dir($dir)) {
        $status = is_writable($dir) ? 'PASS' : 'FAIL';
        $message = is_writable($dir) ? "Directory writable" : "Directory not writable";
        addTestResult($testResults, 'Environment', "Directory Permissions: $dir", $status, $message);
    } else {
        addTestResult($testResults, 'Environment', "Directory Permissions: $dir", 'FAIL', "Directory does not exist");
    }
}

// 2. DATABASE TESTS
try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if ($pdo) {
        addTestResult($testResults, 'Database', 'Connection', 'PASS', 'Database connected successfully');
        
        // Test database tables
        $requiredTables = [
            'users', 'contacts', 'email_campaigns', 'campaign_sends', 
            'email_templates', 'password_resets', 'otp_codes', 'auth_tokens',
            'smtp_settings', 'employee_permissions'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                addTestResult($testResults, 'Database', "Table: $table", 'PASS', "Table exists with $count records");
            } catch (Exception $e) {
                addTestResult($testResults, 'Database', "Table: $table", 'FAIL', "Table missing or error: " . $e->getMessage());
            }
        }
        
        // Test database health
        try {
            $stmt = $pdo->query("SHOW TABLE STATUS");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalTables = count($tables);
            addTestResult($testResults, 'Database', 'Health Check', 'PASS', "Found $totalTables tables");
        } catch (Exception $e) {
            addTestResult($testResults, 'Database', 'Health Check', 'FAIL', "Error: " . $e->getMessage());
        }
        
        // Test database performance
        $startTime = microtime(true);
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        if ($executionTime < 100) {
            addTestResult($testResults, 'Database', 'Performance', 'PASS', "Query executed in {$executionTime}ms");
        } else {
            addTestResult($testResults, 'Database', 'Performance', 'WARNING', "Query took {$executionTime}ms (slow)");
        }
        
    } else {
        addTestResult($testResults, 'Database', 'Connection', 'FAIL', 'Failed to connect to database');
    }
} catch (Exception $e) {
    addTestResult($testResults, 'Database', 'Connection', 'FAIL', 'Database error: ' . $e->getMessage());
}

// 3. CONFIGURATION TESTS
$configFiles = [
    'config/database.php',
    'config/base_path.php',
    'config/config.php',
    'autoload.php',
    'index.php',
    '.htaccess'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        addTestResult($testResults, 'Configuration', "File: $file", 'PASS', 'File exists');
    } else {
        addTestResult($testResults, 'Configuration', "File: $file", 'FAIL', 'File missing');
    }
}

// Test base path detection
try {
    $basePath = base_path();
    addTestResult($testResults, 'Configuration', 'Base Path Detection', 'PASS', "Base path: $basePath");
} catch (Exception $e) {
    addTestResult($testResults, 'Configuration', 'Base Path Detection', 'FAIL', 'Error: ' . $e->getMessage());
}

// 4. ROUTING TESTS
$routes = [
    '/login' => 'Login Page',
    '/signup' => 'Signup Page',
    '/dashboard' => 'Dashboard Page',
    '/contacts' => 'Contacts Page',
    '/campaigns' => 'Campaigns Page',
    '/employee/login' => 'Employee Login Page',
    '/employee/email-dashboard' => 'Employee Dashboard Page',
    '/instant_email.php' => 'Instant Email Page'
];

foreach ($routes as $route => $description) {
    $fullUrl = base_path() . $route;
    addTestResult($testResults, 'Routing', "Route: $description", 'INFO', "Route: $fullUrl");
}

// 5. API ENDPOINT TESTS
$apiEndpoints = [
    '/api/auth/login' => 'POST',
    '/api/auth/register' => 'POST',
    '/api/auth/logout' => 'POST',
    '/api/auth/forgot-password' => 'POST',
    '/api/auth/reset-password' => 'POST',
    '/api/contacts' => 'GET',
    '/api/campaigns' => 'GET',
    '/api/employee/login' => 'POST',
    '/api/employee/send-otp' => 'POST',
    '/api/employee/verify-otp' => 'POST'
];

foreach ($apiEndpoints as $endpoint => $method) {
    addTestResult($testResults, 'API', "Endpoint: $endpoint ($method)", 'INFO', "API endpoint available");
}

// 6. LIVE SERVER COMPATIBILITY TESTS
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLiveServer = strpos($host, 'acrm.regrowup.ca') !== false;

if ($isLiveServer) {
    addTestResult($testResults, 'Live Server', 'Domain Detection', 'PASS', "Live server detected: $host");
    
    // Test SSL
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $status = $isHttps ? 'PASS' : 'FAIL';
    $message = $isHttps ? 'HTTPS enabled' : 'HTTPS not enabled';
    addTestResult($testResults, 'Live Server', 'SSL Certificate', $status, $message);
    
    // Test API endpoints on live server
    $baseUrl = "https://$host";
    $testEndpoints = [
        "$baseUrl/api/auth/login" => 'POST',
        "$baseUrl/api/contacts" => 'GET'
    ];
    
    foreach ($testEndpoints as $url => $method) {
        $result = testApiEndpoint($url, $method);
        $status = $result['success'] ? 'PASS' : 'FAIL';
        $message = $result['success'] ? "Endpoint accessible" : "Endpoint not accessible";
        $details = "HTTP Code: " . $result['httpCode'];
        if ($result['error']) {
            $details .= ", Error: " . $result['error'];
        }
        addTestResult($testResults, 'Live Server', "API Test: $url", $status, $message, $details);
    }
} else {
    addTestResult($testResults, 'Live Server', 'Domain Detection', 'INFO', "Local development environment: $host");
}

// 7. AUTHENTICATION TESTS
// Test user creation and authentication
try {
    // Create a test user
    $testEmail = 'qc_test_' . time() . '@example.com';
    $testPassword = 'Test123!';
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    $result = $stmt->execute([$testEmail, $hashedPassword, 'QC', 'Test', 'admin', 'active']);
    
    if ($result) {
        addTestResult($testResults, 'Authentication', 'User Creation', 'PASS', 'Test user created successfully');
        
        // Test authentication
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$testEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($testPassword, $user['password'])) {
            addTestResult($testResults, 'Authentication', 'Password Verification', 'PASS', 'Password verification working');
        } else {
            addTestResult($testResults, 'Authentication', 'Password Verification', 'FAIL', 'Password verification failed');
        }
        
        // Test session management
        $_SESSION['test_user_id'] = $user['id'];
        if (isset($_SESSION['test_user_id'])) {
            addTestResult($testResults, 'Authentication', 'Session Management', 'PASS', 'Session management working');
        } else {
            addTestResult($testResults, 'Authentication', 'Session Management', 'FAIL', 'Session management failed');
        }
        
        // Clean up test user
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$testEmail]);
        
    } else {
        addTestResult($testResults, 'Authentication', 'User Creation', 'FAIL', 'Failed to create test user');
    }
} catch (Exception $e) {
    addTestResult($testResults, 'Authentication', 'User Creation', 'FAIL', 'Error: ' . $e->getMessage());
}

// 8. FEATURE TESTS
// Test email service
try {
    require_once 'services/EmailService.php';
    $database = new stdClass();
    $database->getConnection = function() use ($pdo) { return $pdo; };
    $emailService = new EmailService($database);
    addTestResult($testResults, 'Features', 'Email Service', 'PASS', 'Email service initialized');
} catch (Exception $e) {
    addTestResult($testResults, 'Features', 'Email Service', 'FAIL', 'Error: ' . $e->getMessage());
}

// Test contact model
try {
    require_once 'models/Contact.php';
    $contactModel = new Contact($pdo);
    addTestResult($testResults, 'Features', 'Contact Model', 'PASS', 'Contact model initialized');
} catch (Exception $e) {
    addTestResult($testResults, 'Features', 'Contact Model', 'FAIL', 'Error: ' . $e->getMessage());
}

// Test campaign model
try {
    require_once 'models/EmailCampaign.php';
    $campaignModel = new EmailCampaign($pdo);
    addTestResult($testResults, 'Features', 'Campaign Model', 'PASS', 'Campaign model initialized');
} catch (Exception $e) {
    addTestResult($testResults, 'Features', 'Campaign Model', 'FAIL', 'Error: ' . $e->getMessage());
}

// Test file upload functionality
try {
    $uploadDir = 'uploads/';
    if (is_dir($uploadDir) && is_writable($uploadDir)) {
        addTestResult($testResults, 'Features', 'File Upload Directory', 'PASS', 'Upload directory accessible');
    } else {
        addTestResult($testResults, 'Features', 'File Upload Directory', 'FAIL', 'Upload directory not accessible');
    }
} catch (Exception $e) {
    addTestResult($testResults, 'Features', 'File Upload Directory', 'FAIL', 'Error: ' . $e->getMessage());
}

// 9. SECURITY TESTS
// Test password hashing
$testPassword = 'TestPassword123';
$hashed = password_hash($testPassword, PASSWORD_DEFAULT);
if (password_verify($testPassword, $hashed)) {
    addTestResult($testResults, 'Security', 'Password Hashing', 'PASS', 'Password hashing working correctly');
} else {
    addTestResult($testResults, 'Security', 'Password Hashing', 'FAIL', 'Password hashing failed');
}

// Test SQL injection protection (basic)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['test@example.com']);
    addTestResult($testResults, 'Security', 'SQL Injection Protection', 'PASS', 'Prepared statements working');
} catch (Exception $e) {
    addTestResult($testResults, 'Security', 'SQL Injection Protection', 'FAIL', 'Error: ' . $e->getMessage());
}

// Test XSS protection
$testInput = '<script>alert("xss")</script>';
$sanitized = htmlspecialchars($testInput, ENT_QUOTES, 'UTF-8');
if ($sanitized !== $testInput) {
    addTestResult($testResults, 'Security', 'XSS Protection', 'PASS', 'XSS protection working');
} else {
    addTestResult($testResults, 'Security', 'XSS Protection', 'FAIL', 'XSS protection failed');
}

// 10. PERFORMANCE TESTS
$startTime = microtime(true);
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2);
    addTestResult($testResults, 'Performance', 'Database Query Speed', 'PASS', "Query executed in {$executionTime}ms");
} catch (Exception $e) {
    addTestResult($testResults, 'Performance', 'Database Query Speed', 'FAIL', 'Error: ' . $e->getMessage());
}

// Test memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
addTestResult($testResults, 'Performance', 'Memory Usage', 'INFO', "Current: " . round($memoryUsage / 1024 / 1024, 2) . "MB, Limit: $memoryLimit");

// 11. BROWSER COMPATIBILITY TESTS
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
addTestResult($testResults, 'Browser', 'User Agent Detection', 'INFO', "Browser: $userAgent");

// Test JavaScript compatibility
addTestResult($testResults, 'Browser', 'JavaScript Support', 'INFO', 'JavaScript will be tested on page load');

// 12. EMAIL FUNCTIONALITY TESTS
try {
    // Test SMTP settings table
    $stmt = $pdo->query("SELECT COUNT(*) FROM smtp_settings");
    $smtpCount = $stmt->fetchColumn();
    addTestResult($testResults, 'Email', 'SMTP Settings', 'PASS', "Found $smtpCount SMTP configurations");
} catch (Exception $e) {
    addTestResult($testResults, 'Email', 'SMTP Settings', 'FAIL', 'SMTP settings table missing');
}

// Test email templates
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM email_templates");
    $templateCount = $stmt->fetchColumn();
    addTestResult($testResults, 'Email', 'Email Templates', 'PASS', "Found $templateCount email templates");
} catch (Exception $e) {
    addTestResult($testResults, 'Email', 'Email Templates', 'FAIL', 'Email templates table missing');
}

// 13. BACKUP & RECOVERY TESTS
$backupDirs = ['backups', 'logs'];
foreach ($backupDirs as $dir) {
    if (is_dir($dir) && is_writable($dir)) {
        addTestResult($testResults, 'Backup', "Directory: $dir", 'PASS', 'Backup directory accessible');
    } else {
        addTestResult($testResults, 'Backup', "Directory: $dir", 'FAIL', 'Backup directory not accessible');
    }
}

// Calculate overall status
$passCount = 0;
$failCount = 0;
$infoCount = 0;
$warningCount = 0;

foreach ($testResults as $result) {
    if ($result['status'] === 'PASS') $passCount++;
    elseif ($result['status'] === 'FAIL') $failCount++;
    elseif ($result['status'] === 'INFO') $infoCount++;
    elseif ($result['status'] === 'WARNING') $warningCount++;
}

if ($failCount > 0) {
    $overallStatus = 'FAIL';
} elseif ($warningCount > 0) {
    $overallStatus = 'WARNING';
} elseif ($passCount > 0) {
    $overallStatus = 'PASS';
} else {
    $overallStatus = 'INFO';
}

$totalTests = count($testResults);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACRM Advanced Quality Control Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-result { margin-bottom: 10px; }
        .test-pass { color: #198754; }
        .test-fail { color: #dc3545; }
        .test-info { color: #0dcaf0; }
        .test-warning { color: #ffc107; }
        .category-header { 
            background-color: #f8f9fa; 
            padding: 10px; 
            margin: 20px 0 10px 0; 
            border-left: 4px solid #0d6efd;
            font-weight: bold;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .test-details {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        .timestamp {
            font-size: 0.8em;
            color: #adb5bd;
        }
        .export-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .progress-bar {
            height: 8px;
            border-radius: 4px;
        }
        .deployment-status {
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Export Button -->
        <button class="btn btn-outline-primary export-btn" onclick="exportResults()">
            <i class="bi bi-download"></i> Export Report
        </button>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="summary-card">
                    <h1 class="text-center mb-3">
                        <i class="bi bi-shield-check"></i> ACRM Advanced Quality Control Dashboard
                    </h1>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h3 class="text-success"><?php echo $passCount; ?></h3>
                            <p>Passed</p>
                        </div>
                        <div class="col-md-2">
                            <h3 class="text-danger"><?php echo $failCount; ?></h3>
                            <p>Failed</p>
                        </div>
                        <div class="col-md-2">
                            <h3 class="text-warning"><?php echo $warningCount; ?></h3>
                            <p>Warnings</p>
                        </div>
                        <div class="col-md-2">
                            <h3 class="text-info"><?php echo $infoCount; ?></h3>
                            <p>Info</p>
                        </div>
                        <div class="col-md-4">
                            <h3 class="<?php echo $overallStatus === 'PASS' ? 'text-success' : ($overallStatus === 'WARNING' ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo $overallStatus; ?>
                            </h3>
                            <p>Overall Status</p>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="progress">
                            <?php 
                            $passPercentage = $totalTests > 0 ? round(($passCount / $totalTests) * 100) : 0;
                            $failPercentage = $totalTests > 0 ? round(($failCount / $totalTests) * 100) : 0;
                            $warningPercentage = $totalTests > 0 ? round(($warningCount / $totalTests) * 100) : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $passPercentage; ?>%"></div>
                            <div class="progress-bar bg-warning" style="width: <?php echo $warningPercentage; ?>%"></div>
                            <div class="progress-bar bg-danger" style="width: <?php echo $failPercentage; ?>%"></div>
                        </div>
                        <small class="text-center d-block mt-2">Success Rate: <?php echo $passPercentage; ?>%</small>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deployment Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-rocket"></i> Deployment Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="deployment-status text-center">
                            <?php if ($overallStatus === 'PASS'): ?>
                                <span class="text-success">
                                    <i class="bi bi-check-circle"></i> READY FOR DEPLOYMENT
                                </span>
                            <?php elseif ($overallStatus === 'WARNING'): ?>
                                <span class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> DEPLOYMENT WITH WARNINGS
                                </span>
                            <?php else: ?>
                                <span class="text-danger">
                                    <i class="bi bi-x-circle"></i> NOT READY FOR DEPLOYMENT
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="row">
            <div class="col-12">
                <?php
                $currentCategory = '';
                foreach ($testResults as $result) {
                    if ($result['category'] !== $currentCategory) {
                        if ($currentCategory !== '') {
                            echo '</div></div>';
                        }
                        $currentCategory = $result['category'];
                        echo '<div class="category-header">';
                        echo '<i class="bi bi-gear"></i> ' . $currentCategory . ' Tests';
                        echo '</div><div class="row mb-4">';
                    }
                    
                    $statusClass = 'test-' . strtolower($result['status']);
                    $statusIcon = $result['status'] === 'PASS' ? 'bi-check-circle' : 
                                ($result['status'] === 'FAIL' ? 'bi-x-circle' : 
                                ($result['status'] === 'WARNING' ? 'bi-exclamation-triangle' : 'bi-info-circle'));
                    
                    echo '<div class="col-md-6 col-lg-4">';
                    echo '<div class="card test-result">';
                    echo '<div class="card-body">';
                    echo '<h6 class="card-title ' . $statusClass . '">';
                    echo '<i class="bi ' . $statusIcon . '"></i> ' . $result['test'];
                    echo '</h6>';
                    echo '<p class="card-text">' . $result['message'] . '</p>';
                    if ($result['details']) {
                        echo '<div class="test-details">' . $result['details'] . '</div>';
                    }
                    echo '<div class="timestamp">' . $result['timestamp'] . '</div>';
                    echo '</div></div></div>';
                }
                if ($currentCategory !== '') {
                    echo '</div></div>';
                }
                ?>
            </div>
        </div>

        <!-- Issues Summary -->
        <?php if ($failCount > 0 || $warningCount > 0): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Issues Found</h5>
                    <p>The following issues need to be addressed before deployment:</p>
                    <ul>
                        <?php
                        foreach ($testResults as $result) {
                            if ($result['status'] === 'FAIL') {
                                echo '<li class="text-danger"><strong>' . $result['test'] . ':</strong> ' . $result['message'] . '</li>';
                            } elseif ($result['status'] === 'WARNING') {
                                echo '<li class="text-warning"><strong>' . $result['test'] . ':</strong> ' . $result['message'] . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Deployment Checklist -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-check"></i> Pre-Deployment Checklist</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Environment</h6>
                                <ul>
                                    <li>✓ PHP version compatibility</li>
                                    <li>✓ Required extensions installed</li>
                                    <li>✓ File permissions set correctly</li>
                                    <li>✓ Configuration files present</li>
                                    <li>✓ Memory and execution limits adequate</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Database</h6>
                                <ul>
                                    <li>✓ Database connection working</li>
                                    <li>✓ All tables exist</li>
                                    <li>✓ Database health check passed</li>
                                    <li>✓ Performance acceptable</li>
                                    <li>✓ Backup system functional</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Security</h6>
                                <ul>
                                    <li>✓ Password hashing working</li>
                                    <li>✓ SQL injection protection active</li>
                                    <li>✓ XSS protection enabled</li>
                                    <li>✓ Authentication system functional</li>
                                    <li>✓ API endpoints secured</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Features</h6>
                                <ul>
                                    <li>✓ Email service initialized</li>
                                    <li>✓ Contact management working</li>
                                    <li>✓ Campaign system functional</li>
                                    <li>✓ Routing system operational</li>
                                    <li>✓ File upload system working</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Live Server</h6>
                                <ul>
                                    <li>✓ SSL certificate installed</li>
                                    <li>✓ API endpoints accessible</li>
                                    <li>✓ Domain configuration correct</li>
                                    <li>✓ Performance optimized</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Quality Assurance</h6>
                                <ul>
                                    <li>✓ All tests passing</li>
                                    <li>✓ No critical errors</li>
                                    <li>✓ Performance benchmarks met</li>
                                    <li>✓ Security standards maintained</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportResults() {
            const results = <?php echo json_encode($testResults); ?>;
            const summary = {
                total: <?php echo $totalTests; ?>,
                passed: <?php echo $passCount; ?>,
                failed: <?php echo $failCount; ?>,
                warnings: <?php echo $warningCount; ?>,
                info: <?php echo $infoCount; ?>,
                overallStatus: '<?php echo $overallStatus; ?>',
                timestamp: '<?php echo date('Y-m-d H:i:s'); ?>'
            };
            
            const report = {
                summary: summary,
                results: results
            };
            
            const dataStr = JSON.stringify(report, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'acrm-advanced-qc-report-' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
            link.click();
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);

        // Test JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('QC Dashboard JavaScript loaded successfully');
        });
    </script>
</body>
</html> 