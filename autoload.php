<?php
// autoload.php - Simple autoloader for the application

spl_autoload_register(function ($class) {
    // Remove namespace if present
    $class = str_replace("\\", "/", $class);
    $class = basename($class);
    
    // Define search paths
    $paths = [
        __DIR__ . "/controllers/" . $class . ".php",
        __DIR__ . "/models/" . $class . ".php",
        __DIR__ . "/services/" . $class . ".php",
        __DIR__ . "/router/" . $class . ".php",
    ];
    
    // Try to load from each path
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});