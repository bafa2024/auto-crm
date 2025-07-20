<?php
/**
 * Git Deploy Hook Script
 * This script should be run after each git push to update version information
 * Usage: php git_deploy_hook.php
 */

echo "=== Git Deploy Hook ===\n\n";

try {
    // Step 1: Check if we're in a git repository
    echo "1. Checking git repository...\n";
    if (!is_dir('.git')) {
        echo "❌ Not in a git repository\n";
        exit(1);
    }
    echo "✅ Git repository found\n";
    
    // Step 2: Get current git information
    echo "\n2. Getting git information...\n";
    
    // Get current commit hash
    $commitHash = trim(shell_exec('git rev-parse HEAD 2>/dev/null'));
    if ($commitHash) {
        $shortHash = substr($commitHash, 0, 8);
        echo "✅ Commit hash: $shortHash\n";
    } else {
        echo "⚠️ Could not get commit hash\n";
        $shortHash = '';
    }
    
    // Get current branch
    $branch = trim(shell_exec('git branch --show-current 2>/dev/null'));
    if ($branch) {
        echo "✅ Current branch: $branch\n";
    } else {
        echo "⚠️ Could not get current branch\n";
        $branch = 'unknown';
    }
    
    // Get last commit message
    $lastCommit = trim(shell_exec('git log -1 --pretty=format:"%s" 2>/dev/null'));
    if ($lastCommit) {
        echo "✅ Last commit: $lastCommit\n";
    } else {
        echo "⚠️ Could not get last commit message\n";
        $lastCommit = 'Unknown commit';
    }
    
    // Step 3: Update version information
    echo "\n3. Updating version information...\n";
    require_once 'version.php';
    
    // Update deployment date
    $deploymentDate = VersionManager::updateDeploymentDate();
    echo "✅ Deployment date updated: $deploymentDate\n";
    
    // Step 4: Create deployment record
    echo "\n4. Creating deployment record...\n";
    $deploymentRecord = [
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => VersionManager::getVersion(),
        'environment' => VersionManager::getEnvironment(),
        'commit_hash' => $shortHash,
        'branch' => $branch,
        'commit_message' => $lastCommit,
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'deployment_type' => 'git_push'
    ];
    
    // Save to deployment log
    $logFile = __DIR__ . '/deployment_log.json';
    $existingLogs = [];
    
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    
    // Keep only last 20 deployments
    $existingLogs = array_slice($existingLogs, -19);
    $existingLogs[] = $deploymentRecord;
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
    echo "✅ Deployment log updated\n";
    
    // Step 5: Update deployment status
    echo "\n5. Updating deployment status...\n";
    $statusFile = __DIR__ . '/deployment_status.json';
    $status = [
        'last_deployment' => $deploymentDate,
        'version' => VersionManager::getVersion(),
        'environment' => VersionManager::getEnvironment(),
        'commit_hash' => $shortHash,
        'branch' => $branch,
        'status' => 'success',
        'timestamp' => time(),
        'deployment_type' => 'git_push'
    ];
    
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    echo "✅ Deployment status updated\n";
    
    // Step 6: Create deployment notification
    echo "\n6. Creating deployment notification...\n";
    $notificationFile = __DIR__ . '/deployment_notification.txt';
    $notification = "=== DEPLOYMENT NOTIFICATION ===\n";
    $notification .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $notification .= "Version: " . VersionManager::getVersion() . "\n";
    $notification .= "Environment: " . VersionManager::getEnvironment() . "\n";
    $notification .= "Commit: $shortHash\n";
    $notification .= "Branch: $branch\n";
    $notification .= "Message: $lastCommit\n";
    $notification .= "Status: SUCCESS\n";
    $notification .= "==============================\n";
    
    file_put_contents($notificationFile, $notification);
    echo "✅ Deployment notification created\n";
    
    // Step 7: Test version display
    echo "\n7. Testing version display...\n";
    $versionBadge = VersionManager::getVersionBadge();
    echo "✅ Version badge generated successfully\n";
    
    // Step 8: Create deployment summary
    echo "\n8. Creating deployment summary...\n";
    $summaryFile = __DIR__ . '/deployment_summary.html';
    $summaryContent = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Summary - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        .deployment-card { transition: transform 0.2s; }
        .deployment-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-rocket-takeoff"></i> Deployment Summary
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Deployment Details</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Version:</strong></td>
                                        <td><span class="badge bg-success">v' . VersionManager::getVersion() . '</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Environment:</strong></td>
                                        <td><span class="badge bg-info">' . ucfirst(VersionManager::getEnvironment()) . '</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Deployment Date:</strong></td>
                                        <td>' . $deploymentDate . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Commit Hash:</strong></td>
                                        <td><code>' . $shortHash . '</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Branch:</strong></td>
                                        <td><span class="badge bg-secondary">' . $branch . '</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span class="badge bg-success">Success</span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Commit Information</h5>
                                <div class="alert alert-info">
                                    <strong>Last Commit Message:</strong><br>
                                    ' . htmlspecialchars($lastCommit) . '
                                </div>
                                
                                <h6>Quick Actions:</h6>
                                <div class="d-grid gap-2">
                                    <a href="/dashboard" class="btn btn-primary btn-sm">
                                        <i class="bi bi-speedometer2"></i> Go to Dashboard
                                    </a>
                                    <a href="/deployment_verification.php" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-check-circle"></i> Verify Deployment
                                    </a>
                                    <a href="/api/version.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-code-slash"></i> Version API
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="text-center">
                            <h6>Deployment Completed Successfully!</h6>
                            <p class="text-muted">The application has been updated and is ready for use.</p>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 
                                <strong>Version ' . VersionManager::getVersion() . ' is now live!</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    
    file_put_contents($summaryFile, $summaryContent);
    echo "✅ Deployment summary created\n";
    
    // Final summary
    echo "\n=== Git Deploy Hook Complete ===\n";
    echo "✅ Git information retrieved\n";
    echo "✅ Version information updated\n";
    echo "✅ Deployment record created\n";
    echo "✅ Status files updated\n";
    echo "✅ Notification created\n";
    echo "✅ Summary page created\n";
    
    echo "\n🎉 Deployment hook completed successfully!\n";
    echo "Version: v" . VersionManager::getVersion() . "\n";
    echo "Environment: " . VersionManager::getEnvironment() . "\n";
    echo "Commit: $shortHash\n";
    echo "Branch: $branch\n";
    echo "Deployment Date: $deploymentDate\n";
    
    echo "\n📝 Deployment Files Created:\n";
    echo "- deployment_log.json (deployment history)\n";
    echo "- deployment_status.json (current status)\n";
    echo "- deployment_notification.txt (deployment notification)\n";
    echo "- deployment_summary.html (deployment summary page)\n";
    echo "- deployment_verification.php (verification page)\n";
    echo "- api/version.php (version API endpoint)\n";
    
    echo "\n🔗 Quick Links:\n";
    echo "- Deployment Summary: /deployment_summary.html\n";
    echo "- Deployment Verification: /deployment_verification.php\n";
    echo "- Version API: /api/version.php\n";
    echo "- Application: /dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 