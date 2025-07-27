@echo off
REM Windows batch file to process email campaigns
REM Schedule this with Windows Task Scheduler to run every minute

REM Change this path to your PHP installation
set PHP_PATH=C:\xampp\php\php.exe

REM Change this path to your project location
set PROJECT_PATH=C:\xampp\htdocs\acrm

REM Run the campaign processor
"%PHP_PATH%" "%PROJECT_PATH%\cron\process_campaigns.php"

REM Optional: Log the execution
echo Campaign processing completed at %date% %time% >> "%PROJECT_PATH%\logs\campaign_cron.log"