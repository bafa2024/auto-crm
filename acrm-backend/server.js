require('dotenv').config();
const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const path = require('path');
const fs = require('fs');

// Import routes
const authRoutes = require('./routes/auth');
const contactRoutes = require('./routes/contacts');

// Import database
const Database = require('./config/database');

const app = express();
const PORT = process.env.PORT || 3001;

// Create uploads directory if it doesn't exist
const uploadsDir = process.env.UPLOAD_PATH || './uploads';
if (!fs.existsSync(uploadsDir)) {
    fs.mkdirSync(uploadsDir, { recursive: true });
}

// Security middleware
app.use(helmet());

// CORS configuration
const corsOptions = {
    origin: process.env.CORS_ORIGIN || 'http://localhost:3000',
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With']
};
app.use(cors(corsOptions));

// Rate limiting
const limiter = rateLimit({
    windowMs: (process.env.RATE_LIMIT_WINDOW || 15) * 60 * 1000, // 15 minutes
    max: process.env.RATE_LIMIT_MAX || 100, // limit each IP to 100 requests per windowMs
    message: {
        success: false,
        message: 'Too many requests from this IP, please try again later.'
    }
});
app.use(limiter);

// Body parsing middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Static files
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        success: true,
        message: 'ACRM Backend API is running',
        timestamp: new Date().toISOString(),
        environment: process.env.NODE_ENV || 'development'
    });
});

// Database test endpoint
app.get('/api/test-db', async (req, res) => {
    try {
        const db = new Database();
        const testResult = await db.testConnection();
        const dbInfo = await db.getDatabaseInfo();
        
        res.json({
            success: true,
            data: {
                connection: testResult,
                database_info: dbInfo
            }
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: 'Database test failed',
            error: error.message
        });
    }
});

// API routes
app.use('/api/auth', authRoutes);
app.use('/api/contacts', contactRoutes);

// Dashboard routes (for future use)
app.get('/api/dashboard/stats', async (req, res) => {
    try {
        const db = new Database();
        const Contact = require('./models/Contact');
        const User = require('./models/User');
        const EmailCampaign = require('./models/EmailCampaign');

        const contactModel = new Contact(db);
        const userModel = new User(db);
        const campaignModel = new EmailCampaign(db);

        const [totalContacts, totalUsers, totalCampaigns] = await Promise.all([
            contactModel.count(),
            userModel.count(),
            campaignModel.count()
        ]);

        res.json({
            success: true,
            data: {
                total_contacts: totalContacts,
                total_users: totalUsers,
                total_campaigns: totalCampaigns
            }
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: 'Failed to get dashboard stats',
            error: error.message
        });
    }
});

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({
        success: false,
        message: 'Endpoint not found',
        path: req.originalUrl
    });
});

// Global error handler
app.use((error, req, res, next) => {
    console.error('Global error handler:', error);
    
    // Handle multer errors
    if (error.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({
            success: false,
            message: 'File too large. Maximum size is 10MB.'
        });
    }
    
    if (error.message === 'Only Excel and CSV files are allowed') {
        return res.status(400).json({
            success: false,
            message: error.message
        });
    }

    res.status(500).json({
        success: false,
        message: 'Internal server error',
        error: process.env.NODE_ENV === 'development' ? error.message : 'Something went wrong'
    });
});

// Graceful shutdown
process.on('SIGTERM', async () => {
    console.log('SIGTERM received, shutting down gracefully');
    const db = new Database();
    await db.close();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('SIGINT received, shutting down gracefully');
    const db = new Database();
    await db.close();
    process.exit(0);
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 ACRM Backend API server running on port ${PORT}`);
    console.log(`📊 Environment: ${process.env.NODE_ENV || 'development'}`);
    console.log(`🌐 CORS Origin: ${process.env.CORS_ORIGIN || 'http://localhost:3000'}`);
    console.log(`💾 Database: ${process.env.DB_ENVIRONMENT || 'auto-detected'}`);
    console.log(`📁 Uploads directory: ${uploadsDir}`);
});

module.exports = app; 