<?php
// create_backup_tar.php - Create Version 3.0 backup using tar

echo "Creating AutoDial Pro Version 3.0 Backup (TAR)\n";
echo "===============================================\n\n";

$version = "3.0";
$timestamp = date('Y-m-d_H-i-s');
$backupFileName = "AutoDialPro_v{$version}_Backup_{$timestamp}.tar.gz";
$backupPath = __DIR__ . "/" . $backupFileName;

echo "1. Creating backup archive: $backupFileName\n";

// Create list of files to include
$filesToBackup = [
    // Core files
    'index.php',
    'autoload.php',
    'api.php',
    
    // Directories (will be added recursively)
    'config/',
    'controllers/',
    'models/',
    'views/',
    'public/',
    'css/',
    'js/',
    'database/',
    'services/',
    'router/',
    
    // Version 3.0 specific files
    'database_manager.php',
    'switch_to_sqlite.php',
    'switch_to_mysql.php',
    'create_admin.php',
    'fix_auth_issues.php',
    'api_test.php',
    
    // Documentation
    'AUTHENTICATION_FIXED.md',
    'NETWORK_ERROR_FIXED.md',
    'QUICK_START.md',
    'USER_ACCOUNTS_GUIDE.md',
    'RELEASE_v3.0.md',
    'SETUP.md',
    
    // Package files
    'composer.json',
];

// Create version info file
$versionInfo = "AutoDial Pro Version 3.0 - Debugged Authentication System\n";
$versionInfo .= "=======================================================\n\n";
$versionInfo .= "Created: " . date('Y-m-d H:i:s') . "\n";
$versionInfo .= "Commit: 8efe44e\n";
$versionInfo .= "Version: 3.0\n\n";
$versionInfo .= "Major Features:\n";
$versionInfo .= "- Fixed authentication system and network errors\n";
$versionInfo .= "- SQLite database integration for local testing\n";
$versionInfo .= "- Complete user management (signup/login)\n";
$versionInfo .= "- AutoDial Pro dashboard with live data\n";
$versionInfo .= "- Mobile-responsive design\n";
$versionInfo .= "- Comprehensive documentation and testing tools\n\n";
$versionInfo .= "Login Credentials:\n";
$versionInfo .= "- Admin: admin@autocrm.com / admin123\n";
$versionInfo .= "- Test: test@autocrm.com / test123\n\n";
$versionInfo .= "Quick Start:\n";
$versionInfo .= "1. Extract to web server directory\n";
$versionInfo .= "2. Run: php switch_to_sqlite.php\n";
$versionInfo .= "3. Access: http://localhost/acrm/login\n\n";
$versionInfo .= "Installation:\n";
$versionInfo .= "tar -xzf " . $backupFileName . "\n";

file_put_contents('VERSION_3.0_INFO.txt', $versionInfo);

// Build tar command
$excludePatterns = [
    '--exclude=.git',
    '--exclude=.claude',
    '--exclude=vendor',
    '--exclude=node_modules',
    '--exclude=*.log',
    '--exclude=sessions/*',
    '--exclude=temp/*',
    '--exclude=cache/*',
    '--exclude=test_*.php',
    '--exclude=debug_*.php',
    '--exclude=show_all_users.php',
    '--exclude=simple_test.php'
];

// Create file list
$fileList = '';
foreach ($filesToBackup as $file) {
    if (file_exists($file)) {
        $fileList .= "$file ";
    }
}

$fileList .= 'VERSION_3.0_INFO.txt ';

// If SQLite database exists, include it
if (file_exists('database/autocrm_local.db')) {
    $fileList .= 'database/autocrm_local.db ';
}

$tarCommand = "tar -czf \"$backupFileName\" " . implode(' ', $excludePatterns) . " $fileList";

echo "2. Running tar command...\n";
echo "Command: $tarCommand\n\n";

// Execute tar command
$output = [];
$returnVar = 0;
exec($tarCommand . " 2>&1", $output, $returnVar);

if ($returnVar === 0) {
    $backupSize = file_exists($backupFileName) ? filesize($backupFileName) : 0;
    
    echo "✅ Backup created successfully!\n\n";
    echo "📦 Backup Details:\n";
    echo "   File: $backupFileName\n";
    echo "   Path: " . realpath($backupFileName) . "\n";
    echo "   Size: " . number_format($backupSize) . " bytes (" . round($backupSize/1024/1024, 2) . " MB)\n";
    echo "   Version: 3.0\n";
    echo "   Commit: 8efe44e\n";
    echo "   Format: tar.gz (compressed)\n\n";
    
    echo "📋 What's Included:\n";
    echo "   ✓ Complete AutoDial Pro application\n";
    echo "   ✓ SQLite database with sample data\n";
    echo "   ✓ All authentication fixes\n";
    echo "   ✓ Dashboard and user interface\n";
    echo "   ✓ Documentation and guides\n";
    echo "   ✓ Database management tools\n\n";
    
    echo "📥 To extract:\n";
    echo "   tar -xzf $backupFileName\n\n";
    
    echo "🚀 Version 3.0 backup ready!\n";
    
} else {
    echo "❌ Error creating backup with tar\n";
    echo "Output: " . implode("\n", $output) . "\n";
    
    // Fallback: Create simple copy instructions
    echo "\n📋 Manual Backup Instructions:\n";
    echo "1. Copy the entire 'acrm' folder\n";
    echo "2. Rename it to: AutoDialPro_v3.0_Backup_$timestamp\n";
    echo "3. Compress it using Windows built-in compression or 7-Zip\n";
}

// Clean up temp file
unlink('VERSION_3.0_INFO.txt');
?>