# AutoDial Pro - Quick Start Guide

## 🚀 Local Testing Setup Complete!

Your AutoDial Pro application is ready for local testing with SQLite database.

## 🔐 Admin Login Credentials

### Primary Admin Account
- **Email:** `admin@autocrm.com`
- **Password:** `admin123`
- **Role:** Full system administrator

### Test User Account  
- **Email:** `test@autocrm.com`
- **Password:** `test123`
- **Role:** Standard user

## 🌐 Access URLs

| Page | URL |
|------|-----|
| **Login Page** | `http://localhost/acrm/login` |
| **Dashboard** | `http://localhost/acrm/dashboard` |
| **Landing Page** | `http://localhost/acrm/` |
| **API Endpoint** | `http://localhost/acrm/api/` |

## 📋 Testing Steps

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache server
   - No MySQL needed (using SQLite)

2. **Access Login**
   - Open browser
   - Go to: `http://localhost/acrm/login`

3. **Login as Admin**
   - Email: `admin@autocrm.com`
   - Password: `admin123`
   - Click "Sign In"

4. **Explore Dashboard**
   - View statistics and metrics
   - Test navigation menu
   - Try quick action buttons

## 📊 Sample Data Available

- **👥 Users:** 2 accounts (admin + test)
- **📞 Contacts:** 10 business contacts
- **📋 Call Logs:** 20 sample call records
- **📧 Campaigns:** 1 active email campaign
- **📝 Templates:** 3 email templates

## 🛠️ Database Management

```bash
# View current database info
php database_manager.php info

# Reset database with fresh data
php database_manager.php reset-sqlite

# Switch back to MySQL (if needed)
php database_manager.php switch-mysql

# Test database functionality
php database_manager.php test-sqlite
```

## 🔧 Troubleshooting

### Login Issues
- ✅ Verify XAMPP Apache is running
- ✅ Check URL: `http://localhost/acrm/login`
- ✅ Use exact credentials: `admin@autocrm.com` / `admin123`
- ✅ Clear browser cache/cookies

### Database Issues
- ✅ SQLite file exists: `database/autocrm_local.db`
- ✅ Run: `php database_manager.php info`
- ✅ Recreate if needed: `php database_manager.php reset-sqlite`

### Access Issues
- ✅ Check XAMPP document root points to correct folder
- ✅ Verify file permissions
- ✅ Check browser console for JavaScript errors

## 🎯 Key Features to Test

### Dashboard
- [x] Statistics cards with real data
- [x] Recent activity table
- [x] Quick action buttons
- [x] Responsive sidebar navigation

### Authentication
- [x] User login/logout
- [x] Session management
- [x] Protected routes

### Navigation
- [x] Dashboard sections
- [x] Mobile responsive menu
- [x] User profile dropdown

## 📝 Development Notes

- **Database:** SQLite (no MySQL setup required)
- **Framework:** Pure PHP with Bootstrap 5
- **Authentication:** Session-based
- **Frontend:** Responsive design with Bootstrap
- **API:** RESTful endpoints available

## 🔄 Quick Commands

```bash
# Create admin account
php create_admin.php

# Test login functionality  
php test_login.php

# Test dashboard with SQLite
php test_sqlite_dashboard.php

# Full database info
php database_manager.php info
```

---

## ✅ Ready for Testing!

Your AutoDial Pro application is now fully configured and ready for local testing. Login with the admin credentials above to start exploring the dashboard and features.