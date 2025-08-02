const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');
const User = require('../models/User');
const Database = require('../config/database');

class AuthController {
    constructor() {
        this.db = new Database();
        this.userModel = new User(this.db);
    }

    /**
     * User registration
     */
    async register(req, res) {
        try {
            const { email, password, first_name, last_name, company_name, phone } = req.body;

            // Validate required fields
            if (!email || !password || !first_name || !last_name) {
                return res.status(400).json({
                    success: false,
                    message: 'Email, password, first name, and last name are required'
                });
            }

            // Check if user already exists
            const existingUser = await this.userModel.findByEmail(email);
            if (existingUser) {
                return res.status(400).json({
                    success: false,
                    message: 'User with this email already exists'
                });
            }

            // Create user
            const userData = {
                email,
                password,
                first_name,
                last_name,
                company_name: company_name || '',
                phone: phone || '',
                role: 'admin',
                status: 'active'
            };

            const user = await this.userModel.create(userData);

            // Generate JWT token
            const token = jwt.sign(
                { userId: user.id, email: user.email, role: user.role },
                process.env.JWT_SECRET,
                { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
            );

            res.status(201).json({
                success: true,
                message: 'User registered successfully',
                data: {
                    user: user,
                    token
                }
            });
        } catch (error) {
            console.error('Registration error:', error);
            res.status(500).json({
                success: false,
                message: 'Registration failed',
                error: error.message
            });
        }
    }

    /**
     * User login
     */
    async login(req, res) {
        try {
            const { email, password } = req.body;

            // Validate required fields
            if (!email || !password) {
                return res.status(400).json({
                    success: false,
                    message: 'Email and password are required'
                });
            }

            // Authenticate user
            const user = await this.userModel.authenticate(email, password);
            if (!user) {
                return res.status(401).json({
                    success: false,
                    message: 'Invalid email or password'
                });
            }

            // Generate JWT token
            const token = jwt.sign(
                { userId: user.id, email: user.email, role: user.role },
                process.env.JWT_SECRET,
                { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
            );

            res.json({
                success: true,
                message: 'Login successful',
                data: {
                    user: user,
                    token
                }
            });
        } catch (error) {
            console.error('Login error:', error);
            res.status(500).json({
                success: false,
                message: 'Login failed',
                error: error.message
            });
        }
    }

    /**
     * Employee login (simplified)
     */
    async employeeLogin(req, res) {
        try {
            const { email, password } = req.body;

            // Validate required fields
            if (!email || !password) {
                return res.status(400).json({
                    success: false,
                    message: 'Email and password are required'
                });
            }

            // Authenticate user
            const user = await this.userModel.authenticate(email, password);
            if (!user) {
                return res.status(401).json({
                    success: false,
                    message: 'Invalid email or password'
                });
            }

            // Check if user is employee
            if (!['agent', 'manager'].includes(user.role)) {
                return res.status(403).json({
                    success: false,
                    message: 'Employee access only'
                });
            }

            // Generate JWT token
            const token = jwt.sign(
                { userId: user.id, email: user.email, role: user.role },
                process.env.JWT_SECRET,
                { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
            );

            res.json({
                success: true,
                message: 'Employee login successful',
                data: {
                    user: user,
                    token
                }
            });
        } catch (error) {
            console.error('Employee login error:', error);
            res.status(500).json({
                success: false,
                message: 'Employee login failed',
                error: error.message
            });
        }
    }

    /**
     * Get current user profile
     */
    async getProfile(req, res) {
        try {
            const user = await this.userModel.findById(req.user.id);
            if (!user) {
                return res.status(404).json({
                    success: false,
                    message: 'User not found'
                });
            }

            res.json({
                success: true,
                data: { user }
            });
        } catch (error) {
            console.error('Get profile error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to get profile',
                error: error.message
            });
        }
    }

    /**
     * Update user profile
     */
    async updateProfile(req, res) {
        try {
            const { first_name, last_name, company_name, phone } = req.body;
            const updateData = {};

            if (first_name) updateData.first_name = first_name;
            if (last_name) updateData.last_name = last_name;
            if (company_name !== undefined) updateData.company_name = company_name;
            if (phone !== undefined) updateData.phone = phone;

            const updatedUser = await this.userModel.update(req.user.id, updateData);

            res.json({
                success: true,
                message: 'Profile updated successfully',
                data: { user: updatedUser }
            });
        } catch (error) {
            console.error('Update profile error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to update profile',
                error: error.message
            });
        }
    }

    /**
     * Change password
     */
    async changePassword(req, res) {
        try {
            const { current_password, new_password } = req.body;

            if (!current_password || !new_password) {
                return res.status(400).json({
                    success: false,
                    message: 'Current password and new password are required'
                });
            }

            // Verify current password
            const user = await this.userModel.findById(req.user.id);
            if (!user) {
                return res.status(404).json({
                    success: false,
                    message: 'User not found'
                });
            }

            let isValidPassword = false;
            if (['agent', 'manager'].includes(user.role)) {
                // For employees, check plain text password
                isValidPassword = current_password === user.password;
            } else {
                // For admin users, check hashed password
                isValidPassword = await bcrypt.compare(current_password, user.password);
            }

            if (!isValidPassword) {
                return res.status(400).json({
                    success: false,
                    message: 'Current password is incorrect'
                });
            }

            // Update password
            await this.userModel.updatePassword(req.user.id, new_password);

            res.json({
                success: true,
                message: 'Password changed successfully'
            });
        } catch (error) {
            console.error('Change password error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to change password',
                error: error.message
            });
        }
    }

    /**
     * Logout
     */
    async logout(req, res) {
        try {
            // In a stateless JWT system, logout is handled client-side
            // by removing the token. Server-side, we could implement a
            // blacklist if needed for additional security.
            
            res.json({
                success: true,
                message: 'Logged out successfully'
            });
        } catch (error) {
            console.error('Logout error:', error);
            res.status(500).json({
                success: false,
                message: 'Logout failed',
                error: error.message
            });
        }
    }

    /**
     * Refresh token
     */
    async refreshToken(req, res) {
        try {
            const user = await this.userModel.findById(req.user.id);
            if (!user) {
                return res.status(404).json({
                    success: false,
                    message: 'User not found'
                });
            }

            // Generate new JWT token
            const token = jwt.sign(
                { userId: user.id, email: user.email, role: user.role },
                process.env.JWT_SECRET,
                { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
            );

            res.json({
                success: true,
                message: 'Token refreshed successfully',
                data: { token }
            });
        } catch (error) {
            console.error('Refresh token error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to refresh token',
                error: error.message
            });
        }
    }
}

module.exports = AuthController; 