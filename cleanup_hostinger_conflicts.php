<?php
// cleanup_hostinger_conflicts.php - Remove conflicting files for Hostinger deployment

echo "🧹 Hostinger Git Deployment Conflict Resolver\n";
echo "=============================================\n\n";

// Files that are causing conflicts (from the error message)
$conflictFiles = [
    'fix_autocrm.php',
    'fix_dashboard.php', 
    'fix_email_dashboard.php',
    'generate_excel_template.php',
    'setup_admin.php',
    'setup_email_tamponents/navigation.php',
    'views/co' // This seems truncated, might be views/components/navigation.php
];

// Additional files that might cause conflicts (common temporary files)
$additionalFiles = [
    'test_*.php',
    'debug_*.php',
    'fix_*.php',
    'setup_*.php',
    'temp_*.php',
    'show_*.php'
];

echo "1. Identifying conflicting files...\n";

$foundFiles = [];
$backupCommands = [];

foreach ($conflictFiles as $file) {
    if (file_exists($file)) {
        $foundFiles[] = $file;
        echo "   ⚠ Found: $file\n";
        
        // Create backup command
        $backupName = "backup_" . basename($file, '.php') . '_' . date('Y-m-d_H-i-s') . '.php';
        $backupCommands[] = "mv '$file' '$backupName'";
    } else {
        echo "   ✓ Not found: $file\n";
    }
}

echo "\n2. Generating cleanup commands for Hostinger...\n";

// Create shell script for Hostinger
$shellScript = "#!/bin/bash\n";
$shellScript .= "# Hostinger Git Deployment Conflict Cleanup\n";
$shellScript .= "# Run this on your Hostinger server before git pull\n\n";

$shellScript .= "echo 'Starting Hostinger deployment cleanup...'\n\n";

// Add backup commands
if (!empty($backupCommands)) {
    $shellScript .= "# Backup existing files\n";
    foreach ($backupCommands as $cmd) {
        $shellScript .= "$cmd\n";
    }
    $shellScript .= "\n";
}

// Add removal commands for known conflict files
$shellScript .= "# Remove known conflict files\n";
foreach ($conflictFiles as $file) {
    $shellScript .= "rm -f '$file'\n";
}

$shellScript .= "\n# Remove temporary/test files\n";
$shellScript .= "rm -f test_*.php\n";
$shellScript .= "rm -f debug_*.php\n";
$shellScript .= "rm -f fix_*.php\n";
$shellScript .= "rm -f setup_*.php\n";
$shellScript .= "rm -f temp_*.php\n";
$shellScript .= "rm -f show_*.php\n";

$shellScript .= "\n# Check git status\n";
$shellScript .= "git status\n\n";

$shellScript .= "# Pull latest changes\n";
$shellScript .= "git pull origin main\n\n";

$shellScript .= "echo 'Deployment cleanup complete!'\n";

// Save shell script
file_put_contents('hostinger_cleanup.sh', $shellScript);

echo "✅ Created: hostinger_cleanup.sh\n\n";

echo "📋 Manual Commands for Hostinger:\n";
echo "==================================\n\n";

echo "Option 1 - Quick removal (recommended):\n";
echo "cd /path/to/your/project\n";
foreach ($conflictFiles as $file) {
    echo "rm -f '$file'\n";
}
echo "git pull origin main\n\n";

echo "Option 2 - Backup then remove:\n";
echo "cd /path/to/your/project\n";
foreach ($conflictFiles as $file) {
    $backup = "backup_" . basename($file, '.php') . '_' . date('Ymd') . '.php';
    echo "mv '$file' '$backup' 2>/dev/null || true\n";
}
echo "git pull origin main\n\n";

echo "Option 3 - Force reset (nuclear option):\n";
echo "cd /path/to/your/project\n";
echo "git fetch origin\n";
echo "git reset --hard origin/main\n";
echo "git clean -fd\n\n";

echo "Option 4 - Use the shell script:\n";
echo "chmod +x hostinger_cleanup.sh\n";
echo "./hostinger_cleanup.sh\n\n";

echo "⚡ Quickest Solution:\n";
echo "====================\n";
echo "Run these commands on your Hostinger server:\n\n";
echo "cd /path/to/your/project\n";
echo "rm -f fix_*.php setup_*.php generate_*.php\n";
echo "rm -rf setup_email_tamponents/\n";
echo "git pull origin main\n\n";

echo "🔍 What these files likely are:\n";
echo "- fix_*.php: Temporary fix scripts\n";
echo "- setup_*.php: Old setup/installation scripts\n";
echo "- generate_*.php: Utility scripts\n";
echo "- setup_email_tamponents/: Typo directory (should be 'components')\n\n";

echo "💡 After cleanup, your deployment should work!\n";
?>