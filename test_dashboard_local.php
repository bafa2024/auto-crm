<?php
// test_dashboard_local.php - Local Testing Dashboard for XAMPP

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session for testing
session_start();

// Load environment variables
$env = [];
if (file_exists(__DIR__ . '/.env')) {
    $envFile = file_get_contents(__DIR__ . '/.env');
    $envLines = explode("\n", $envFile);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    // Set environment variables
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Test results storage
$testResults = [];

// Function to add test result
function addTestResult($category, $test, $status, $message = '', $details = '') {
    global $testResults;
    $testResults[] = [
        'category' => $category,
        'test' => $test,
        'status' => $status,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Function to run a test
function runTest($category, $test, $callback) {
    try {
        $result = $callback();
        addTestResult($category, $test, 'PASS', $result['message'] ?? 'Test passed', $result['details'] ?? '');
    } catch (Exception $e) {
        addTestResult($category, $test, 'FAIL', $e->getMessage(), $e->getTraceAsString());
    }
}

// Handle AJAX test requests
if (isset($_POST['action']) && $_POST['action'] === 'run_test') {
    header('Content-Type: application/json');
    
    $testType = $_POST['test_type'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown test'];
    
    switch ($testType) {
        case 'database':
            try {
                require_once 'autoload.php';
                require_once 'config/database.php';
                
                $database = new Database();
                $db = $database->getConnection();
                
                if ($db) {
                    // Test basic query
                    $stmt = $db->query("SELECT 1 as test");
                    $result = $stmt->fetch();
                    
                    if ($result && $result['test'] == 1) {
                        $response = ['success' => true, 'message' => 'Database connection successful'];
                    } else {
                        $response = ['success' => false, 'message' => 'Database query failed'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Database connection failed'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
            break;
            
        case 'signup':
            try {
                $testData = [
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'company_name' => 'Test Company',
                    'email' => 'test' . time() . '@example.com',
                    'password' => 'testpassword123'
                ];
                
                // Test local API
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://localhost/autocrm/api.php/api/auth/register');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response_text = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode == 200) {
                    $response = ['success' => true, 'message' => 'Signup test successful', 'data' => $response_text];
                } else {
                    $response = ['success' => false, 'message' => "Signup failed with HTTP $httpCode", 'data' => $response_text];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Signup error: ' . $e->getMessage()];
            }
            break;
            
        case 'login':
            try {
                $testData = [
                    'email' => 'admin@autocrm.com',
                    'password' => 'admin123'
                ];
                
                // Test local API
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://localhost/autocrm/api.php/api/auth/login');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response_text = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode == 200) {
                    $response = ['success' => true, 'message' => 'Login test successful', 'data' => $response_text];
                } else {
                    $response = ['success' => false, 'message' => "Login failed with HTTP $httpCode", 'data' => $response_text];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
            }
            break;
            
        case 'direct_signup':
            try {
                // Test direct controller call
                require_once 'autoload.php';
                require_once 'controllers/AuthController.php';
                
                $controller = new AuthController();
                
                // Simulate POST data
                $_POST = [
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'company_name' => 'Test Company',
                    'email' => 'test' . time() . '@example.com',
                    'password' => 'testpassword123'
                ];
                
                // Capture output
                ob_start();
                $controller->register();
                $output = ob_get_clean();
                
                $response = ['success' => true, 'message' => 'Direct signup test completed', 'data' => $output];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Direct signup error: ' . $e->getMessage()];
            }
            break;
            
        case 'direct_login':
            try {
                // Test direct controller call
                require_once 'autoload.php';
                require_once 'controllers/AuthController.php';
                
                $controller = new AuthController();
                
                // Simulate POST data
                $_POST = [
                    'email' => 'admin@autocrm.com',
                    'password' => 'admin123'
                ];
                
                // Capture output
                ob_start();
                $controller->login();
                $output = ob_get_clean();
                
                $response = ['success' => true, 'message' => 'Direct login test completed', 'data' => $output];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Direct login error: ' . $e->getMessage()];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Run initial tests
runTest('Environment', 'PHP Version', function() {
    if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
        return ['message' => 'PHP ' . PHP_VERSION . ' is compatible'];
    }
    throw new Exception('PHP 7.4+ required, found ' . PHP_VERSION);
});

runTest('Environment', 'Required Extensions', function() {
    $required = ['pdo', 'pdo_mysql', 'json', 'curl'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    if (empty($missing)) {
        return ['message' => 'All required extensions loaded'];
    }
    throw new Exception('Missing extensions: ' . implode(', ', $missing));
});

runTest('Environment', 'Environment File', function() {
    if (file_exists(__DIR__ . '/.env')) {
        return ['message' => '.env file exists'];
    }
    throw new Exception('.env file not found');
});

runTest('Environment', 'Environment Variables', function() {
    global $env;
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
    $missing = [];
    
    foreach ($required as $var) {
        if (empty($env[$var])) {
            $missing[] = $var;
        }
    }
    
    if (empty($missing)) {
        return ['message' => 'All required environment variables set'];
    }
    throw new Exception('Missing environment variables: ' . implode(', ', $missing));
});

runTest('Database', 'Connection', function() {
    global $env;
    
    $dsn = "mysql:host=" . $env['DB_HOST'] . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . $env['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    return ['message' => 'Database connection successful'];
});

runTest('Database', 'Tables Check', function() {
    global $env;
    
    $dsn = "mysql:host=" . $env['DB_HOST'] . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . $env['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS']);
    
    $requiredTables = ['users', 'contacts', 'email_campaigns', 'email_templates'];
    $missing = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        return ['message' => 'All required tables exist'];
    }
    throw new Exception('Missing tables: ' . implode(', ', $missing));
});

runTest('Database', 'Admin User', function() {
    global $env;
    
    $dsn = "mysql:host=" . $env['DB_HOST'] . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . $env['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS']);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    $stmt->execute(['admin@autocrm.com']);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['message' => 'Admin user exists'];
    }
    throw new Exception('Admin user not found');
});

runTest('Files', 'Autoloader', function() {
    if (file_exists(__DIR__ . '/autoload.php')) {
        require_once 'autoload.php';
        return ['message' => 'Autoloader loaded successfully'];
    }
    throw new Exception('Autoloader file not found');
});

runTest('Files', 'Database Class', function() {
    if (file_exists(__DIR__ . '/config/database.php')) {
        require_once 'config/database.php';
        if (class_exists('Database')) {
            return ['message' => 'Database class loaded'];
        }
        throw new Exception('Database class not found');
    }
    throw new Exception('Database config file not found');
});

runTest('Files', 'AuthController', function() {
    if (file_exists(__DIR__ . '/controllers/AuthController.php')) {
        require_once 'controllers/AuthController.php';
        if (class_exists('AuthController')) {
            return ['message' => 'AuthController class loaded'];
        }
        throw new Exception('AuthController class not found');
    }
    throw new Exception('AuthController file not found');
});

runTest('Files', 'User Model', function() {
    if (file_exists(__DIR__ . '/models/User.php')) {
        require_once 'models/User.php';
        if (class_exists('User')) {
            return ['message' => 'User model loaded'];
        }
        throw new Exception('User model not found');
    }
    throw new Exception('User model file not found');
});

runTest('API', 'Local Endpoint', function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/autocrm/api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 404) {
        return ['message' => 'Local API endpoint accessible'];
    }
    throw new Exception("Local API returned HTTP $httpCode");
});

runTest('Security', 'File Permissions', function() {
    $directories = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions'];
    $issues = [];
    
    foreach ($directories as $dir) {
        if (is_dir(__DIR__ . '/' . $dir)) {
            if (!is_writable(__DIR__ . '/' . $dir)) {
                $issues[] = "$dir not writable";
            }
        } else {
            $issues[] = "$dir directory missing";
        }
    }
    
    if (empty($issues)) {
        return ['message' => 'All directories have proper permissions'];
    }
    throw new Exception('Permission issues: ' . implode(', ', $issues));
});

runTest('XAMPP', 'Apache Running', function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return ['message' => 'Apache is running'];
    }
    throw new Exception("Apache not responding (HTTP $httpCode)");
});

runTest('XAMPP', 'Project Accessible', function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/autocrm/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 || $httpCode == 404) {
        return ['message' => 'Project directory accessible'];
    }
    throw new Exception("Project not accessible (HTTP $httpCode)");
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDial Pro CRM - Local Testing Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-result { margin-bottom: 10px; }
        .test-result.pass { color: #198754; }
        .test-result.fail { color: #dc3545; }
        .test-details { font-size: 0.9em; color: #6c757d; margin-left: 20px; }
        .category-section { margin-bottom: 30px; }
        .progress { height: 25px; }
        .btn-test { margin: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-speedometer2"></i> AutoDial Pro CRM - Local Testing Dashboard
                </h1>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Total Tests</h5>
                                <h3 class="text-primary" id="totalTests">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Passed</h5>
                                <h3 class="text-success" id="passedTests">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Failed</h5>
                                <h3 class="text-danger" id="failedTests">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Success Rate</h5>
                                <h3 class="text-info" id="successRate">0%</h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="progress mb-4">
                    <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%"></div>
                </div>
                
                <!-- Test Results -->
                <div id="testResults">
                    <?php
                    $categories = [];
                    foreach ($testResults as $result) {
                        if (!isset($categories[$result['category']])) {
                            $categories[$result['category']] = [];
                        }
                        $categories[$result['category']][] = $result;
                    }
                    
                    foreach ($categories as $category => $results): ?>
                        <div class="category-section">
                            <h3 class="mb-3">
                                <i class="bi bi-gear"></i> <?= htmlspecialchars($category) ?>
                            </h3>
                            <div class="card">
                                <div class="card-body">
                                    <?php foreach ($results as $result): ?>
                                        <div class="test-result <?= strtolower($result['status']) ?>">
                                            <i class="bi bi-<?= $result['status'] === 'PASS' ? 'check-circle' : 'x-circle' ?>"></i>
                                            <strong><?= htmlspecialchars($result['test']) ?></strong>
                                            <span class="badge bg-<?= $result['status'] === 'PASS' ? 'success' : 'danger' ?> ms-2">
                                                <?= $result['status'] ?>
                                            </span>
                                            <div class="test-details">
                                                <?= htmlspecialchars($result['message']) ?>
                                                <?php if (!empty($result['details'])): ?>
                                                    <br><small><code><?= htmlspecialchars($result['details']) ?></code></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Manual Tests -->
                <div class="category-section">
                    <h3 class="mb-3">
                        <i class="bi bi-play-circle"></i> Manual Tests
                    </h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <button class="btn btn-primary btn-test" onclick="runManualTest('database')">
                                        <i class="bi bi-database"></i> Test Database
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-success btn-test" onclick="runManualTest('signup')">
                                        <i class="bi bi-person-plus"></i> Test Signup API
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-info btn-test" onclick="runManualTest('login')">
                                        <i class="bi bi-box-arrow-in-right"></i> Test Login API
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-warning btn-test" onclick="runManualTest('direct_signup')">
                                        <i class="bi bi-person-plus"></i> Direct Signup
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-secondary btn-test" onclick="runManualTest('direct_login')">
                                        <i class="bi bi-box-arrow-in-right"></i> Direct Login
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-dark btn-test" onclick="runAllTests()">
                                        <i class="bi bi-arrow-clockwise"></i> Run All Tests
                                    </button>
                                </div>
                            </div>
                            <div id="manualTestResults" class="mt-3"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Environment Info -->
                <div class="category-section">
                    <h3 class="mb-3">
                        <i class="bi bi-info-circle"></i> Environment Information
                    </h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Server Information</h5>
                                    <ul class="list-unstyled">
                                        <li><strong>PHP Version:</strong> <?= PHP_VERSION ?></li>
                                        <li><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></li>
                                        <li><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></li>
                                        <li><strong>HTTPS:</strong> <?= isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No' ?></li>
                                        <li><strong>Local URL:</strong> http://localhost/autocrm/</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5>Database Configuration</h5>
                                    <ul class="list-unstyled">
                                        <li><strong>Host:</strong> <?= $env['DB_HOST'] ?? 'Not set' ?></li>
                                        <li><strong>Database:</strong> <?= $env['DB_NAME'] ?? 'Not set' ?></li>
                                        <li><strong>User:</strong> <?= $env['DB_USER'] ?? 'Not set' ?></li>
                                        <li><strong>Port:</strong> <?= $env['DB_PORT'] ?? '3306' ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update summary on page load
        updateSummary();
        
        function updateSummary() {
            const results = <?= json_encode($testResults) ?>;
            const total = results.length;
            const passed = results.filter(r => r.status === 'PASS').length;
            const failed = results.filter(r => r.status === 'FAIL').length;
            const successRate = total > 0 ? Math.round((passed / total) * 100) : 0;
            
            document.getElementById('totalTests').textContent = total;
            document.getElementById('passedTests').textContent = passed;
            document.getElementById('failedTests').textContent = failed;
            document.getElementById('successRate').textContent = successRate + '%';
            document.getElementById('progressBar').style.width = successRate + '%';
        }
        
        function runManualTest(testType) {
            const button = event.target;
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Running...';
            
            fetch('test_dashboard_local.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=run_test&test_type=${testType}`
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('manualTestResults');
                const resultHtml = `
                    <div class="alert alert-${data.success ? 'success' : 'danger'} alert-dismissible fade show">
                        <strong>${testType.toUpperCase()} Test:</strong> ${data.message}
                        ${data.data ? `<br><small><code>${data.data}</code></small>` : ''}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                resultsDiv.innerHTML = resultHtml + resultsDiv.innerHTML;
            })
            .catch(error => {
                const resultsDiv = document.getElementById('manualTestResults');
                const resultHtml = `
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>${testType.toUpperCase()} Test:</strong> Network error
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                resultsDiv.innerHTML = resultHtml + resultsDiv.innerHTML;
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
        
        function runAllTests() {
            const tests = ['database', 'signup', 'login', 'direct_signup', 'direct_login'];
            tests.forEach((test, index) => {
                setTimeout(() => {
                    runManualTest(test);
                }, index * 1000);
            });
        }
    </script>
</body>
</html> 