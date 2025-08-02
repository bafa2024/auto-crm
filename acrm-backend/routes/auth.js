const express = require('express');
const router = express.Router();
const AuthController = require('../controllers/AuthController');
const { verifyToken } = require('../middleware/auth');

const authController = new AuthController();

// Public routes
router.post('/register', authController.register.bind(authController));
router.post('/login', authController.login.bind(authController));
router.post('/employee/login', authController.employeeLogin.bind(authController));

// Protected routes
router.get('/profile', verifyToken, authController.getProfile.bind(authController));
router.put('/profile', verifyToken, authController.updateProfile.bind(authController));
router.put('/change-password', verifyToken, authController.changePassword.bind(authController));
router.post('/logout', verifyToken, authController.logout.bind(authController));
router.post('/refresh-token', verifyToken, authController.refreshToken.bind(authController));

module.exports = router; 