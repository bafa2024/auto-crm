# Testing Scheduled Email Campaigns

This directory contains test scripts to verify scheduled email functionality works correctly.

## Test Scripts

### 1. test_email_config.php
Checks your email configuration settings.

```bash
cd C:\xampp\htdocs\acrm
php tests/scheduled_email/test_email_config.php
```

### 2. test_immediate_email.php
Tests sending an email immediately to verify email service works.

```bash
php tests/scheduled_email/test_immediate_email.php
```

### 3. test_scheduled_email.php
Creates a test scheduled campaign set for 1 minute in the future.

```bash
php tests/scheduled_email/test_scheduled_email.php
```

### 4. test_process_scheduled.php
Manually runs the scheduled campaign processor.

```bash
php tests/scheduled_email/test_process_scheduled.php
```

## Testing Process

1. **First, check email configuration:**
   ```bash
   php tests/scheduled_email/test_email_config.php
   ```
   Make sure SMTP credentials are set if using SMTP.

2. **Test immediate email sending:**
   ```bash
   php tests/scheduled_email/test_immediate_email.php
   ```
   This verifies the email service can send emails.

3. **Create a scheduled campaign:**
   ```bash
   php tests/scheduled_email/test_scheduled_email.php
   ```
   This creates a campaign scheduled for 1 minute from now.

4. **Wait 1 minute, then process:**
   ```bash
   php tests/scheduled_email/test_process_scheduled.php
   ```
   This should process and send the scheduled campaign.

## Troubleshooting

- **Emails not sending:** Check config/email.php for SMTP settings
- **Campaign not processing:** Ensure schedule_date is in the past
- **No recipients:** Check that email_recipients table has data
- **Database errors:** Verify database connection in config/database.php

## Running via Cron Job

For production, the processor should run automatically:
```bash
php C:\xampp\htdocs\acrm\cron\process_scheduled_campaigns.php
```

This should be scheduled to run every minute via Windows Task Scheduler or crontab.