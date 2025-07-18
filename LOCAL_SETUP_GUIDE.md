# Local Setup Guide - Email Upload to SQLite

## Quick Start

This guide will help you set up the ACRM application locally to upload email contacts to a SQLite database.

### Prerequisites
- PHP 8.0+ (You have PHP 8.2.12 ✓)
- SQLite extension enabled ✓
- Web server (Built-in PHP server recommended)

### 1. Database Setup

The SQLite database is already configured and working:

```bash
# Check database status
php check_local_setup.php
```

**Database Location:** `C:\xampp\htdocs\acrm\database\autocrm_local.db`

### 2. Start the Web Server

#### Option A: PHP Built-in Server (Recommended)
```bash
cd "C:\xampp\htdocs\acrm"
php -S localhost:8080
```

#### Option B: XAMPP Apache
- Start XAMPP Control Panel
- Start Apache
- Access: `http://localhost/acrm/`

### 3. Test Email Upload

1. **Open the upload interface:**
   ```
   http://localhost:8080/test_email_upload_form.php
   ```

2. **Upload the test CSV file:**
   - Use the provided `test_contacts.csv` file
   - Or create your own CSV with columns: Email, Name, Company

3. **Verify the upload:**
   - Check the results page
   - View imported contacts in the database

### 4. CSV Format

Your CSV file should follow this format:

```csv
Email,Name,Company
john.doe@example.com,John Doe,Example Corp
jane.smith@example.com,Jane Smith,Tech Solutions
bob.wilson@example.com,Bob Wilson,Marketing Agency
```

**Supported Headers:**
- **Email:** Email, E-mail, email
- **Name:** Name, Full Name, Contact Name
- **Company:** Company, Organization, Company Name

### 5. Database Tables

The following tables are used for email functionality:

- `email_campaigns` - Campaign information
- `email_recipients` - Uploaded contact lists
- `email_templates` - Email templates
- `email_uploads` - Upload tracking

### 6. Testing Commands

```bash
# Test database connection
php check_local_setup.php

# Test email upload workflow
php test_full_workflow.php

# Setup email tables (if needed)
php setup_email_sqlite.php
```

### 7. Access Points

| URL | Purpose |
|-----|---------|
| `http://localhost:8080/` | Main application |
| `http://localhost:8080/test_email_upload_form.php` | Email upload interface |
| `http://localhost:8080/download_template.php` | Download CSV template |
| `http://localhost:8080/check_local_setup.php` | System status check |

### 8. File Structure

```
acrm/
├── database/
│   └── autocrm_local.db          # SQLite database
├── services/
│   └── EmailUploadService.php    # Email upload processing
├── controllers/
│   └── EmailCampaignController.php # Campaign management
├── test_contacts.csv             # Sample CSV file
├── test_email_upload_form.php    # Upload interface
└── uploads/                      # Uploaded files (temporary)
```

### 9. Features

✅ **Working Features:**
- CSV file upload and parsing
- Email validation
- Database storage in SQLite
- Duplicate email detection
- Campaign association
- Error handling and reporting
- Template download

✅ **Tested Successfully:**
- 10 sample contacts imported
- All data stored correctly
- Web interface functional
- Database operations working

### 10. Troubleshooting

**Database Connection Issues:**
```bash
# Check if database exists
ls -la database/autocrm_local.db

# Test connection
php -r "require_once 'config/database.php'; $db = new Database(); echo 'OK';"
```

**Permission Issues:**
```bash
# Check directory permissions
chmod 755 uploads logs temp sessions
```

**Port Already in Use:**
```bash
# Use alternative port
php -S localhost:8081
```

### 11. Sample Data

The system includes a test CSV file with 10 sample contacts:
- john.doe@example.com (John Doe)
- jane.smith@example.com (Jane Smith)
- bob.wilson@example.com (Bob Wilson)
- And 7 more contacts...

### 12. Next Steps

1. **Start the server:** `php -S localhost:8080`
2. **Open the upload page:** `http://localhost:8080/test_email_upload_form.php`
3. **Upload test_contacts.csv**
4. **Verify results in the database**

---

## Status: ✅ READY FOR USE

The email upload system is fully functional and ready for local testing and development.

### Support Files Created:
- `EmailUploadService.php` - Core upload functionality
- `test_email_upload_form.php` - Web interface
- `test_contacts.csv` - Sample data
- `download_template.php` - Template generator

All components tested and working correctly with SQLite database.