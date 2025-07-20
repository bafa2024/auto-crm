<?php
// create_v8_backup.php - Create version 8.0 backup with scheduled campaigns
// This script creates a comprehensive backup of the entire project

echo "=== Creating Version 8.0 Backup (Scheduled Campaigns) ===\n\n";

try {
    // Define backup details
    $version = "8.0";
    $backupName = "acrm_v{$version}_scheduled_campaigns";
    $backupDate = date('Y-m-d_H-i-s');
    $backupPath = "C:/xampp/htdocs/acrm/backups/";
    $backupFileName = "{$backupName}_{$backupDate}.zip";
    $fullBackupPath = $backupPath . $backupFileName;
    
    // Create backups directory if it doesn't exist
    if (!is_dir($backupPath)) {
        mkdir($backupPath, 0755, true);
        echo "✅ Created backups directory: $backupPath\n";
    }
    
    // Initialize ZipArchive
    $zip = new ZipArchive();
    $zipResult = $zip->open($fullBackupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($zipResult !== TRUE) {
        throw new Exception("Failed to create zip file: " . $zipResult);
    }
    
    echo "✅ Created zip file: $fullBackupPath\n";
    
    // Define files and directories to include
    $includePaths = [
        // Core application files
        'index.php',
        'autoload.php',
        'composer.json',
        'composer.lock',
        
        // Configuration
        'config/',
        
        // Controllers
        'controllers/',
        
        // Models
        'models/',
        
        // Services
        'services/',
        
        // Views
        'views/',
        
        // Public assets
        'public/',
        
        // Router
        'router/',
        
        // Database
        'database/',
        
        // CSS and JS
        'css/',
        'js/',
        
        // Cron jobs
        'cron/',
        
        // Uploads
        'uploads/',
        
        // Logs
        'logs/',
        
        // Sessions
        'sessions/',
        
        // Cache
        'cache/',
        
        // Components
        'components/',
        
        // API
        'api/',
        
        // Vendor (if exists)
        'vendor/',
        
        // Fix and verification scripts
        'fix_*.php',
        'verify_*.php',
        'create_v*.php',
        'setup_*.php',
        'deploy.php',
        'switch_to_*.php',
        
        // Documentation
        '*.md',
        '*.txt',
        
        // Database files
        '*.db',
        '*.sql',
        
        // Excel templates
        '*.xlsx',
        '*.xls',
        '*.csv'
    ];
    
    // Define files and directories to exclude
    $excludePaths = [
        '.git/',
        '.gitignore',
        'node_modules/',
        'temp/',
        'tmp/',
        '*.log',
        '*.tmp',
        '*.bak',
        '*.old',
        'Thumbs.db',
        '.DS_Store',
        '*.swp',
        '*.swo'
    ];
    
    // Function to check if path should be excluded
    function shouldExclude($path, $excludePaths) {
        foreach ($excludePaths as $exclude) {
            if (strpos($exclude, '/') !== false) {
                // Directory exclusion
                if (strpos($path, rtrim($exclude, '/')) === 0) {
                    return true;
                }
            } else {
                // File exclusion
                if (basename($path) === $exclude || fnmatch($exclude, basename($path))) {
                    return true;
                }
            }
        }
        return false;
    }
    
    // Function to add files to zip
    function addToZip($zip, $path, $basePath = '') {
        if (is_file($path)) {
            $relativePath = $basePath . basename($path);
            $zip->addFile($path, $relativePath);
            echo "  📄 Added file: $relativePath\n";
        } elseif (is_dir($path)) {
            $relativePath = $basePath . basename($path) . '/';
            $zip->addEmptyDir($relativePath);
            echo "  📁 Added directory: $relativePath\n";
            
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $fullPath = $path . '/' . $file;
                    addToZip($zip, $fullPath, $relativePath);
                }
            }
        }
    }
    
    // Add files to zip
    $totalFiles = 0;
    $totalSize = 0;
    
    echo "\n📦 Adding files to backup...\n";
    
    foreach ($includePaths as $includePath) {
        if (file_exists($includePath)) {
            if (is_dir($includePath)) {
                // Add directory
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($includePath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($files as $file) {
                    $filePath = $file->getRealPath();
                    $relativePath = str_replace('\\', '/', substr($filePath, strlen(getcwd()) + 1));
                    
                    // Check if file should be excluded
                    if (!shouldExclude($relativePath, $excludePaths)) {
                        $zip->addFile($filePath, $relativePath);
                        $totalFiles++;
                        $totalSize += filesize($filePath);
                    }
                }
                
                echo "  📁 Added directory: $includePath\n";
            } else {
                // Add single file
                if (!shouldExclude($includePath, $excludePaths)) {
                    $zip->addFile($includePath, $includePath);
                    $totalFiles++;
                    $totalSize += filesize($includePath);
                    echo "  📄 Added file: $includePath\n";
                }
            }
        } else {
            echo "  ⚠️ Path not found: $includePath\n";
        }
    }
    
    // Create backup info file
    $backupInfo = [
        'version' => $version,
        'backup_name' => $backupName,
        'backup_date' => $backupDate,
        'created_at' => date('Y-m-d H:i:s'),
        'total_files' => $totalFiles,
        'total_size' => $totalSize,
        'size_formatted' => formatBytes($totalSize),
        'features' => [
            'scheduled_campaigns' => true,
            'immediate_campaigns' => true,
            'recurring_campaigns' => true,
            'email_personalization' => true,
            'campaign_statistics' => true,
            'cron_job_processing' => true,
            'database_migration' => true,
            'contact_management' => true,
            'email_upload' => true,
            'user_authentication' => true,
            'dashboard' => true,
            'reports' => true
        ],
        'database_tables' => [
            'users',
            'email_campaigns',
            'campaign_sends',
            'email_recipients',
            'email_templates',
            'email_queue'
        ],
        'services' => [
            'ScheduledCampaignService',
            'EmailService',
            'EmailCampaignService',
            'CronService',
            'FileUploadService',
            'EmailUploadService'
        ],
        'cron_jobs' => [
            'process_scheduled_campaigns.php'
        ],
        'fix_scripts' => [
            'fix_scheduled_campaigns.php',
            'fix_contact_table_consistency.php',
            'fix_foreign_key_constraint_issue.php',
            'fix_session_issues.php'
        ],
        'verification_scripts' => [
            'verify_scheduled_campaigns.php',
            'verify_live_scheduled.php',
            'verify_contact_table_usage.php'
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_type' => 'mysql/sqlite',
            'auto_environment_detection' => true
        ]
    ];
    
    $backupInfoJson = json_encode($backupInfo, JSON_PRETTY_PRINT);
    $zip->addFromString('backup_info.json', $backupInfoJson);
    
    // Create README file
    $readme = "# ACRM Version {$version} Backup\n\n";
    $readme .= "**Backup Date:** {$backupDate}\n";
    $readme .= "**Total Files:** {$totalFiles}\n";
    $readme .= "**Total Size:** " . formatBytes($totalSize) . "\n\n";
    
    $readme .= "## 🚀 New Features in Version {$version}\n\n";
    $readme .= "### Scheduled Campaigns System\n";
    $readme .= "- ✅ **Immediate Campaigns**: Send emails right away\n";
    $readme .= "- ✅ **Scheduled Campaigns**: Send at specific date/time\n";
    $readme .= "- ✅ **Recurring Campaigns**: Daily, weekly, monthly\n";
    $readme .= "- ✅ **Email Personalization**: {first_name}, {name}, {email}, {company}\n";
    $readme .= "- ✅ **Campaign Statistics**: Sent, opened, clicked tracking\n";
    $readme .= "- ✅ **Automated Processing**: Cron job support\n";
    $readme .= "- ✅ **Status Management**: Draft, scheduled, sending, completed, failed\n\n";
    
    $readme .= "### Database Improvements\n";
    $readme .= "- ✅ **email_campaigns table**: Complete campaign management\n";
    $readme .= "- ✅ **campaign_sends table**: Individual email tracking\n";
    $readme .= "- ✅ **Foreign key constraints**: Data integrity\n";
    $readme .= "- ✅ **Auto-migration**: Seamless database updates\n\n";
    
    $readme .= "### Services & Processing\n";
    $readme .= "- ✅ **ScheduledCampaignService**: Core scheduling logic\n";
    $readme .= "- ✅ **Cron job processor**: Automated campaign execution\n";
    $readme .= "- ✅ **Email service integration**: Seamless email sending\n";
    $readme .= "- ✅ **Error handling**: Robust error recovery\n\n";
    
    $readme .= "## 📋 Installation Instructions\n\n";
    $readme .= "1. **Extract backup** to your web server directory\n";
    $readme .= "2. **Run fix script**: `fix_scheduled_campaigns.php`\n";
    $readme .= "3. **Set up cron job**: Every minute execution\n";
    $readme .= "4. **Test system**: `verify_scheduled_campaigns.php`\n";
    $readme .= "5. **Configure email**: Update email service settings\n\n";
    
    $readme .= "## 🔧 Cron Job Setup\n\n";
    $readme .= "Add this to your server's cron jobs:\n";
    $readme .= "```bash\n";
    $readme .= "*/1 * * * * php /path/to/cron/process_scheduled_campaigns.php\n";
    $readme .= "```\n\n";
    
    $readme .= "## 🧪 Testing\n\n";
    $readme .= "- **Fix script**: `fix_scheduled_campaigns.php`\n";
    $readme .= "- **Verification**: `verify_scheduled_campaigns.php`\n";
    $readme .= "- **Live testing**: `verify_live_scheduled.php`\n";
    $readme .= "- **Cron testing**: `cron/process_scheduled_campaigns.php`\n\n";
    
    $readme .= "## 📊 Database Tables\n\n";
    $readme .= "- `users`: User management\n";
    $readme .= "- `email_campaigns`: Campaign data and scheduling\n";
    $readme .= "- `campaign_sends`: Individual email tracking\n";
    $readme .= "- `email_recipients`: Contact management\n";
    $readme .= "- `email_templates`: Email templates\n";
    $readme .= "- `email_queue`: Email processing queue\n\n";
    
    $readme .= "## 🎯 Key Features\n\n";
    $readme .= "- **Multi-environment support**: Local (SQLite) and Live (MySQL)\n";
    $readme .= "- **Auto-detection**: Environment and database type\n";
    $readme .= "- **Comprehensive testing**: Multiple test scripts\n";
    $readme .= "- **Error recovery**: Automatic fix scripts\n";
    $readme .= "- **Statistics tracking**: Detailed campaign analytics\n";
    $readme .= "- **Email personalization**: Dynamic content\n";
    $readme .= "- **Recurring campaigns**: Automated scheduling\n\n";
    
    $readme .= "## 📞 Support\n\n";
    $readme .= "For issues or questions:\n";
    $readme .= "- Check fix scripts for common issues\n";
    $readme .= "- Run verification scripts for diagnostics\n";
    $readme .= "- Review logs for detailed error information\n\n";
    
    $readme .= "---\n";
    $readme .= "**ACRM Version {$version}** - Scheduled Campaigns Edition\n";
    $readme .= "**Backup created:** {$backupDate}\n";
    
    $zip->addFromString('README.md', $readme);
    
    // Close zip file
    $zip->close();
    
    // Verify zip file was created
    if (!file_exists($fullBackupPath)) {
        throw new Exception("Zip file was not created successfully");
    }
    
    $zipSize = filesize($fullBackupPath);
    
    echo "\n🎉 Backup completed successfully!\n\n";
    echo "📦 Backup Details:\n";
    echo "- Version: {$version}\n";
    echo "- Name: {$backupName}\n";
    echo "- Date: {$backupDate}\n";
    echo "- Location: {$fullBackupPath}\n";
    echo "- Size: " . formatBytes($zipSize) . "\n";
    echo "- Files: {$totalFiles}\n";
    echo "- Total Source Size: " . formatBytes($totalSize) . "\n";
    echo "- Compression Ratio: " . round((1 - $zipSize / $totalSize) * 100, 2) . "%\n\n";
    
    echo "📋 Backup Contents:\n";
    echo "- Complete application code\n";
    echo "- Database structure and data\n";
    echo "- Configuration files\n";
    echo "- Services and controllers\n";
    echo "- Views and templates\n";
    echo "- CSS and JavaScript assets\n";
    echo "- Cron job scripts\n";
    echo "- Fix and verification scripts\n";
    echo "- Documentation and README\n";
    echo "- Backup information (JSON)\n\n";
    
    echo "🚀 New Features in Version {$version}:\n";
    echo "- ✅ Scheduled Campaigns System\n";
    echo "- ✅ Immediate, Scheduled, and Recurring Campaigns\n";
    echo "- ✅ Email Personalization\n";
    echo "- ✅ Campaign Statistics Tracking\n";
    echo "- ✅ Automated Cron Job Processing\n";
    echo "- ✅ Comprehensive Testing Scripts\n";
    echo "- ✅ Database Migration and Fix Scripts\n";
    echo "- ✅ Multi-Environment Support\n\n";
    
    echo "📝 Next Steps:\n";
    echo "1. Upload backup to secure location\n";
    echo "2. Test restoration on staging environment\n";
    echo "3. Deploy to production server\n";
    echo "4. Set up cron jobs for scheduled campaigns\n";
    echo "5. Run verification scripts\n\n";
    
    echo "🎯 Backup is ready for deployment!\n";
    
} catch (Exception $e) {
    echo "❌ Error creating backup: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?> 