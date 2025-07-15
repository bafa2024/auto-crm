<?php
// autoload.php - Custom autoloader that works without Composer

spl_autoload_register(function ($class) {
    // Define namespace to directory mappings
    $namespaces = [
        'Router\\' => __DIR__ . '/router/',
        'Controllers\\' => __DIR__ . '/controllers/',
        'Models\\' => __DIR__ . '/models/',
        'Services\\' => __DIR__ . '/services/',
        'Utils\\' => __DIR__ . '/utils/',
        'App\\' => __DIR__ . '/app/'
    ];
    
    // Check each namespace
    foreach ($namespaces as $namespace => $directory) {
        $len = strlen($namespace);
        if (strncmp($namespace, $class, $len) === 0) {
            // Get the relative class name
            $relativeClass = substr($class, $len);
            
            // Replace namespace separator with directory separator
            $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';
            
            // If the file exists, require it
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
    
    // Try without namespace (for backward compatibility)
    $directories = [
        __DIR__ . '/controllers/',
        __DIR__ . '/models/',
        __DIR__ . '/services/',
        __DIR__ . '/utils/',
        __DIR__ . '/router/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load configuration files
$configFiles = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config/cloud.php'
];

foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        require_once $configFile;
    }
}

// Helper function to check if Composer autoloader exists
function hasComposerAutoloader() {
    return file_exists(__DIR__ . '/vendor/autoload.php');
}

// If Composer autoloader exists, load it too
if (hasComposerAutoloader()) {
    require_once __DIR__ . '/vendor/autoload.php';
}