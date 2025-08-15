@echo off
REM Email Queue Processor for AutoDial Pro CRM
REM Run this batch file to process pending emails in the queue

echo.
echo ========================================
echo AutoDial Pro - Email Queue Processor
echo ========================================
echo.

REM Change to the application directory
cd /d C:\xampp\htdocs\acrm

REM Run the email queue processor
C:\xampp\php\php.exe cron\process_email_queue.php

echo.
echo Process completed. Press any key to exit...
pause > nul