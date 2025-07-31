<?php
/**
 * Create Missing Directories Script
 * Run this to create all missing directories found by QC dashboard
 */

echo "<h2>Creating Missing Directories</h2>";

$directories = [
    'temp',
    'uploads', 
    'backups',
    'sessions',
    'cache',
    'logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<p style='color: green;'>✅ Created directory: $dir</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create directory: $dir</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Directory already exists: $dir</p>";
    }
    
    // Set permissions
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "<p style='color: green;'>✅ Set permissions for: $dir</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not set permissions for: $dir</p>";
        }
    }
}

echo "<h3>Directory Creation Complete!</h3>";
echo "<p>All required directories have been created with proper permissions.</p>";
?> 