<?php
// live_hosting_debug.php - Debug live hosting issues

header('Content-Type: text/plain');
echo "Live Hosting Diagnostic Tool\n";
echo "============================\n\n";

// 1. Environment Detection
echo "1. Environment Information:\n";
echo "   Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "   Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";
echo "   Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
echo "   HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
echo "   HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No') . "\n";
echo "   Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n\n";

// 2. Path Analysis
echo "2. Path Analysis:\n";
$currentDir = __DIR__;
$webRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
echo "   Current Directory: $currentDir\n";
echo "   Web Root: $webRoot\n";

// Calculate the web path
if ($webRoot && strpos($currentDir, $webRoot) === 0) {
    $webPath = substr($currentDir, strlen($webRoot));
    $webPath = str_replace('\\', '/', $webPath);
    if (!$webPath || $webPath[0] !== '/') {
        $webPath = '/' . ltrim($webPath, '/');
    }
    echo "   Web Path: $webPath\n";
    echo "   Expected API URL: https://" . $_SERVER['HTTP_HOST'] . $webPath . "/api/auth/login\n";
} else {
    echo "   Web Path: Could not determine\n";
}
echo "\n";

// 3. File System Check
echo "3. File System Check:\n";
$criticalFiles = [
    'index.php',
    'api.php', 
    'config/database.php',
    'controllers/AuthController.php',
    'models/User.php',
    'views/auth/login.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "   ✓ $file (exists)\n";
    } else {
        echo "   ✗ $file (missing)\n";
    }
}
echo "\n";

// 4. Database Test
echo "4. Database Connectivity:\n";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            echo "   ✓ Database connection successful\n";
            
            // Test basic query
            $stmt = $db->query("SELECT 1 as test");
            $result = $stmt->fetch();
            if ($result && $result['test'] == 1) {
                echo "   ✓ Database query working\n";
            } else {
                echo "   ✗ Database query failed\n";
            }
            
            // Check if users table exists
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM users");
                $count = $stmt->fetchColumn();
                echo "   ✓ Users table exists ($count users)\n";
            } catch (Exception $e) {
                echo "   ✗ Users table error: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "   ✗ Database connection failed\n";
        }
    } else {
        echo "   ✗ Database config file missing\n";
    }
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. API Endpoint Test
echo "5. API Endpoint Analysis:\n";

// Check what the login form is trying to call
if (file_exists('views/auth/login.php')) {
    $loginContent = file_get_contents('views/auth/login.php');
    if (preg_match('/fetch\s*\(\s*["\']([^"\']+)["\']/', $loginContent, $matches)) {
        $apiUrl = $matches[1];
        echo "   Frontend calls: $apiUrl\n";
        
        // Construct full URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        if (strpos($apiUrl, 'http') === 0) {
            $fullApiUrl = $apiUrl;
        } elseif ($apiUrl[0] === '/') {
            $fullApiUrl = $protocol . '://' . $host . $apiUrl;
        } else {
            $fullApiUrl = $protocol . '://' . $host . $webPath . '/' . $apiUrl;
        }
        
        echo "   Full API URL: $fullApiUrl\n";
        
        // Test if this URL is accessible
        $testUrl = str_replace('/auth/login', '', $fullApiUrl);
        echo "   Testing base API: $testUrl\n";
        
    } else {
        echo "   Could not find API URL in login form\n";
    }
} else {
    echo "   Login form not found\n";
}
echo "\n";

// 6. PHP Configuration
echo "6. PHP Configuration:\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'openssl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ $ext extension loaded\n";
    } else {
        echo "   ✗ $ext extension missing\n";
    }
}

echo "   Error Reporting: " . (error_reporting() ? 'Enabled' : 'Disabled') . "\n";
echo "   Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
echo "   Allow URL fopen: " . (ini_get('allow_url_fopen') ? 'Yes' : 'No') . "\n";
echo "\n";

// 7. Permissions Check
echo "7. File Permissions:\n";
$dirsToCheck = ['config', 'logs', 'temp', 'cache', 'sessions', 'uploads'];
foreach ($dirsToCheck as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "   ✓ $dir (writable)\n";
        } else {
            echo "   ✗ $dir (not writable)\n";
        }
    } else {
        echo "   ? $dir (doesn't exist)\n";
    }
}
echo "\n";

// 8. Suggested Fixes
echo "8. Common Live Hosting Issues & Fixes:\n";
echo "   A. Wrong API URL paths:\n";
echo "      - Check if frontend calls correct URL\n";
echo "      - Verify web path in URLs\n";
echo "      - Ensure HTTPS/HTTP protocol matches\n\n";

echo "   B. Database connectivity:\n";
echo "      - Update database credentials in config\n";
echo "      - Check if MySQL/database server is running\n";
echo "      - Verify database exists and has proper tables\n\n";

echo "   C. File permissions:\n";
echo "      - Ensure web server can read/write files\n";
echo "      - Check directory permissions (755 recommended)\n";
echo "      - Verify ownership is correct\n\n";

echo "   D. PHP configuration:\n";
echo "      - Enable required PHP extensions\n";
echo "      - Check PHP error logs\n";
echo "      - Ensure error reporting is enabled for debugging\n\n";

echo "   E. CORS/Security headers:\n";
echo "      - Check if hosting provider blocks certain headers\n";
echo "      - Verify mod_rewrite is enabled\n";
echo "      - Check for firewall/security restrictions\n\n";

echo "9. Next Steps:\n";
echo "   1. Fix any ✗ items shown above\n";
echo "   2. Test the specific API URL shown in section 5\n";
echo "   3. Check your hosting provider's error logs\n";
echo "   4. Use the simple API test endpoint\n";
echo "   5. Verify the frontend URL matches the actual API path\n\n";

echo "Run this diagnostic on your live hosting to identify the specific issue.\n";
?>