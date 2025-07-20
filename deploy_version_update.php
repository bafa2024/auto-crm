<?php
/**
 * Deploy Version Update Script
 * This script updates the version and deployment information when new code is deployed
 */

echo "=== Deploy Version Update ===\n\n";

try {
    require_once 'version.php';
    
    // Step 1: Update deployment date
    echo "1. Updating deployment date...\n";
    $deploymentDate = VersionManager::updateDeploymentDate();
    echo "✅ Deployment date updated: $deploymentDate\n";
    
    // Step 2: Get current version info
    echo "\n2. Current version information:\n";
    $versionInfo = VersionManager::getVersionInfo();
    
    echo "- Version: {$versionInfo['version']}\n";
    echo "- Build Date: {$versionInfo['build_date']}\n";
    echo "- Deployment Date: {$versionInfo['deployment_date']}\n";
    echo "- Environment: {$versionInfo['environment']}\n";
    echo "- Commit Hash: " . ($versionInfo['commit_hash'] ?: 'Not available') . "\n";
    
    // Step 3: Create deployment log
    echo "\n3. Creating deployment log...\n";
    $deploymentLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => $versionInfo['version'],
        'environment' => $versionInfo['environment'],
        'commit_hash' => $versionInfo['commit_hash'],
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $logFile = __DIR__ . '/deployment_log.json';
    $existingLogs = [];
    
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    
    // Keep only last 10 deployments
    $existingLogs = array_slice($existingLogs, -9);
    $existingLogs[] = $deploymentLog;
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
    echo "✅ Deployment log updated\n";
    
    // Step 4: Create deployment status file
    echo "\n4. Creating deployment status file...\n";
    $statusFile = __DIR__ . '/deployment_status.json';
    $status = [
        'last_deployment' => $deploymentDate,
        'version' => $versionInfo['version'],
        'environment' => $versionInfo['environment'],
        'status' => 'success',
        'timestamp' => time()
    ];
    
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    echo "✅ Deployment status file created\n";
    
    // Step 5: Test version display
    echo "\n5. Testing version display...\n";
    $versionBadge = VersionManager::getVersionBadge();
    echo "Version badge HTML generated successfully\n";
    
    // Step 6: Create API endpoint for version info
    echo "\n6. Creating version API endpoint...\n";
    $apiFile = __DIR__ . '/api/version.php';
    $apiContent = '<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../version.php";

$versionInfo = VersionManager::getApiVersionInfo();
echo json_encode($versionInfo, JSON_PRETTY_PRINT);
?>';
    
    file_put_contents($apiFile, $apiContent);
    echo "✅ Version API endpoint created\n";
    
    // Step 7: Create deployment verification page
    echo "\n7. Creating deployment verification page...\n";
    $verificationFile = __DIR__ . '/deployment_verification.php';
    $verificationContent = '<?php
require_once "version.php";

$versionInfo = VersionManager::getVersionInfo();
$deploymentLogFile = __DIR__ . "/deployment_log.json";
$deploymentLogs = [];

if (file_exists($deploymentLogFile)) {
    $deploymentLogs = json_decode(file_get_contents($deploymentLogFile), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Verification - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-check-circle"></i> Deployment Verification
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Current Version</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Version:</strong></td>
                                        <td><span class="badge bg-success">v<?php echo $versionInfo["version"]; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Environment:</strong></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($versionInfo["environment"]); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Build Date:</strong></td>
                                        <td><?php echo $versionInfo["build_date"]; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Deployment Date:</strong></td>
                                        <td><?php echo $versionInfo["deployment_date"]; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Commit Hash:</strong></td>
                                        <td><code><?php echo $versionInfo["commit_hash"] ?: "Not available"; ?></code></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Deployment Status</h5>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> 
                                    <strong>Deployment Successful!</strong><br>
                                    The application has been successfully updated.
                                </div>
                                
                                <h6>Quick Links:</h6>
                                <div class="d-grid gap-2">
                                    <a href="/dashboard" class="btn btn-primary btn-sm">
                                        <i class="bi bi-speedometer2"></i> Go to Dashboard
                                    </a>
                                    <a href="/api/version.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-code-slash"></i> Version API
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($deploymentLogs)): ?>
                        <hr>
                        <h5>Recent Deployments</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Version</th>
                                        <th>Environment</th>
                                        <th>Commit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($deploymentLogs) as $log): ?>
                                    <tr>
                                        <td><?php echo $log["timestamp"]; ?></td>
                                        <td><span class="badge bg-secondary">v<?php echo $log["version"]; ?></span></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($log["environment"]); ?></span></td>
                                        <td><code><?php echo $log["commit_hash"] ?: "N/A"; ?></code></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    
    file_put_contents($verificationFile, $verificationContent);
    echo "✅ Deployment verification page created\n";
    
    // Final summary
    echo "\n=== Deployment Update Complete ===\n";
    echo "✅ Deployment date updated\n";
    echo "✅ Version information logged\n";
    echo "✅ Deployment log created\n";
    echo "✅ Status file updated\n";
    echo "✅ API endpoint created\n";
    echo "✅ Verification page created\n";
    
    echo "\n🎉 Version update completed successfully!\n";
    echo "Current version: v{$versionInfo['version']}\n";
    echo "Deployment date: $deploymentDate\n";
    echo "Environment: {$versionInfo['environment']}\n";
    
    echo "\n📝 Next Steps:\n";
    echo "1. Visit the application to see the version badge in the header\n";
    echo "2. Check /deployment_verification.php for deployment status\n";
    echo "3. Use /api/version.php for programmatic version info\n";
    echo "4. The version badge will show: v{$versionInfo['version']} {$versionInfo['environment']}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 