@echo off
echo === Testing Scheduled Campaign Processor ===
echo.

REM Check if PHP is available
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: PHP not found in PATH!
    echo Please add PHP to your system PATH or use full path to php.exe
    echo Example: C:\xampp\php\php.exe
    pause
    exit /b 1
)

echo Running scheduled campaign processor...
echo.

REM Run the cron job
php "%~dp0process_scheduled_campaigns.php"

echo.
echo === Test Complete ===
echo.
echo To run this automatically every minute:
echo 1. Open Task Scheduler (taskschd.msc)
echo 2. Create Basic Task
echo 3. Set trigger to "Daily" and repeat every 1 minute
echo 4. Set action to run this batch file
echo.
pause