const jwt = require('jsonwebtoken');
const User = require('../models/User');
const Database = require('../config/database');

/**
 * Verify JWT token middleware
 */
const verifyToken = async (req, res, next) => {
    try {
        const token = req.headers.authorization?.split(' ')[1] || 
                     req.cookies?.token || 
                     req.query?.token;

        if (!token) {
            return res.status(401).json({
                success: false,
                message: 'Access token required'
            });
        }

        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        const db = new Database();
        const userModel = new User(db);
        const user = await userModel.findById(decoded.userId);

        if (!user) {
            return res.status(401).json({
                success: false,
                message: 'Invalid token'
            });
        }

        req.user = user;
        next();
    } catch (error) {
        console.error('Token verification error:', error);
        return res.status(401).json({
            success: false,
            message: 'Invalid token'
        });
    }
};

/**
 * Optional token verification middleware
 */
const optionalAuth = async (req, res, next) => {
    try {
        const token = req.headers.authorization?.split(' ')[1] || 
                     req.cookies?.token || 
                     req.query?.token;

        if (token) {
            const decoded = jwt.verify(token, process.env.JWT_SECRET);
            const db = new Database();
            const userModel = new User(db);
            const user = await userModel.findById(decoded.userId);

            if (user) {
                req.user = user;
            }
        }

        next();
    } catch (error) {
        // Continue without authentication
        next();
    }
};

/**
 * Role-based authorization middleware
 */
const requireRole = (roles) => {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({
                success: false,
                message: 'Authentication required'
            });
        }

        if (!roles.includes(req.user.role)) {
            return res.status(403).json({
                success: false,
                message: 'Insufficient permissions'
            });
        }

        next();
    };
};

/**
 * Admin authorization middleware
 */
const requireAdmin = (req, res, next) => {
    if (!req.user) {
        return res.status(401).json({
            success: false,
            message: 'Authentication required'
        });
    }

    if (req.user.role !== 'admin') {
        return res.status(403).json({
            success: false,
            message: 'Admin access required'
        });
    }

    next();
};

/**
 * Employee authorization middleware
 */
const requireEmployee = (req, res, next) => {
    if (!req.user) {
        return res.status(401).json({
            success: false,
            message: 'Authentication required'
        });
    }

    if (!['agent', 'manager', 'admin'].includes(req.user.role)) {
        return res.status(403).json({
            success: false,
            message: 'Employee access required'
        });
    }

    next();
};

module.exports = {
    verifyToken,
    optionalAuth,
    requireRole,
    requireAdmin,
    requireEmployee
}; 