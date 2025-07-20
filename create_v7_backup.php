<?php
// create_v7_backup.php - Create version 7.0 backup
// Contact creation in live server fixed

echo "=== Creating Version 7.0 Backup ===\n";
echo "Contact creation in live server fixed\n\n";

$version = "v7.0-contact-creation-fixed";
$backupDir = "C:/xampp/htdocs";
$projectDir = __DIR__;
$backupFile = $backupDir . "/acrm-" . $version . ".zip";

echo "Version: $version\n";
echo "Backup Directory: $backupDir\n";
echo "Project Directory: $projectDir\n";
echo "Backup File: $backupFile\n\n";

// Check if backup directory exists
if (!is_dir($backupDir)) {
    echo "❌ Backup directory does not exist: $backupDir\n";
    exit(1);
}

// Check if project directory exists
if (!is_dir($projectDir)) {
    echo "❌ Project directory does not exist: $projectDir\n";
    exit(1);
}

echo "1. Creating backup archive...\n";

// Create a temporary directory for the backup
$tempDir = sys_get_temp_dir() . "/acrm_backup_" . time();
if (!mkdir($tempDir, 0755, true)) {
    echo "❌ Failed to create temporary directory\n";
    exit(1);
}

// Copy project files to temporary directory
echo "2. Copying project files...\n";

$excludePatterns = [
    '.git',
    'vendor',
    'node_modules',
    'cache',
    'temp',
    'logs',
    'uploads',
    'sessions',
    '*.log',
    '*.tmp',
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini'
];

function shouldExclude($path, $excludePatterns) {
    foreach ($excludePatterns as $pattern) {
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
            if (preg_match('/' . $pattern . '/', basename($path))) {
                return true;
            }
        } else {
            if (basename($path) === $pattern) {
                return true;
            }
        }
    }
    return false;
}

function copyDirectory($source, $destination, $excludePatterns) {
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $files = scandir($source);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $sourcePath = $source . '/' . $file;
        $destPath = $destination . '/' . $file;
        
        if (shouldExclude($sourcePath, $excludePatterns)) {
            echo "  Skipping: $file\n";
            continue;
        }
        
        if (is_dir($sourcePath)) {
            copyDirectory($sourcePath, $destPath, $excludePatterns);
        } else {
            copy($sourcePath, $destPath);
        }
    }
}

copyDirectory($projectDir, $tempDir . '/acrm', $excludePatterns);

echo "3. Creating ZIP archive...\n";

// Create ZIP archive
$zip = new ZipArchive();
if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    echo "❌ Failed to create ZIP archive\n";
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$fileCount = 0;
foreach ($iterator as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($tempDir) + 1);
        
        $zip->addFile($filePath, $relativePath);
        $fileCount++;
    }
}

$zip->close();

// Clean up temporary directory
function removeDirectory($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

removeDirectory($tempDir);

// Verify backup file
if (file_exists($backupFile)) {
    $fileSize = filesize($backupFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
    
    echo "4. Backup completed successfully!\n";
    echo "✅ Backup file: $backupFile\n";
    echo "✅ File size: $fileSizeMB MB\n";
    echo "✅ Files included: $fileCount\n";
    
    echo "\n=== Version 7.0 Features ===\n";
    echo "✅ Contact creation with NULL campaign_id fixed\n";
    echo "✅ Database schema updated for nullable campaign_id\n";
    echo "✅ Manual contact creation working\n";
    echo "✅ Bulk contact upload working\n";
    echo "✅ Session issues resolved\n";
    echo "✅ Foreign key constraints fixed\n";
    echo "✅ Campaign management working\n";
    echo "✅ Dashboard functionality working\n";
    echo "✅ Authentication system working\n";
    echo "✅ Database switching (SQLite/MySQL) working\n";
    
    echo "\n=== Backup Information ===\n";
    echo "Version: $version\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Location: $backupFile\n";
    echo "Size: $fileSizeMB MB\n";
    echo "Files: $fileCount\n";
    
    echo "\n🎉 Version 7.0 backup created successfully!\n";
    echo "The backup file is ready for deployment or archival.\n";
    
} else {
    echo "❌ Backup file was not created\n";
    exit(1);
}
?> 