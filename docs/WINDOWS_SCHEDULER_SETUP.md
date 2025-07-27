# Windows Task Scheduler Setup for Email Campaigns

This guide will help you set up automatic email campaign processing on Windows using Task Scheduler.

## Prerequisites

- XAMPP installed with PHP
- AutoCRM system running
- Administrator access to Windows

## Setup Steps

### 1. Open Task Scheduler

1. Press `Win + R` to open Run dialog
2. Type `taskschd.msc` and press Enter
3. Task Scheduler will open

### 2. Create a New Task

1. In the right panel, click "Create Basic Task..."
2. Name: "AutoCRM Email Campaign Processor"
3. Description: "Processes scheduled email campaigns every minute"
4. Click "Next"

### 3. Set Trigger

1. Choose "Daily"
2. Set start date and time to current
3. Click "Next"
4. In "Action", choose "Start a program"
5. Click "Next"

### 4. Set Action

1. Program/script: Browse to `C:\xampp\htdocs\acrm\cron\windows_scheduler.bat`
2. Start in: `C:\xampp\htdocs\acrm\cron`
3. Click "Next"

### 5. Finish and Configure Advanced Settings

1. Check "Open the Properties dialog for this task when I click Finish"
2. Click "Finish"

### 6. Configure Advanced Properties

In the Properties dialog:

#### General Tab:
- Check "Run whether user is logged on or not"
- Check "Run with highest privileges"
- Configure for: Windows 10 (or your version)

#### Triggers Tab:
1. Select the trigger and click "Edit"
2. Under "Advanced settings":
   - Check "Repeat task every: 1 minutes"
   - For a duration of: Indefinitely
   - Check "Enabled"
3. Click "OK"

#### Settings Tab:
- Check "Allow task to be run on demand"
- Check "Run task as soon as possible after a scheduled start is missed"
- Uncheck "Stop the task if it runs longer than: 3 days"

### 7. Save and Test

1. Enter your Windows password when prompted
2. Right-click the task and select "Run" to test
3. Check `C:\xampp\htdocs\acrm\logs\campaign_cron.log` for execution logs

## Alternative: Using XAMPP's Built-in Cron

If you have XAMPP with cron support:

1. Add to crontab:
   ```
   * * * * * C:/xampp/php/php.exe C:/xampp/htdocs/acrm/cron/process_campaigns.php
   ```

## Troubleshooting

### Task Not Running
- Ensure XAMPP/Apache is running
- Check Windows Event Viewer for errors
- Verify paths in `windows_scheduler.bat`

### PHP Errors
- Check PHP error log in `C:\xampp\php\logs`
- Ensure database is accessible
- Check email configuration in `.env`

### Email Not Sending
- Verify SMTP settings in `.env`
- Check firewall settings for SMTP port
- Test email configuration manually

## Monitoring

To monitor campaign processing:

1. Check campaign status in the web interface
2. Review logs in `logs/campaign_cron.log`
3. Monitor email_campaigns table status changes
4. Check email_recipients table for sent emails

## Performance Tips

- Adjust batch_size in `config/email.php` based on your server
- Increase delay_between_emails for shared hosting
- Monitor server resources during sending
- Consider running during off-peak hours for large campaigns