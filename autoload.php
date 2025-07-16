<?php
// autoload.php - Simple autoloader for the application

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = '';
    
    // Handle Router namespace
    if (strpos($class, 'Router\\') === 0) {
        $class = str_replace('Router\\', '', $class);
        $file = __DIR__ . '/router/' . $class . '.php';
    }
    // Handle other classes
    else {
        // Check in controllers directory
        $file = __DIR__ . '/controllers/' . $class . '.php';
        
        // If not found, check in models directory
        if (!file_exists($file)) {
            $file = __DIR__ . '/models/' . $class . '.php';
        }
        
        // If not found, check in services directory
        if (!file_exists($file)) {
            $file = __DIR__ . '/services/' . $class . '.php';
        }
    }
    
    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
}); 