@echo off
echo Starting ACRM Email Upload Application
echo ======================================
echo.

echo Checking setup...
php check_local_setup.php

echo.
echo Starting PHP server on localhost:8080
echo.
echo Access URLs:
echo - Main Upload Interface: http://localhost:8080/test_email_upload_form.php
echo - System Status: http://localhost:8080/check_local_setup.php
echo - Download Template: http://localhost:8080/download_template.php
echo.
echo Press Ctrl+C to stop the server
echo.

php -S localhost:8080