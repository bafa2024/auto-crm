# Hostinger Deployment Guide

## 🚨 **Current Issue Fixed**

The deployment was failing because Hostinger's server had untracked files that would be overwritten by Git merge.

## ✅ **Solution Applied**

1. **Added comprehensive .gitignore** - Prevents future conflicts
2. **Removed test files** - Cleaned up repository
3. **Updated repository** - Pushed clean version to GitHub

## 🚀 **Deployment Steps**

### **Option 1: SSH Access (Recommended)**

If you have SSH access to your Hostinger server:

```bash
# Connect to your Hostinger server via SSH
ssh username@your-server.com

# Navigate to your project directory
cd /path/to/your/project

# Clean up and deploy
git stash
git clean -fd
git pull origin main
```

### **Option 2: Hostinger Git Deployment Settings**

1. **Go to Hostinger Control Panel**
2. **Navigate to Git Deployment**
3. **Add these Build Commands:**

```bash
git stash
git clean -fd
git pull origin main
```

### **Option 3: Manual File Cleanup**

If you can access the file manager, remove these files:

```
.claude/settings.local.json
LIVE_HOSTING_QUICK_FIX.md
RELEASE_v3.0.md
VERSION_3.0_BACKUP_INFO.md
config/database_mysql_backup.php
config/database_mysql_live.php
create_backup_tar.php
create_backup_zip.ps1
create_version_backup.php
database/autocrm_local.db
debug_network_error.php
setup_live_hosting.php
show_all_users.php
simple_test.php
switch_to_mysql_live.php
test_api_direct.php
test_api_endpoints.php
test_dashboard_simple.php
test_fixed_login.php
test_login.php
test_signup_login.php
test_simple_login.html
test_sqlite_dashboard.php
```

Then trigger a new deployment.

## 🔧 **Post-Deployment Setup**

### **1. Database Configuration**

Create a new `config/database_mysql_live.php` file on the server:

```php
<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'your_database_name';
    private $username = 'your_username';
    private $password = 'your_password';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
```

### **2. Update config/config.php**

Make sure your `config/config.php` uses the correct database configuration:

```php
<?php
// Use MySQL for live environment
require_once 'database_mysql_live.php';
?>
```

### **3. Set Up Email Configuration**

Configure your email settings in `services/EmailCampaignService.php` for production.

## 📋 **Files to Keep on Server**

These files should remain on your live server:

- `config/database_mysql_live.php` (create this)
- Any environment-specific configuration files
- Database files (if using local database)

## 🚫 **Files Excluded from Git**

The `.gitignore` now excludes:

- Database files (`*.db`, `*.sqlite`)
- Test files (`test_*.php`, `debug_*.php`)
- Backup files (`*.zip`, `*.tar`)
- Configuration files (`database_mysql_*.php`)
- Documentation (`*.md`)
- IDE files (`.claude/`, `.vscode/`)

## ✅ **Verification**

After deployment, verify:

1. **Campaigns page loads**: `/campaigns.php`
2. **Contacts page works**: `/contacts.php`
3. **Database connection**: Check for errors
4. **Email functionality**: Test campaign creation

## 🆘 **Troubleshooting**

### **If deployment still fails:**

1. **Check Hostinger logs** for specific error messages
2. **Verify Git repository** is accessible
3. **Ensure SSH keys** are properly configured
4. **Contact Hostinger support** if issues persist

### **If files are missing:**

1. **Check .gitignore** - files might be excluded
2. **Verify file permissions** on server
3. **Check deployment logs** for errors

## 📞 **Support**

If you continue to have issues:

1. **Check Hostinger's Git deployment documentation**
2. **Contact Hostinger support** with specific error messages
3. **Verify your Git repository** is properly configured

---

**Last Updated**: July 19, 2025
**Repository**: https://github.com/bafa2024/auto-crm.git 