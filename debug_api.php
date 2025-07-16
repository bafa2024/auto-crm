<?php
// debug_api.php - Debug API issues

echo "AutoDial Pro CRM - API Debug\n";
echo "============================\n\n";

// Check if we're running from command line or web
if (php_sapi_name() === 'cli') {
    echo "Running from command line\n";
} else {
    echo "Running from web server\n";
    echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "HTTP Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
}

echo "\n=== Environment Check ===\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension loaded\n";
    } else {
        echo "❌ $ext extension missing\n";
    }
}

// Check if .htaccess is being processed
echo "\n=== .htaccess Check ===\n";
if (isset($_SERVER['REDIRECT_STATUS'])) {
    echo "✓ .htaccess is being processed (REDIRECT_STATUS: " . $_SERVER['REDIRECT_STATUS'] . ")\n";
} else {
    echo "⚠️  .htaccess might not be processed (no REDIRECT_STATUS)\n";
}

// Check if mod_rewrite is enabled
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "✓ mod_rewrite is enabled\n";
    } else {
        echo "❌ mod_rewrite is not enabled\n";
    }
} else {
    echo "⚠️  Cannot check if mod_rewrite is enabled\n";
}

// Check file permissions
echo "\n=== File Permissions ===\n";
$files = ['index.php', '.htaccess', 'autoload.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
        if (is_readable($file)) {
            echo "  - Readable\n";
        } else {
            echo "  - Not readable\n";
        }
    } else {
        echo "❌ $file missing\n";
    }
}

// Check directories
echo "\n=== Directory Check ===\n";
$dirs = ['controllers', 'models', 'router', 'config'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir directory exists\n";
        if (is_readable($dir)) {
            echo "  - Readable\n";
        } else {
            echo "  - Not readable\n";
        }
    } else {
        echo "❌ $dir directory missing\n";
    }
}

// Test autoloader
echo "\n=== Autoloader Test ===\n";
if (file_exists('autoload.php')) {
    require_once 'autoload.php';
    echo "✓ autoload.php loaded\n";
    
    // Test if classes can be loaded
    try {
        if (class_exists('AuthController')) {
            echo "✓ AuthController class can be loaded\n";
        } else {
            echo "❌ AuthController class cannot be loaded\n";
        }
    } catch (Exception $e) {
        echo "❌ Error loading AuthController: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ autoload.php not found\n";
}

// Test database connection
echo "\n=== Database Test ===\n";
if (file_exists('config/database.php')) {
    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            echo "✓ Database connection successful\n";
        } else {
            echo "❌ Database connection failed\n";
        }
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ config/database.php not found\n";
}

// Test router
echo "\n=== Router Test ===\n";
if (file_exists('router/Router.php')) {
    try {
        require_once 'router/Router.php';
        $router = new Router\Router();
        echo "✓ Router class loaded successfully\n";
    } catch (Exception $e) {
        echo "❌ Router error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ router/Router.php not found\n";
}

// Test API endpoint directly
echo "\n=== Direct API Test ===\n";
$testData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'company_name' => 'Test Company',
    'email' => 'test@example.com',
    'password' => 'testpassword123'
];

// Simulate API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/register';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Capture output
ob_start();

try {
    // Load required files
    require_once 'autoload.php';
    require_once 'config/database.php';
    require_once 'router/Router.php';
    
    // Initialize router
    $router = new Router\Router();
    
    // Add the register route
    $database = new Database();
    $db = $database->getConnection();
    
    $router->post('/api/auth/register', function($request) use ($db) {
        $controller = new AuthController($db);
        return $controller->register($request);
    });
    
    // Simulate request
    $request = new Router\Request();
    $request->body = $testData;
    
    // Dispatch
    $router->dispatch();
    
} catch (Exception $e) {
    echo "❌ API simulation error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo "API Response: " . $output . "\n";

echo "\n✅ Debug completed!\n"; 