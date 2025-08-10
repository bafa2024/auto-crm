<?php
/**
 * Deployment Script
 * Run this script after deployment to update version information
 */

require_once __DIR__ . '/../version.php';

echo "ACRM Deployment Script\n";
echo "======================\n\n";

// Update deployment date
$deploymentDate = VersionManager::updateDeploymentDate();
echo "✓ Deployment date updated: {$deploymentDate}\n";

// Get version info
$versionInfo = VersionManager::getVersionInfo();
echo "✓ Version: {$versionInfo['version']}\n";
echo "✓ Environment: {$versionInfo['environment']}\n";
echo "✓ Timezone: " . APP_TIMEZONE . "\n";

// Create version-info.json
$jsonFile = __DIR__ . '/../version-info.json';
if (file_exists($jsonFile)) {
    echo "✓ version-info.json created successfully\n";
} else {
    echo "✗ Failed to create version-info.json\n";
}

// Clear any caches
$cacheFiles = [
    __DIR__ . '/../cache/*.cache',
    __DIR__ . '/../temp/*.tmp'
];

foreach ($cacheFiles as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "✓ Cleared " . count($files) . " cache files\n";
    }
}

echo "\nDeployment complete!\n";
echo "Visit /deployment-info.php to verify deployment status.\n";
?>