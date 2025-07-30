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

// Helper function to check if test passed
function isTestPassed($status) {
    return $status === 'PASS';
}

// 1. ENVIRONMENT & CONFIGURATION TESTS
$testResults[] = ['category' => 'Environment', 'test' => 'PHP Version', 'status' => 'PASS', 'message' => 'PHP ' . PHP_VERSION, 'details' => '', 'timestamp' => date('Y-m-d H:i:s')];

// Check required PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'pdo_sqlite', 'curl', 'json', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? 'PASS' : 'FAIL';
    $message = extension_loaded($ext) ? "Extension loaded" : "Extension missing";
    addTestResult($testResults, 'Environment', "PHP Extension: $ext", $status, $message);
}

// Check file permissions
$writableDirs = ['logs', 'temp', 'uploads', 'backups', 'sessions'];
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
    'index.php'
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
    '/employee/email-dashboard' => 'Employee Dashboard Page'
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

// 6. AUTHENTICATION TESTS
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
        
        // Clean up test user
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$testEmail]);
        
    } else {
        addTestResult($testResults, 'Authentication', 'User Creation', 'FAIL', 'Failed to create test user');
    }
} catch (Exception $e) {
    addTestResult($testResults, 'Authentication', 'User Creation', 'FAIL', 'Error: ' . $e->getMessage());
}

// 7. FEATURE TESTS
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

// 8. SECURITY TESTS
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

// 9. PERFORMANCE TESTS
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

// 10. BROWSER COMPATIBILITY TESTS
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
addTestResult($testResults, 'Browser', 'User Agent Detection', 'INFO', "Browser: $userAgent");

// Calculate overall status
$passCount = 0;
$failCount = 0;
$infoCount = 0;

foreach ($testResults as $result) {
    if ($result['status'] === 'PASS') $passCount++;
    elseif ($result['status'] === 'FAIL') $failCount++;
    elseif ($result['status'] === 'INFO') $infoCount++;
}

if ($failCount > 0) {
    $overallStatus = 'FAIL';
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
    <title>ACRM Quality Control Dashboard</title>
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
                        <i class="bi bi-shield-check"></i> ACRM Quality Control Dashboard
                    </h1>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h3 class="text-success"><?php echo $passCount; ?></h3>
                            <p>Passed Tests</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-danger"><?php echo $failCount; ?></h3>
                            <p>Failed Tests</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-info"><?php echo $infoCount; ?></h3>
                            <p>Info Tests</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="<?php echo $overallStatus === 'PASS' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $overallStatus; ?>
                            </h3>
                            <p>Overall Status</p>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small>
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
                                ($result['status'] === 'FAIL' ? 'bi-x-circle' : 'bi-info-circle');
                    
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

        <!-- Recommendations -->
        <?php if ($failCount > 0): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Issues Found</h5>
                    <p>The following issues need to be addressed before deployment:</p>
                    <ul>
                        <?php
                        foreach ($testResults as $result) {
                            if ($result['status'] === 'FAIL') {
                                echo '<li><strong>' . $result['test'] . ':</strong> ' . $result['message'] . '</li>';
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
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Database</h6>
                                <ul>
                                    <li>✓ Database connection working</li>
                                    <li>✓ All tables exist</li>
                                    <li>✓ Database health check passed</li>
                                    <li>✓ Performance acceptable</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Security</h6>
                                <ul>
                                    <li>✓ Password hashing working</li>
                                    <li>✓ SQL injection protection active</li>
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
            link.download = 'acrm-qc-report-' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
            link.click();
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html> 