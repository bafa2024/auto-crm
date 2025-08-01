#!/bin/bash

# ACRM Docker Quick Start Script

echo "🚀 Starting ACRM Docker Environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose is not installed. Please install Docker Compose and try again."
    exit 1
fi

# Create necessary directories if they don't exist
echo "📁 Creating necessary directories..."
mkdir -p logs uploads temp sessions cache database

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 777 logs uploads temp sessions cache database 2>/dev/null || true

# Build and start containers
echo "🔨 Building and starting containers..."
docker-compose up -d --build

# Wait for containers to be ready
echo "⏳ Waiting for containers to be ready..."
sleep 10

# Check container status
echo "📊 Container status:"
docker-compose ps

# Show access information
echo ""
echo "✅ ACRM is now running!"
echo ""
echo "🌐 Access URLs:"
echo "   - ACRM Application: http://localhost:8080"
echo "   - phpMyAdmin: http://localhost:8081"
echo ""
echo "🔑 Default Credentials:"
echo "   - Email: admin@acrm.local"
echo "   - Password: password"
echo ""
echo "📝 Useful Commands:"
echo "   - View logs: docker-compose logs -f"
echo "   - Stop services: docker-compose down"
echo "   - Restart services: docker-compose restart"
echo "   - Access web container: docker-compose exec web bash"
echo ""

# Check if containers are healthy
if docker-compose ps | grep -q "Up"; then
    echo "🎉 All containers are running successfully!"
else
    echo "⚠️  Some containers may not be running. Check logs with: docker-compose logs"
fi 