@echo off
REM ACRM Docker Quick Start Script for Windows

echo 🚀 Starting ACRM Docker Environment...

REM Check if Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker is not running. Please start Docker Desktop and try again.
    pause
    exit /b 1
)

REM Check if docker-compose is available
docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo ❌ docker-compose is not installed. Please install Docker Compose and try again.
    pause
    exit /b 1
)

REM Create necessary directories if they don't exist
echo 📁 Creating necessary directories...
if not exist "logs" mkdir logs
if not exist "uploads" mkdir uploads
if not exist "temp" mkdir temp
if not exist "sessions" mkdir sessions
if not exist "cache" mkdir cache
if not exist "database" mkdir database

REM Build and start containers
echo 🔨 Building and starting containers...
docker-compose up -d --build

REM Wait for containers to be ready
echo ⏳ Waiting for containers to be ready...
timeout /t 10 /nobreak >nul

REM Check container status
echo 📊 Container status:
docker-compose ps

REM Show access information
echo.
echo ✅ ACRM is now running!
echo.
echo 🌐 Access URLs:
echo    - ACRM Application: http://localhost:8080
echo    - phpMyAdmin: http://localhost:8081
echo.
echo 🔑 Default Credentials:
echo    - Email: admin@acrm.local
echo    - Password: password
echo.
echo 📝 Useful Commands:
echo    - View logs: docker-compose logs -f
echo    - Stop services: docker-compose down
echo    - Restart services: docker-compose restart
echo    - Access web container: docker-compose exec web bash
echo.

REM Check if containers are healthy
docker-compose ps | findstr "Up" >nul
if errorlevel 1 (
    echo ⚠️  Some containers may not be running. Check logs with: docker-compose logs
) else (
    echo 🎉 All containers are running successfully!
)

pause 