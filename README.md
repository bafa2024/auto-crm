# ACRM Backend API

A modern Node.js + Express backend for the ACRM (Advanced Customer Relationship Management) system.

## 🚀 Features

- **RESTful API** with Express.js
- **MySQL Database** with connection pooling
- **JWT Authentication** for secure user sessions
- **Contact Management** with search and filtering
- **Email Campaign Management**
- **User Management** with role-based access
- **Error Handling** and validation
- **CORS Support** for frontend integration

## 📋 Prerequisites

- Node.js (v14 or higher)
- MySQL (v8.0 or higher)
- npm or yarn

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd acrm-backend
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Set up environment variables**
   ```bash
   cp env.example .env
   ```
   Edit `.env` file with your database credentials and JWT secret.

4. **Set up the database**
   ```sql
   -- Create database
   CREATE DATABASE acrm;
   USE acrm;
   
   -- Create users table
   CREATE TABLE users (
     id INT AUTO_INCREMENT PRIMARY KEY,
     first_name VARCHAR(100) NOT NULL,
     last_name VARCHAR(100) NOT NULL,
     email VARCHAR(255) UNIQUE NOT NULL,
     password VARCHAR(255) NOT NULL,
     role ENUM('admin', 'manager', 'agent') DEFAULT 'agent',
     status ENUM('active', 'inactive') DEFAULT 'active',
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   
   -- Create contacts table
   CREATE TABLE contacts (
     id INT AUTO_INCREMENT PRIMARY KEY,
     first_name VARCHAR(100) NOT NULL,
     last_name VARCHAR(100) NOT NULL,
     email VARCHAR(255) NOT NULL,
     phone VARCHAR(20),
     company VARCHAR(255),
     status ENUM('active', 'inactive') DEFAULT 'active',
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   
   -- Create email_campaigns table
   CREATE TABLE email_campaigns (
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(255) NOT NULL,
     subject VARCHAR(255) NOT NULL,
     content TEXT NOT NULL,
     status ENUM('draft', 'scheduled', 'sending', 'sent', 'cancelled') DEFAULT 'draft',
     scheduled_at TIMESTAMP NULL,
     sent_at TIMESTAMP NULL,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

5. **Start the server**
   ```bash
   # Development mode
   npm run dev
   
   # Production mode
   npm start
   ```

## 📚 API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `GET /api/auth/me` - Get current user

### Contacts
- `GET /api/contacts` - Get all contacts (with search/filtering)
- `GET /api/contacts/:id` - Get single contact
- `POST /api/contacts` - Create new contact
- `PUT /api/contacts/:id` - Update contact
- `DELETE /api/contacts/:id` - Delete contact
- `DELETE /api/contacts` - Delete all contacts

### Campaigns
- `GET /api/campaigns` - Get all campaigns
- `GET /api/campaigns/:id` - Get single campaign
- `POST /api/campaigns` - Create new campaign
- `PUT /api/campaigns/:id` - Update campaign
- `DELETE /api/campaigns/:id` - Delete campaign
- `POST /api/campaigns/:id/send` - Send campaign

### Health Check
- `GET /api/health` - API health status

## 🔧 Environment Variables

```env
# Server Configuration
PORT=3001
NODE_ENV=development

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=acrm
DB_PORT=3306

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-change-this-in-production
JWT_EXPIRES_IN=24h

# CORS Configuration
CORS_ORIGIN=http://localhost:5173
```

## 🏗️ Project Structure

```
src/
├── config/
│   └── database.js          # Database configuration
├── routes/
│   ├── auth.js              # Authentication routes
│   ├── contacts.js          # Contact management routes
│   └── campaigns.js         # Campaign management routes
└── index.js                 # Main server file
```

## 🔒 Security Features

- **Helmet.js** for security headers
- **CORS** configuration for frontend integration
- **JWT** token-based authentication
- **Password hashing** with bcryptjs
- **Input validation** and sanitization
- **Error handling** with proper HTTP status codes

## 🚀 Development

```bash
# Start development server with hot reload
npm run dev

# Start production server
npm start

# Check API health
curl http://localhost:3001/api/health
```

## 📊 Database Schema

The application uses MySQL with the following main tables:

- **users** - User accounts and authentication
- **contacts** - Customer contact information
- **email_campaigns** - Email marketing campaigns

## 🔗 Frontend Integration

This backend is designed to work with the React frontend. The API provides:

- RESTful endpoints for all CRUD operations
- JSON responses with consistent structure
- CORS support for cross-origin requests
- JWT authentication for secure API access

## 🐛 Error Handling

All API endpoints return consistent error responses:

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error (development only)"
}
```

## 📝 License

This project is licensed under the ISC License. 