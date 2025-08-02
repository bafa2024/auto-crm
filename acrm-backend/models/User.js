const BaseModel = require('./BaseModel');
const bcrypt = require('bcryptjs');

class User extends BaseModel {
    constructor(db) {
        super(db, 'users');
        this.fillable = [
            'email', 'password', 'first_name', 'last_name', 
            'company_name', 'phone', 'role', 'status'
        ];
        this.hidden = ['password'];
    }

    /**
     * Create a new user with password hashing
     */
    async create(data) {
        if (data.password) {
            // Check if user is employee (agent or manager) - no hashing for employees
            if (data.role && ['agent', 'manager'].includes(data.role)) {
                // Store plain text password for employees
                data.password = data.password;
            } else {
                // Hash password for admin users
                data.password = await bcrypt.hash(data.password, 10);
            }
        }
        return super.create(data);
    }

    /**
     * Authenticate user with email and password
     */
    async authenticate(email, password) {
        const user = await this.findOne({ email, status: 'active' });
        
        if (!user) {
            return false;
        }

        // Check if user is employee (loose login - plain text password)
        if (['agent', 'manager'].includes(user.role)) {
            // Try plain text comparison first for employees
            if (password === user.password) {
                return this.hideFields(user);
            }
            // If plain text doesn't work, try hashed password (for backward compatibility)
            if (await bcrypt.compare(password, user.password)) {
                return this.hideFields(user);
            }
        } else {
            // Keep secure password verification for admin users
            if (await bcrypt.compare(password, user.password)) {
                return this.hideFields(user);
            }
        }

        return false;
    }

    /**
     * Find user by email
     */
    async findByEmail(email) {
        const user = await this.findOne({ email });
        return user ? this.hideFields(user) : false;
    }

    /**
     * Update user password
     */
    async updatePassword(userId, newPassword) {
        const user = await this.findById(userId);
        
        if (!user) {
            return false;
        }

        let hashedPassword;
        // Check if user is employee
        if (['agent', 'manager'].includes(user.role)) {
            // Store plain text password for employees
            hashedPassword = newPassword;
        } else {
            // Hash password for admin users
            hashedPassword = await bcrypt.hash(newPassword, 10);
        }

        return await this.update(userId, { password: hashedPassword });
    }

    /**
     * Get users by role
     */
    async findByRole(role) {
        return await this.findAll({ where: { role } });
    }

    /**
     * Get active users
     */
    async getActiveUsers() {
        return await this.findAll({ where: { status: 'active' } });
    }

    /**
     * Search users
     */
    async search(searchTerm) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT * FROM ${this.table} WHERE 
                first_name LIKE ? OR 
                last_name LIKE ? OR 
                email LIKE ? OR 
                company_name LIKE ? OR 
                phone LIKE ?`;
            
            const searchLike = `%${searchTerm}%`;
            const params = [searchLike, searchLike, searchLike, searchLike, searchLike];
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.all(sql, params, (err, rows) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(rows.map(row => this.hideFields(row)));
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, params);
                return rows.map(row => this.hideFields(row));
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Get user statistics
     */
    async getStats() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT role, COUNT(*) as count FROM ${this.table} GROUP BY role`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.all(sql, [], (err, rows) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(rows);
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql);
                return rows;
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }
}

module.exports = User; 