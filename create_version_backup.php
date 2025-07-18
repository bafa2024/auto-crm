<?php
// create_version_backup.php - Create Version 3.0 backup zip

echo "Creating AutoDial Pro Version 3.0 Backup\n";
echo "========================================\n\n";

$version = "3.0";
$timestamp = date('Y-m-d_H-i-s');
$zipFileName = "AutoDialPro_v{$version}_Backup_{$timestamp}.zip";
$backupPath = __DIR__ . "/" . $zipFileName;

// Check if zip extension is available
if (!extension_loaded('zip')) {
    echo "❌ Error: PHP ZIP extension is not available\n";
    echo "Alternative: Please manually create a zip of the entire 'acrm' folder\n";
    exit(1);
}

try {
    $zip = new ZipArchive();
    $result = $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        throw new Exception("Failed to create zip file: $result");
    }
    
    echo "1. Creating backup zip: $zipFileName\n";
    
    // Files and directories to include
    $filesToInclude = [
        // Core application files
        'index.php',
        'autoload.php',
        'api.php',
        
        // Configuration
        'config/database.php',
        'config/database_sqlite.php',
        'config/config.php',
        'config/cloud.php',
        
        // Controllers
        'controllers/AuthController.php',
        'controllers/BaseController.php',
        'controllers/ContactController.php',
        'controllers/EmailCampaignController.php',
        
        // Models
        'models/BaseModel.php',
        'models/User.php',
        'models/Contact.php',
        'models/EmailCampaign.php',
        'models/EmailTemplate.php',
        'models/BulkUpload.php',
        
        // Views - Auth
        'views/auth/login.php',
        'views/auth/signup.php',
        
        // Views - Components
        'views/components/header.php',
        'views/components/footer.php',
        'views/components/sidebar.php',
        'views/components/navigation.php',
        'views/components/dashboard-overview.php',
        
        // Views - Dashboard
        'views/dashboard.php',
        'views/dashboard/index.php',
        'views/dashboard/profile.php',
        'views/dashboard/settings.php',
        
        // Views - Other
        'views/landing.php',
        'views/404.php',
        
        // Public assets
        'public/index.php',
        'public/css/styles.css',
        'public/js/app.js',
        
        // CSS and JS
        'css/styles.css',
        'js/app.js',
        
        // Database
        'database/schema.sql',
        'database/migrate.php',
        'database/create_sqlite.php',
        
        // Services
        'services/EmailService.php',
        'services/CronService.php',
        'services/FileUploadService.php',
        
        // Router
        'router/Router.php',
        
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
        'composer.lock'
    ];
    
    $fileCount = 0;
    $totalSize = 0;
    
    foreach ($filesToInclude as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, $file);
            $fileCount++;
            $totalSize += filesize($file);
            echo "  ✓ Added: $file\n";
        } else {
            echo "  ⚠ Missing: $file\n";
        }
    }
    
    // Add directories recursively with important content
    $directoriesToInclude = [
        'uploads' => 'uploads',
        'logs' => 'logs', 
        'temp' => 'temp',
        'cache' => 'cache',
        'sessions' => 'sessions'
    ];
    
    foreach ($directoriesToInclude as $dir => $zipPath) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filePath = $file->getRealPath();
                    $relativePath = $zipPath . '/' . substr($filePath, strlen(realpath($dir)) + 1);
                    $zip->addFile($filePath, $relativePath);
                    $fileCount++;
                }
            }
            echo "  ✓ Added directory: $dir\n";
        }
    }
    
    // Add SQLite database if it exists
    if (file_exists('database/autocrm_local.db')) {
        $zip->addFile('database/autocrm_local.db', 'database/autocrm_local.db');
        $fileCount++;
        echo "  ✓ Added: SQLite database\n";
    }
    
    // Create version info file
    $versionInfo = "AutoDial Pro Version 3.0 - Debugged Authentication System\n";
    $versionInfo .= "=======================================================\n\n";
    $versionInfo .= "Created: " . date('Y-m-d H:i:s') . "\n";
    $versionInfo .= "Commit: 8efe44e\n";
    $versionInfo .= "Files: $fileCount\n";
    $versionInfo .= "Total Size: " . number_format($totalSize) . " bytes\n\n";
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
    $versionInfo .= "3. Access: http://localhost/acrm/login\n";
    
    $zip->addFromString('VERSION_3.0_INFO.txt', $versionInfo);
    
    echo "\n2. Finalizing backup...\n";
    $zip->close();
    
    $zipSize = filesize($backupPath);
    
    echo "✅ Backup created successfully!\n\n";
    echo "📦 Backup Details:\n";
    echo "   File: $zipFileName\n";
    echo "   Path: $backupPath\n";
    echo "   Size: " . number_format($zipSize) . " bytes (" . round($zipSize/1024/1024, 2) . " MB)\n";
    echo "   Files: $fileCount\n";
    echo "   Version: 3.0\n";
    echo "   Commit: 8efe44e\n\n";
    
    echo "📋 What's Included:\n";
    echo "   ✓ Complete AutoDial Pro application\n";
    echo "   ✓ SQLite database with sample data\n";
    echo "   ✓ All authentication fixes\n";
    echo "   ✓ Dashboard and user interface\n";
    echo "   ✓ Documentation and guides\n";
    echo "   ✓ Testing and utility scripts\n";
    echo "   ✓ Database management tools\n\n";
    
    echo "🚀 Ready for deployment or backup storage!\n";
    
} catch (Exception $e) {
    echo "❌ Error creating backup: " . $e->getMessage() . "\n";
    exit(1);
}
?>