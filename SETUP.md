# AutoDial Pro CRM - Setup Guide

## Quick Start

### For Local Development (XAMPP)

1. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services
   - Ensure both services show green status

2. **Run Local Setup**
   ```bash
   php setup_local.php
   ```

3. **Access Application**
   - Open browser and go to: `http://localhost/autocrm`
   - Login with default admin credentials:
     - Email: `admin@autocrm.com`
     - Password: `admin123`

### For Live Server

1. **Upload Files**
   - Upload all project files to your web server
   - Ensure the web root points to the project directory

2. **Create Environment File**
   Create a `.env` file in the project root:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=autocrm
   DB_USER=your_database_username
   DB_PASS=your_database_password
   
   # Application Configuration
   APP_URL=https://yourdomain.com
   APP_ENV=production
   APP_DEBUG=false
   
   # Security
   JWT_SECRET=your_random_secret_key_here
   SESSION_LIFETIME=3600
   
   # Email Configuration (optional)
   SMTP_HOST=your_smtp_host
   SMTP_PORT=587
   SMTP_USERNAME=your_smtp_username
   SMTP_PASSWORD=your_smtp_password
   SMTP_ENCRYPTION=tls
   ```

3. **Run Live Setup**
   ```bash
   php setup_live.php
   ```

4. **Set Permissions** (if needed)
   ```bash
   chmod 755 uploads logs temp backups cache sessions
   chmod 644 .env
   ```

5. **Access Application**
   - Visit your domain: `https://yourdomain.com`
   - Login with default admin credentials:
     - Email: `admin@autocrm.com`
     - Password: `admin123`

## Database Setup

### Manual Database Creation

If you prefer to set up the database manually:

1. **Create Database**
   ```sql
   CREATE DATABASE autocrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   ```bash
   mysql -u your_username -p autocrm < database/schema.sql
   ```

3. **Run Migrations**
   ```bash
   php database/migrate.php
   ```

### Database Structure

The application creates the following tables:

- **users** - User accounts and authentication
- **contacts** - Customer contact information
- **email_campaigns** - Email marketing campaigns
- **email_templates** - Reusable email templates

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check if MySQL service is running
   - Verify database credentials in `.env` file
   - Ensure database exists and user has proper permissions

2. **404 Errors**
   - Check if `.htaccess` file exists
   - Ensure Apache mod_rewrite is enabled
   - Verify web server points to correct directory

3. **Permission Errors**
   - Set proper permissions on directories:
     ```bash
     chmod 755 uploads logs temp backups cache sessions
     ```
   - Ensure web server can write to these directories

4. **Signup/Login Not Working**
   - Check browser console for JavaScript errors
   - Verify API endpoints are accessible
   - Check server error logs

### Debug Mode

To enable debug mode for troubleshooting:

1. **Local Development**
   - Edit `.env` file and set: `APP_DEBUG=true`

2. **Check Logs**
   - Application logs: `logs/app.log`
   - Server logs: Check your web server's error log

### Security Checklist

- [ ] Change default admin password
- [ ] Set strong JWT_SECRET in `.env`
- [ ] Use HTTPS in production
- [ ] Set proper file permissions
- [ ] Keep PHP and dependencies updated
- [ ] Regularly backup database

## API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/profile` - Get user profile
- `PUT /api/auth/profile` - Update user profile

### Contacts
- `GET /api/contacts` - List contacts
- `POST /api/contacts` - Create contact
- `GET /api/contacts/{id}` - Get contact
- `PUT /api/contacts/{id}` - Update contact
- `DELETE /api/contacts/{id}` - Delete contact

### Email Campaigns
- `GET /api/campaigns` - List campaigns
- `POST /api/campaigns` - Create campaign
- `GET /api/campaigns/{id}` - Get campaign
- `PUT /api/campaigns/{id}` - Update campaign
- `DELETE /api/campaigns/{id}` - Delete campaign

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review server error logs
3. Enable debug mode for detailed error messages
4. Ensure all requirements are met

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- PDO MySQL extension
- JSON extension
- MBString extension 