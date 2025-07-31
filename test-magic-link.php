<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simple test page for magic link system
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Magic Link System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Magic Link System Test</h2>
        
        <?php
        // Test 1: Check if database tables exist
        try {
            require_once __DIR__ . "/config/database.php";
            $database = new Database();
            $db = $database->getConnection();
            
            echo '<div class="alert alert-info">Database Type: ' . $database->getDatabaseType() . '</div>';
            
            // Check auth_tokens table
            if ($database->getDatabaseType() === 'sqlite') {
                $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='auth_tokens'");
            } else {
                $stmt = $db->query("SHOW TABLES LIKE 'auth_tokens'");
            }
            
            if ($stmt->fetch()) {
                echo '<div class="alert alert-success">✅ auth_tokens table exists</div>';
            } else {
                echo '<div class="alert alert-danger">❌ auth_tokens table missing - <a href="database/create_auth_tokens_table.php">Create it</a></div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Database Error: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <h3 class="mt-4">Test Sending Magic Link</h3>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            
            try {
                require_once __DIR__ . "/models/User.php";
                require_once __DIR__ . "/models/AuthToken.php";
                
                // Check if user exists
                $userModel = new User($db);
                $user = $userModel->findBy("email", $email);
                
                if (!$user) {
                    echo '<div class="alert alert-warning">User not found: ' . htmlspecialchars($email) . '</div>';
                } else {
                    echo '<div class="alert alert-info">User found: ' . $user['first_name'] . ' ' . $user['last_name'] . ' (Role: ' . $user['role'] . ')</div>';
                    
                    if (in_array($user['role'], ['agent', 'manager'])) {
                        // Generate token
                        $authTokenModel = new AuthToken($db);
                        $token = $authTokenModel->generateToken($email);
                        
                        if ($token) {
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            
                            // Get base path
                            require_once __DIR__ . "/config/base_path.php";
                            $basePath = BasePath::getBasePath();
                            
                            $loginUrl = "{$protocol}://{$host}{$basePath}/employee/auth?token={$token}";
                            
                            echo '<div class="alert alert-success">';
                            echo '<h5>✅ Magic link generated!</h5>';
                            echo '<p>Login URL: <a href="' . $loginUrl . '" target="_blank">' . $loginUrl . '</a></p>';
                            echo '<p>Token: ' . $token . '</p>';
                            echo '<p>This link expires in 30 minutes</p>';
                            echo '</div>';
                            
                            // Log to file
                            $logDir = __DIR__ . '/logs';
                            if (!is_dir($logDir)) {
                                mkdir($logDir, 0777, true);
                            }
                            $logFile = $logDir . '/test_login_links.log';
                            $logEntry = date('Y-m-d H:i:s') . " - {$email}: {$loginUrl}\n";
                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                            
                        } else {
                            echo '<div class="alert alert-danger">Failed to generate token</div>';
                        }
                    } else {
                        echo '<div class="alert alert-warning">User is not an employee (must be agent or manager)</div>';
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
        ?>
        
        <form method="POST" class="mt-3">
            <div class="mb-3">
                <label>Test Employee Emails:</label>
                <ul>
                    <li>john.agent@example.com</li>
                    <li>jane.manager@example.com</li>
                </ul>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Employee Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate Magic Link</button>
        </form>
        
        <hr class="mt-5">
        
        <h3>Test API Endpoint</h3>
        <button onclick="testAPI()" class="btn btn-secondary">Test API</button>
        <div id="apiResult"></div>
        
        <script>
        async function testAPI() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<div class="spinner-border" role="status"></div>';
            
            try {
                const response = await fetch('/api-employee-send-link.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: 'john.agent@example.com' })
                });
                
                const text = await response.text();
                console.log('Raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    resultDiv.innerHTML = '<pre class="alert alert-info">' + JSON.stringify(data, null, 2) + '</pre>';
                } catch (e) {
                    resultDiv.innerHTML = '<div class="alert alert-warning">Response is not JSON:<br>' + text + '</div>';
                }
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="alert alert-danger">Network Error: ' + error.message + '</div>';
            }
        }
        </script>
    </div>
</body>
</html>