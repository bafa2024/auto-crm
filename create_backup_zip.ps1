# create_backup_zip.ps1 - Create Version 3.0 ZIP backup using PowerShell

Write-Host "Creating AutoDial Pro Version 3.0 ZIP Backup" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green
Write-Host ""

$version = "3.0"
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$backupFileName = "AutoDialPro_v$version`_Backup_$timestamp.zip"
$backupPath = Join-Path (Get-Location) $backupFileName

Write-Host "1. Creating ZIP backup: $backupFileName" -ForegroundColor Yellow

# Create version info file
$versionInfo = @"
AutoDial Pro Version 3.0 - Debugged Authentication System
=======================================================

Created: $(Get-Date)
Commit: 8efe44e
Version: 3.0

Major Features:
- Fixed authentication system and network errors
- SQLite database integration for local testing
- Complete user management (signup/login)
- AutoDial Pro dashboard with live data
- Mobile-responsive design
- Comprehensive documentation and testing tools

Login Credentials:
- Admin: admin@autocrm.com / admin123
- Test: test@autocrm.com / test123

Quick Start:
1. Extract to web server directory
2. Run: php switch_to_sqlite.php
3. Access: http://localhost/acrm/login

Installation:
Extract this ZIP file to your web server directory (e.g., htdocs/acrm/)
"@

$versionInfo | Out-File -FilePath "VERSION_3.0_INFO.txt" -Encoding UTF8

# Files and directories to include
$itemsToBackup = @(
    "index.php",
    "autoload.php", 
    "api.php",
    "config",
    "controllers",
    "models",
    "views",
    "public",
    "css",
    "js", 
    "database",
    "services",
    "router",
    "database_manager.php",
    "switch_to_sqlite.php", 
    "switch_to_mysql.php",
    "create_admin.php",
    "fix_auth_issues.php",
    "api_test.php",
    "AUTHENTICATION_FIXED.md",
    "NETWORK_ERROR_FIXED.md", 
    "QUICK_START.md",
    "USER_ACCOUNTS_GUIDE.md",
    "RELEASE_v3.0.md",
    "SETUP.md",
    "composer.json",
    "VERSION_3.0_INFO.txt"
)

Write-Host "2. Compressing files..." -ForegroundColor Yellow

try {
    # Create ZIP file using .NET classes
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    if (Test-Path $backupPath) {
        Remove-Item $backupPath -Force
    }
    
    $zip = [System.IO.Compression.ZipFile]::Open($backupPath, 'Create')
    
    $fileCount = 0
    
    foreach ($item in $itemsToBackup) {
        if (Test-Path $item) {
            if (Test-Path $item -PathType Container) {
                # Directory
                Get-ChildItem -Path $item -Recurse -File | ForEach-Object {
                    $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
                    $entry = $zip.CreateEntry($relativePath.Replace('\', '/'))
                    $entryStream = $entry.Open()
                    $fileStream = [System.IO.File]::OpenRead($_.FullName)
                    $fileStream.CopyTo($entryStream)
                    $fileStream.Close()
                    $entryStream.Close()
                    $fileCount++
                }
                Write-Host "  ✓ Added directory: $item" -ForegroundColor Green
            } else {
                # File
                $entry = $zip.CreateEntry($item.Replace('\', '/'))
                $entryStream = $entry.Open()
                $fileStream = [System.IO.File]::OpenRead((Join-Path (Get-Location) $item))
                $fileStream.CopyTo($entryStream)
                $fileStream.Close()
                $entryStream.Close()
                $fileCount++
                Write-Host "  ✓ Added file: $item" -ForegroundColor Green
            }
        } else {
            Write-Host "  ⚠ Missing: $item" -ForegroundColor Yellow
        }
    }
    
    $zip.Dispose()
    
    $backupSize = (Get-Item $backupPath).Length
    $backupSizeMB = [math]::Round($backupSize / 1MB, 2)
    
    Write-Host ""
    Write-Host "✅ ZIP Backup created successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "📦 Backup Details:" -ForegroundColor Cyan
    Write-Host "   File: $backupFileName" -ForegroundColor White
    Write-Host "   Path: $backupPath" -ForegroundColor White  
    Write-Host "   Size: $($backupSize.ToString('N0')) bytes ($backupSizeMB MB)" -ForegroundColor White
    Write-Host "   Files: $fileCount" -ForegroundColor White
    Write-Host "   Version: 3.0" -ForegroundColor White
    Write-Host "   Commit: 8efe44e" -ForegroundColor White
    Write-Host "   Format: ZIP (Windows compatible)" -ForegroundColor White
    Write-Host ""
    Write-Host "📋 What's Included:" -ForegroundColor Cyan
    Write-Host "   ✓ Complete AutoDial Pro application" -ForegroundColor Green
    Write-Host "   ✓ SQLite database with sample data" -ForegroundColor Green
    Write-Host "   ✓ All authentication fixes" -ForegroundColor Green
    Write-Host "   ✓ Dashboard and user interface" -ForegroundColor Green
    Write-Host "   ✓ Documentation and guides" -ForegroundColor Green
    Write-Host "   ✓ Database management tools" -ForegroundColor Green
    Write-Host ""
    Write-Host "🚀 Version 3.0 ZIP backup ready!" -ForegroundColor Green
    
} catch {
    Write-Host "❌ Error creating ZIP backup: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "📋 Manual Backup Instructions:" -ForegroundColor Yellow
    Write-Host "1. Select all files in the 'acrm' folder" -ForegroundColor White
    Write-Host "2. Right-click → Send to → Compressed (zipped) folder" -ForegroundColor White
    Write-Host "3. Rename to: AutoDialPro_v3.0_Backup_$timestamp.zip" -ForegroundColor White
} finally {
    # Clean up temp file
    if (Test-Path "VERSION_3.0_INFO.txt") {
        Remove-Item "VERSION_3.0_INFO.txt" -Force
    }
}

Write-Host ""
Write-Host "Press any key to continue..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")