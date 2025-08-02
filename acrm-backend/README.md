# ACRM Backend API

A complete Node.js backend API for the ACRM (Advanced Customer Relationship Management) system. This backend provides authentication, contact management, email campaigns, and more.

## Features

- 🔐 **Authentication & Authorization**: JWT-based authentication with role-based access control
- 👥 **User Management**: Admin, manager, and agent roles with different permissions
- 📇 **Contact Management**: CRUD operations with bulk import/export
- 📧 **Email Campaigns**: Create, schedule, and track email campaigns
- 🗄️ **Database Support**: Automatic switching between SQLite (local) and MySQL (production)
- 📊 **Statistics & Analytics**: Contact and campaign statistics
- 🔒 **Security**: Rate limiting, CORS, helmet, input validation
- 📁 **File Upload**: Excel and CSV file processing for bulk operations

## Tech Stack

- **Runtime**: Node.js
- **Framework**: Express.js
- **Database**: SQLite (local) / MySQL (production)
- **Authentication**: JWT (JSON Web Tokens)
- **Security**: bcryptjs, helmet, express-rate-limit
- **File Processing**: multer, xlsx, csv-parser
- **Email**: nodemailer (for future email sending)

## Quick Start

### Prerequisites

- Node.js (v14 or higher)
- npm or yarn
- MySQL (for production) or SQLite (for local development)

### Installation

1. **Clone the repository**
   ```bash
   cd acrm-backend
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Environment Configuration**
   ```bash
   cp env.example .env
   ```
   
   Edit `.env` file with your configuration:
   ```env
   # Database Configuration
   DB_ENVIRONMENT=local
   DB_HOST=localhost
   DB_PORT=3306
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=acrm
   DB_SQLITE_PATH=./database.sqlite

   # JWT Configuration
   JWT_SECRET=your-super-secret-jwt-key-change-this-in-production
   JWT_EXPIRES_IN=24h

   # Server Configuration
   PORT=3001
   NODE_ENV=development

   # CORS Configuration
   CORS_ORIGIN=http://localhost:3000

   # File Upload Configuration
   UPLOAD_PATH=./uploads
   MAX_FILE_SIZE=10485760
   ```

4. **Database Setup**
   ```bash
   # Run database migration
   npm run migrate
   
   # Seed initial data
   npm run seed
   ```

5. **Start the server**
   ```bash
   # Development mode
   npm run dev
   
   # Production mode
   npm start
   ```

The API will be available at `http://localhost:3001`

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/employee/login` | Employee login |
| GET | `/api/auth/profile` | Get user profile |
| PUT | `/api/auth/profile` | Update user profile |
| PUT | `/api/auth/change-password` | Change password |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/auth/refresh-token` | Refresh JWT token |

### Contacts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/contacts` | Get all contacts (with pagination) |
| GET | `/api/contacts/:id` | Get single contact |
| POST | `/api/contacts` | Create new contact |
| PUT | `/api/contacts/:id` | Update contact |
| DELETE | `/api/contacts/:id` | Delete contact |
| GET | `/api/contacts/stats` | Get contact statistics |
| GET | `/api/contacts/export` | Export contacts to CSV |
| POST | `/api/contacts/bulk-upload` | Bulk upload contacts from file |
| DELETE | `/api/contacts/delete-all` | Delete all contacts (admin only) |

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard/stats` | Get dashboard statistics |

### Health Check

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check endpoint |
| GET | `/api/test-db` | Database connection test |

## Database Configuration

The backend automatically detects the environment and switches between databases:

- **Local Development**: Uses SQLite (file-based database)
- **Production**: Uses MySQL

### Environment Detection

The system detects the environment based on:
- Server name (localhost, xampp, etc.)
- Document root path
- Environment variables

### Manual Override

You can override the database selection by setting:
```env
DB_ENVIRONMENT=local  # Force SQLite
DB_ENVIRONMENT=live   # Force MySQL
```

## User Roles

### Admin
- Full access to all features
- Can manage users and system settings
- Can delete all contacts

### Manager
- Can manage contacts and campaigns
- Can view statistics and reports
- Cannot delete all contacts

### Agent
- Can view and edit contacts
- Can create basic campaigns
- Limited access to statistics

## File Upload

The system supports bulk contact import from:
- Excel files (.xlsx, .xls)
- CSV files (.csv)

### Upload Configuration
```env
UPLOAD_PATH=./uploads
MAX_FILE_SIZE=10485760  # 10MB
```

## Security Features

- **Rate Limiting**: Configurable rate limiting per IP
- **CORS**: Cross-origin resource sharing configuration
- **Helmet**: Security headers
- **Input Validation**: Request validation and sanitization
- **JWT Authentication**: Secure token-based authentication
- **Password Hashing**: bcryptjs for secure password storage

## Development

### Scripts

```bash
# Start development server
npm run dev

# Start production server
npm start

# Run tests
npm test

# Database migration
npm run migrate

# Seed database
npm run seed
```

### Project Structure

```
acrm-backend/
├── config/
│   └── database.js          # Database configuration
├── controllers/
│   ├── AuthController.js    # Authentication logic
│   └── ContactController.js # Contact management
├── middleware/
│   └── auth.js             # Authentication middleware
├── models/
│   ├── BaseModel.js        # Base model class
│   ├── User.js            # User model
│   ├── Contact.js         # Contact model
│   └── EmailCampaign.js   # Email campaign model
├── routes/
│   ├── auth.js            # Authentication routes
│   └── contacts.js        # Contact routes
├── scripts/
│   ├── migrate.js         # Database migration
│   └── seed.js           # Database seeding
├── uploads/               # File upload directory
├── server.js             # Main server file
├── package.json
└── README.md
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_ENVIRONMENT` | Database environment (local/live) | auto-detected |
| `DB_HOST` | MySQL host | localhost |
| `DB_PORT` | MySQL port | 3306 |
| `DB_USER` | MySQL username | root |
| `DB_PASSWORD` | MySQL password | empty |
| `DB_NAME` | MySQL database name | acrm |
| `DB_SQLITE_PATH` | SQLite database path | ./database.sqlite |
| `JWT_SECRET` | JWT secret key | required |
| `JWT_EXPIRES_IN` | JWT expiration time | 24h |
| `PORT` | Server port | 3001 |
| `NODE_ENV` | Environment | development |
| `CORS_ORIGIN` | CORS origin | http://localhost:3000 |
| `UPLOAD_PATH` | File upload directory | ./uploads |
| `MAX_FILE_SIZE` | Maximum file size | 10485760 |
| `RATE_LIMIT_WINDOW` | Rate limit window (minutes) | 15 |
| `RATE_LIMIT_MAX` | Rate limit max requests | 100 |

## Testing

### Manual Testing

1. **Health Check**
   ```bash
   curl http://localhost:3001/health
   ```

2. **Database Test**
   ```bash
   curl http://localhost:3001/api/test-db
   ```

3. **Authentication**
   ```bash
   # Register
   curl -X POST http://localhost:3001/api/auth/register \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","password":"password123","first_name":"Test","last_name":"User"}'
   
   # Login
   curl -X POST http://localhost:3001/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","password":"password123"}'
   ```

## Deployment

### Production Setup

1. **Environment Configuration**
   ```bash
   NODE_ENV=production
   DB_ENVIRONMENT=live
   JWT_SECRET=your-production-secret-key
   ```

2. **Database Setup**
   ```bash
   # Create MySQL database
   CREATE DATABASE acrm;
   
   # Run migration
   npm run migrate
   ```

3. **Process Management**
   ```bash
   # Using PM2
   npm install -g pm2
   pm2 start server.js --name acrm-backend
   ```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `.env`
   - Ensure database server is running
   - Verify database exists

2. **CORS Errors**
   - Check `CORS_ORIGIN` in `.env`
   - Ensure frontend URL is correct

3. **File Upload Issues**
   - Check `UPLOAD_PATH` directory exists
   - Verify file size limits
   - Check file format (Excel/CSV only)

4. **Authentication Errors**
   - Verify JWT_SECRET is set
   - Check token expiration
   - Ensure user exists and is active

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the ISC License.

## Support

For support and questions, please contact the development team. 