<?php
/**
 * Deployment Verification Page
 * This page shows deployment information and verifies the deployment was successful
 */

require_once 'config/config.php';
require_once 'version.php';

// Update deployment date when this page is accessed
VersionManager::updateDeploymentDate();

// Get version information
$versionInfo = VersionManager::getVersionInfo();

// Check if version-info.json exists
$versionJsonFile = __DIR__ . '/version-info.json';
$jsonInfo = file_exists($versionJsonFile) ? json_decode(file_get_contents($versionJsonFile), true) : null;

// Perform deployment checks
$checks = [];

// Check 1: Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $checks['database'] = ['status' => 'success', 'message' => 'Database connected successfully'];
} catch (Exception $e) {
    $checks['database'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
}

// Check 2: Write permissions
$uploadDir = __DIR__ . '/uploads';
$checks['write_permissions'] = is_writable($uploadDir) 
    ? ['status' => 'success', 'message' => 'Upload directory is writable']
    : ['status' => 'warning', 'message' => 'Upload directory is not writable'];

// Check 3: PHP Version
$phpVersion = phpversion();
$checks['php_version'] = version_compare($phpVersion, '7.4.0', '>=')
    ? ['status' => 'success', 'message' => 'PHP ' . $phpVersion . ' (OK)']
    : ['status' => 'error', 'message' => 'PHP ' . $phpVersion . ' (Requires 7.4+)'];

// Check 4: Required PHP Extensions
$requiredExtensions = ['mysqli', 'pdo', 'json', 'mbstring', 'openssl'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}
$checks['php_extensions'] = empty($missingExtensions)
    ? ['status' => 'success', 'message' => 'All required extensions loaded']
    : ['status' => 'error', 'message' => 'Missing extensions: ' . implode(', ', $missingExtensions)];

// Check 5: Git repository status
$gitDir = __DIR__ . '/.git';
if (is_dir($gitDir)) {
    $checks['git_status'] = ['status' => 'success', 'message' => 'Git repository found'];
} else {
    $checks['git_status'] = ['status' => 'info', 'message' => 'Not a git repository'];
}

// Check 6: Last commit info
$lastCommit = VersionManager::getCommitHash();
$checks['last_commit'] = $lastCommit 
    ? ['status' => 'success', 'message' => 'Commit: ' . $lastCommit]
    : ['status' => 'info', 'message' => 'No commit information available'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Verification - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .deployment-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .status-badge {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .status-success { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        .status-info { background-color: #17a2b8; }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .check-item {
            padding: 10px;
            border-left: 3px solid;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        .check-success { border-color: #28a745; }
        .check-warning { border-color: #ffc107; }
        .check-error { border-color: #dc3545; }
        .check-info { border-color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="deployment-card">
            <h1 class="mb-4">
                <i class="bi bi-rocket-takeoff"></i> 
                ACRM Deployment Verification
            </h1>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                This page verifies that your ACRM deployment was successful and all systems are operational.
            </div>

            <h3 class="mt-4 mb-3">Version Information</h3>
            <div class="info-grid">
                <div class="info-label">Version:</div>
                <div><?php echo $versionInfo['version']; ?></div>
                
                <div class="info-label">Build Date:</div>
                <div><?php echo $versionInfo['build_date']; ?></div>
                
                <div class="info-label">Deployment Date:</div>
                <div><?php echo $versionInfo['deployment_date']; ?></div>
                
                <div class="info-label">Environment:</div>
                <div>
                    <span class="badge bg-<?php echo $versionInfo['environment'] === 'live' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($versionInfo['environment']); ?>
                    </span>
                </div>
                
                <div class="info-label">Timezone:</div>
                <div><?php echo APP_TIMEZONE; ?></div>
                
                <div class="info-label">Current Time:</div>
                <div><?php echo date('Y-m-d H:i:s T'); ?></div>
                
                <div class="info-label">Server:</div>
                <div><?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?></div>
            </div>

            <h3 class="mt-4 mb-3">System Checks</h3>
            <?php foreach ($checks as $checkName => $check): ?>
                <div class="check-item check-<?php echo $check['status']; ?>">
                    <div class="d-flex align-items-center">
                        <span class="status-badge status-<?php echo $check['status']; ?>"></span>
                        <strong><?php echo ucwords(str_replace('_', ' ', $checkName)); ?>:</strong>
                        <span class="ms-2"><?php echo $check['message']; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <h3 class="mt-4 mb-3">Deployment Actions</h3>
            <div class="d-flex gap-2">
                <a href="/" class="btn btn-primary">
                    <i class="bi bi-house"></i> Go to Homepage
                </a>
                <a href="/dashboard" class="btn btn-secondary">
                    <i class="bi bi-speedometer2"></i> Go to Dashboard
                </a>
                <button onclick="location.reload()" class="btn btn-info">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Check
                </button>
            </div>

            <?php if ($jsonInfo): ?>
            <h3 class="mt-4 mb-3">Version JSON Info</h3>
            <pre class="bg-light p-3 rounded"><?php echo json_encode($jsonInfo, JSON_PRETTY_PRINT); ?></pre>
            <?php endif; ?>
        </div>

        <div class="text-center text-muted mt-4">
            <small>
                ACRM &copy; <?php echo date('Y'); ?> | 
                Deployment verification page | 
                <a href="#" onclick="if(confirm('Clear deployment cache?')) { fetch('version.php').then(() => location.reload()); }">Clear Cache</a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>