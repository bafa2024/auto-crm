<?php
// create_comprehensive_v8_backup.php - Create comprehensive version 8.0 backup
// This script creates a complete backup including all fix and verification scripts

echo "=== Creating Comprehensive Version 8.0 Backup ===\n\n";

try {
    // Define backup details
    $version = "8.0";
    $backupName = "acrm_v{$version}_comprehensive";
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
    
    // Define specific files to include
    $specificFiles = [
        // Core application files
        'index.php',
        'autoload.php',
        'composer.json',
        'composer.lock',
        
        // Main application files
        'campaigns.php',
        'contacts.php',
        'dashboard.php',
        'login.php',
        'signup.php',
        'landing.php',
        
        // Fix scripts
        'fix_scheduled_campaigns.php',
        'fix_contact_table_consistency.php',
        'fix_foreign_key_constraint_issue.php',
        'fix_session_issues.php',
        'fix_campaign_id_null_issue.php',
        'fix_live_campaign_id_issue.php',
        'fix_contact_datetime_issue.php',
        
        // Verification scripts
        'verify_scheduled_campaigns.php',
        'verify_live_scheduled.php',
        'verify_contact_table_usage.php',
        'verify_live_contact_creation.php',
        
        // Setup scripts
        'setup_local.php',
        'setup_live.php',
        'setup_admin.php',
        'setup_email_tables.php',
        'setup_email_sqlite.php',
        
        // Switch scripts
        'switch_to_sqlite.php',
        'switch_to_mysql.php',
        'switch_to_mysql_live.php',
        
        // Create scripts
        'create_admin.php',
        'create_backup_tar.php',
        'create_backup_zip.ps1',
        'create_version_backup.php',
        'create_v7_backup.php',
        'create_v8_backup.php',
        
        // Deploy scripts
        'deploy.php',
        'database_manager.php',
        
        // Test scripts
        'test_api.php',
        'test_api_direct.php',
        'test_api_endpoints.php',
        'test_api_test.php',
        'test_contacts.csv',
        'test_dashboard.php',
        'test_dashboard_local.php',
        'test_dashboard_simple.php',
        'test_db.php',
        'test_email_upload.php',
        'test_email_upload_form.php',
        'test_fixed_login.php',
        'test_full_workflow.php',
        'test_login.php',
        'test_signup_login.php',
        'test_simple_login.html',
        'test_sqlite_dashboard.php',
        'test_upload_direct.php',
        'test_quick.php',
        'test_simple.php',
        'test_live_hosting.php',
        'test_live_functionality.php',
        'test_live_environment.php',
        'test_auto_database.php',
        'test_edit_simple.php',
        'test_edit_service.php',
        'test_edit_campaign.php',
        'test_scheduled_campaign.php',
        'test_campaign_fix.php',
        'test_campaign_creation.php',
        'test_upload_service.php',
        'test_contact_creation.php',
        'test_base_path.php',
        'test_contact_creation_fix.php',
        'test_live_contact_creation.php',
        'test_contact_table_usage.php',
        
        // Debug scripts
        'debug_api.php',
        'debug_network_error.php',
        'debug_recipients.php',
        'debug_edit_service.php',
        
        // Check scripts
        'check_env.php',
        'check_excel_template.php',
        'check_local_setup.php',
        'check_table_structure.php',
        'check_recipients_table.php',
        'check_campaigns_table.php',
        
        // Add scripts
        'add_dot_column.php',
        
        // Export scripts
        'export_sqlite_data.php',
        
        // Migrate scripts
        'migrate_to_mysql.php',
        
        // Cleanup scripts
        'cleanup_hostinger_conflicts.php',
        'cleanup_hostinger_files.php',
        'hostinger_cleanup.sh',
        
        // Deploy scripts
        'deploy_to_hostinger.php',
        
        // Send scripts
        'test_send_campaign.php',
        
        // Start scripts
        'start_app.bat',
        'start_local_server.php',
        
        // Live scripts
        'live_hosting_debug.php',
        'simple_live_test.php',
        'simple_test.php',
        
        // Excel files
        'Email marketing.xlsx',
        
        // Documentation
        'README.md',
        'QUICK_START.md',
        'SETUP.md',
        'SETUP_COMPLETE.md',
        'LOCAL_SETUP_GUIDE.md',
        'HOSTINGER_FIX.md',
        'LIVE_HOSTING_QUICK_FIX.md',
        'NETWORK_ERROR_FIXED.md',
        'AUTHENTICATION_FIXED.md',
        'EMAIL_UPLOAD_SUMMARY.md',
        'USER_ACCOUNTS_GUIDE.md',
        'RELEASE_v3.0.md',
        'VERSION_3.0_BACKUP_INFO.md'
    ];
    
    // Define directories to include
    $directories = [
        'config/',
        'controllers/',
        'models/',
        'services/',
        'views/',
        'public/',
        'router/',
        'database/',
        'css/',
        'js/',
        'cron/',
        'uploads/',
        'logs/',
        'sessions/',
        'cache/',
        'components/',
        'api/',
        'vendor/',
        'temp/',
        'backups/'
    ];
    
    $totalFiles = 0;
    $totalSize = 0;
    
    echo "\n📦 Adding specific files to backup...\n";
    
    // Add specific files
    foreach ($specificFiles as $file) {
        if (file_exists($file)) {
            $zip->addFile($file, $file);
            $totalFiles++;
            $totalSize += filesize($file);
            echo "  📄 Added file: $file\n";
        } else {
            echo "  ⚠️ File not found: $file\n";
        }
    }
    
    echo "\n📁 Adding directories to backup...\n";
    
    // Add directories recursively
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                $filePath = $file->getRealPath();
                $relativePath = str_replace('\\', '/', substr($filePath, strlen(getcwd()) + 1));
                
                // Skip certain files
                if (strpos($relativePath, '.git/') !== false ||
                    strpos($relativePath, 'node_modules/') !== false ||
                    strpos($relativePath, 'Thumbs.db') !== false ||
                    strpos($relativePath, '.DS_Store') !== false) {
                    continue;
                }
                
                $zip->addFile($filePath, $relativePath);
                $totalFiles++;
                $totalSize += filesize($filePath);
            }
            
            echo "  📁 Added directory: $dir\n";
        } else {
            echo "  ⚠️ Directory not found: $dir\n";
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
        'backup_type' => 'comprehensive',
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
            'reports' => true,
            'multi_environment' => true,
            'auto_detection' => true
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
            'fix_session_issues.php',
            'fix_campaign_id_null_issue.php',
            'fix_live_campaign_id_issue.php',
            'fix_contact_datetime_issue.php'
        ],
        'verification_scripts' => [
            'verify_scheduled_campaigns.php',
            'verify_live_scheduled.php',
            'verify_contact_table_usage.php',
            'verify_live_contact_creation.php'
        ],
        'setup_scripts' => [
            'setup_local.php',
            'setup_live.php',
            'setup_admin.php',
            'setup_email_tables.php',
            'setup_email_sqlite.php'
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
    
    // Create comprehensive README
    $readme = "# ACRM Version {$version} Comprehensive Backup\n\n";
    $readme .= "**Backup Date:** {$backupDate}\n";
    $readme .= "**Backup Type:** Comprehensive\n";
    $readme .= "**Total Files:** {$totalFiles}\n";
    $readme .= "**Total Size:** " . formatBytes($totalSize) . "\n\n";
    
    $readme .= "## 🚀 Version {$version} Features\n\n";
    $readme .= "### Scheduled Campaigns System\n";
    $readme .= "- ✅ **Immediate Campaigns**: Send emails right away\n";
    $readme .= "- ✅ **Scheduled Campaigns**: Send at specific date/time\n";
    $readme .= "- ✅ **Recurring Campaigns**: Daily, weekly, monthly\n";
    $readme .= "- ✅ **Email Personalization**: {first_name}, {name}, {email}, {company}\n";
    $readme .= "- ✅ **Campaign Statistics**: Sent, opened, clicked tracking\n";
    $readme .= "- ✅ **Automated Processing**: Cron job support\n";
    $readme .= "- ✅ **Status Management**: Draft, scheduled, sending, completed, failed\n\n";
    
    $readme .= "### Database & Migration\n";
    $readme .= "- ✅ **email_campaigns table**: Complete campaign management\n";
    $readme .= "- ✅ **campaign_sends table**: Individual email tracking\n";
    $readme .= "- ✅ **email_recipients table**: Contact management\n";
    $readme .= "- ✅ **Foreign key constraints**: Data integrity\n";
    $readme .= "- ✅ **Auto-migration**: Seamless database updates\n";
    $readme .= "- ✅ **Multi-environment**: SQLite (local) and MySQL (live)\n\n";
    
    $readme .= "### Services & Processing\n";
    $readme .= "- ✅ **ScheduledCampaignService**: Core scheduling logic\n";
    $readme .= "- ✅ **EmailService**: Email sending and processing\n";
    $readme .= "- ✅ **EmailCampaignService**: Campaign management\n";
    $readme .= "- ✅ **CronService**: Automated task processing\n";
    $readme .= "- ✅ **FileUploadService**: File upload handling\n";
    $readme .= "- ✅ **EmailUploadService**: Email list uploads\n\n";
    
    $readme .= "### Testing & Verification\n";
    $readme .= "- ✅ **Comprehensive test scripts**: All functionality tested\n";
    $readme .= "- ✅ **Live testing scripts**: Production environment testing\n";
    $readme .= "- ✅ **Fix scripts**: Automatic issue resolution\n";
    $readme .= "- ✅ **Verification scripts**: System health checks\n";
    $readme .= "- ✅ **Setup scripts**: Easy installation\n\n";
    
    $readme .= "## 📋 Installation Instructions\n\n";
    $readme .= "### Quick Setup\n";
    $readme .= "1. **Extract backup** to your web server directory\n";
    $readme .= "2. **Run fix script**: `fix_scheduled_campaigns.php`\n";
    $readme .= "3. **Set up cron job**: Every minute execution\n";
    $readme .= "4. **Test system**: `verify_scheduled_campaigns.php`\n";
    $readme .= "5. **Configure email**: Update email service settings\n\n";
    
    $readme .= "### Detailed Setup\n";
    $readme .= "1. **Environment Setup**:\n";
    $readme .= "   - Local: `setup_local.php`\n";
    $readme .= "   - Live: `setup_live.php`\n";
    $readme .= "2. **Database Setup**:\n";
    $readme .= "   - SQLite: `switch_to_sqlite.php`\n";
    $readme .= "   - MySQL: `switch_to_mysql.php`\n";
    $readme .= "3. **Admin Setup**: `setup_admin.php`\n";
    $readme .= "4. **Email Tables**: `setup_email_tables.php`\n\n";
    
    $readme .= "## 🔧 Cron Job Setup\n\n";
    $readme .= "### Hostinger Setup\n";
    $readme .= "1. Go to Hostinger Control Panel → Advanced → Cron Jobs\n";
    $readme .= "2. Add new cron job:\n";
    $readme .= "   - **Common Settings**: Every minute\n";
    $readme .= "   - **Command**: `php /home/u946493694/domains/regrowup.ca/public_html/acrm/cron/process_scheduled_campaigns.php`\n\n";
    
    $readme .= "### Manual Testing\n";
    $readme .= "Test cron job manually:\n";
    $readme .= "```bash\n";
    $readme .= "php cron/process_scheduled_campaigns.php\n";
    $readme .= "```\n\n";
    
    $readme .= "## 🧪 Testing Procedures\n\n";
    $readme .= "### 1. System Verification\n";
    $readme .= "- **Basic test**: `verify_scheduled_campaigns.php`\n";
    $readme .= "- **Live test**: `verify_live_scheduled.php`\n";
    $readme .= "- **Contact test**: `verify_contact_table_usage.php`\n";
    $readme .= "- **Contact creation**: `verify_live_contact_creation.php`\n\n";
    
    $readme .= "### 2. Campaign Testing\n";
    $readme .= "- **Immediate campaigns**: Send right away\n";
    $readme .= "- **Scheduled campaigns**: Set future date/time\n";
    $readme .= "- **Recurring campaigns**: Daily/weekly/monthly\n";
    $readme .= "- **Email personalization**: Test variables\n\n";
    
    $readme .= "### 3. Database Testing\n";
    $readme .= "- **Table structure**: `check_table_structure.php`\n";
    $readme .= "- **Recipients table**: `check_recipients_table.php`\n";
    $readme .= "- **Campaigns table**: `check_campaigns_table.php`\n";
    $readme .= "- **Environment check**: `check_env.php`\n\n";
    
    $readme .= "## 🚨 Troubleshooting\n\n";
    $readme .= "### Common Issues\n";
    $readme .= "1. **Database Issues**: Run `fix_scheduled_campaigns.php`\n";
    $readme .= "2. **Contact Issues**: Run `fix_contact_table_consistency.php`\n";
    $readme .= "3. **Session Issues**: Run `fix_session_issues.php`\n";
    $readme .= "4. **Campaign Issues**: Run `fix_campaign_id_null_issue.php`\n";
    $readme .= "5. **Email Issues**: Check email service configuration\n";
    $readme .= "6. **Cron Issues**: Verify cron job setup\n\n";
    
    $readme .= "### Debug Scripts\n";
    $readme .= "- **API Debug**: `debug_api.php`\n";
    $readme .= "- **Network Debug**: `debug_network_error.php`\n";
    $readme .= "- **Recipients Debug**: `debug_recipients.php`\n";
    $readme .= "- **Edit Service Debug**: `debug_edit_service.php`\n\n";
    
    $readme .= "## 📊 Database Schema\n\n";
    $readme .= "### Core Tables\n";
    $readme .= "- `users`: User management and authentication\n";
    $readme .= "- `email_campaigns`: Campaign data and scheduling\n";
    $readme .= "- `campaign_sends`: Individual email tracking\n";
    $readme .= "- `email_recipients`: Contact management\n";
    $readme .= "- `email_templates`: Email templates\n";
    $readme .= "- `email_queue`: Email processing queue\n\n";
    
    $readme .= "### Key Fields\n";
    $readme .= "- `schedule_type`: immediate, scheduled, recurring\n";
    $readme .= "- `schedule_date`: When to send the campaign\n";
    $readme .= "- `frequency`: daily, weekly, monthly (for recurring)\n";
    $readme .= "- `status`: draft, scheduled, sending, completed, failed\n";
    $readme .= "- `sent_count`, `opened_count`, `clicked_count`: Statistics\n\n";
    
    $readme .= "## 🎯 Key Features Summary\n\n";
    $readme .= "- **Multi-environment support**: Local (SQLite) and Live (MySQL)\n";
    $readme .= "- **Auto-detection**: Environment and database type\n";
    $readme .= "- **Comprehensive testing**: Multiple test scripts\n";
    $readme .= "- **Error recovery**: Automatic fix scripts\n";
    $readme .= "- **Statistics tracking**: Detailed campaign analytics\n";
    $readme .= "- **Email personalization**: Dynamic content\n";
    $readme .= "- **Recurring campaigns**: Automated scheduling\n";
    $readme .= "- **Contact management**: Bulk upload and management\n";
    $readme .= "- **User authentication**: Secure login system\n";
    $readme .= "- **Dashboard**: Comprehensive overview\n";
    $readme .= "- **Reports**: Campaign performance tracking\n\n";
    
    $readme .= "## 📞 Support & Maintenance\n\n";
    $readme .= "### Regular Maintenance\n";
    $readme .= "1. **Monitor cron jobs**: Ensure scheduled campaigns run\n";
    $readme .= "2. **Check logs**: Review error logs regularly\n";
    $readme .= "3. **Backup data**: Regular database backups\n";
    $readme .= "4. **Update scripts**: Keep fix scripts current\n\n";
    
    $readme .= "### Emergency Procedures\n";
    $readme .= "1. **System down**: Check server status and logs\n";
    $readme .= "2. **Database issues**: Run appropriate fix scripts\n";
    $readme .= "3. **Email issues**: Verify email service configuration\n";
    $readme .= "4. **Campaign issues**: Check campaign status and logs\n\n";
    
    $readme .= "---\n";
    $readme .= "**ACRM Version {$version}** - Comprehensive Scheduled Campaigns Edition\n";
    $readme .= "**Backup created:** {$backupDate}\n";
    $readme .= "**Total files:** {$totalFiles}\n";
    $readme .= "**Total size:** " . formatBytes($totalSize) . "\n";
    
    $zip->addFromString('README.md', $readme);
    
    // Close zip file
    $zip->close();
    
    // Verify zip file was created
    if (!file_exists($fullBackupPath)) {
        throw new Exception("Zip file was not created successfully");
    }
    
    $zipSize = filesize($fullBackupPath);
    
    echo "\n🎉 Comprehensive backup completed successfully!\n\n";
    echo "📦 Backup Details:\n";
    echo "- Version: {$version}\n";
    echo "- Name: {$backupName}\n";
    echo "- Date: {$backupDate}\n";
    echo "- Location: {$fullBackupPath}\n";
    echo "- Size: " . formatBytes($zipSize) . "\n";
    echo "- Files: {$totalFiles}\n";
    echo "- Total Source Size: " . formatBytes($totalSize) . "\n";
    echo "- Compression Ratio: " . round((1 - $zipSize / $totalSize) * 100, 2) . "%\n\n";
    
    echo "📋 Comprehensive Backup Contents:\n";
    echo "- ✅ Complete application code\n";
    echo "- ✅ Database structure and data\n";
    echo "- ✅ Configuration files\n";
    echo "- ✅ Services and controllers\n";
    echo "- ✅ Views and templates\n";
    echo "- ✅ CSS and JavaScript assets\n";
    echo "- ✅ Cron job scripts\n";
    echo "- ✅ ALL fix and verification scripts\n";
    echo "- ✅ ALL setup and deployment scripts\n";
    echo "- ✅ ALL test and debug scripts\n";
    echo "- ✅ Documentation and README\n";
    echo "- ✅ Backup information (JSON)\n";
    echo "- ✅ Vendor dependencies\n";
    echo "- ✅ Upload directories\n";
    echo "- ✅ Log files\n\n";
    
    echo "🚀 Version {$version} Features Included:\n";
    echo "- ✅ Scheduled Campaigns System\n";
    echo "- ✅ Immediate, Scheduled, and Recurring Campaigns\n";
    echo "- ✅ Email Personalization\n";
    echo "- ✅ Campaign Statistics Tracking\n";
    echo "- ✅ Automated Cron Job Processing\n";
    echo "- ✅ Comprehensive Testing Scripts\n";
    echo "- ✅ Database Migration and Fix Scripts\n";
    echo "- ✅ Multi-Environment Support\n";
    echo "- ✅ Contact Management System\n";
    echo "- ✅ User Authentication\n";
    echo "- ✅ Dashboard and Reports\n";
    echo "- ✅ File Upload System\n";
    echo "- ✅ API Endpoints\n\n";
    
    echo "📝 Next Steps:\n";
    echo "1. Upload backup to secure location\n";
    echo "2. Test restoration on staging environment\n";
    echo "3. Deploy to production server\n";
    echo "4. Set up cron jobs for scheduled campaigns\n";
    echo "5. Run verification scripts\n";
    echo "6. Test all functionality\n\n";
    
    echo "🎯 Comprehensive backup is ready for deployment!\n";
    
} catch (Exception $e) {
    echo "❌ Error creating comprehensive backup: " . $e->getMessage() . "\n";
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