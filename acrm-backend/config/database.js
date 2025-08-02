const mysql = require('mysql2/promise');
const sqlite3 = require('sqlite3').verbose();
const path = require('path');
const fs = require('fs');

class Database {
    constructor() {
        this.connection = null;
        this.config = null;
        this.environment = this.detectEnvironment();
        this.loadConfiguration();
    }

    /**
     * Detect if we're running locally or on live server
     */
    detectEnvironment() {
        const localIndicators = [
            'localhost',
            '127.0.0.1',
            '::1',
            'xampp',
            'wamp',
            'mamp',
            'dev',
            'local'
        ];

        const serverName = process.env.SERVER_NAME || '';
        const httpHost = process.env.HTTP_HOST || '';
        const documentRoot = process.env.DOCUMENT_ROOT || '';
        const currentDir = process.cwd();

        // Check if we're on localhost or local development environment
        let isLocal = false;

        // Check server variables if available
        if (serverName || httpHost) {
            for (const indicator of localIndicators) {
                if (serverName.toLowerCase().includes(indicator) || 
                    httpHost.toLowerCase().includes(indicator)) {
                    isLocal = true;
                    break;
                }
            }
        }

        // Check document root for local development paths
        if (documentRoot) {
            if (documentRoot.toLowerCase().includes('xampp') || 
                documentRoot.toLowerCase().includes('wamp') ||
                documentRoot.toLowerCase().includes('htdocs') ||
                documentRoot.toLowerCase().includes('www')) {
                isLocal = true;
            }
        }

        // Check current working directory
        if (currentDir.toLowerCase().includes('xampp') || 
            currentDir.toLowerCase().includes('htdocs') ||
            currentDir.toLowerCase().includes('localhost') ||
            currentDir.toLowerCase().includes('dev')) {
            isLocal = true;
        }

        // Default to local if we can't determine (safer for development)
        if (!serverName && !httpHost && !documentRoot) {
            isLocal = true;
        }

        // Check for environment variable override
        const envOverride = process.env.DB_ENVIRONMENT;
        if (envOverride) {
            return envOverride;
        }

        return isLocal ? 'local' : 'live';
    }

    /**
     * Load appropriate database configuration
     */
    loadConfiguration() {
        if (this.environment === 'local') {
            this.config = {
                type: 'sqlite',
                database: process.env.DB_SQLITE_PATH || './database.sqlite'
            };
        } else {
            this.config = {
                type: 'mysql',
                host: process.env.DB_HOST || 'localhost',
                port: process.env.DB_PORT || 3306,
                user: process.env.DB_USER || 'root',
                password: process.env.DB_PASSWORD || '',
                database: process.env.DB_NAME || 'acrm',
                charset: 'utf8mb4'
            };
        }

        console.log(`Database Environment: ${this.environment} (${this.config.type})`);
    }

    /**
     * Get database connection
     */
    async getConnection() {
        if (this.connection) {
            return this.connection;
        }

        try {
            if (this.config.type === 'sqlite') {
                this.connection = await this.createSQLiteConnection();
            } else {
                this.connection = await this.createMySQLConnection();
            }

            return this.connection;
        } catch (error) {
            console.error('Database connection failed:', error);
            throw error;
        }
    }

    /**
     * Create SQLite connection
     */
    async createSQLiteConnection() {
        return new Promise((resolve, reject) => {
            const dbPath = path.resolve(this.config.database);
            
            // Ensure directory exists
            const dir = path.dirname(dbPath);
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }

            const db = new sqlite3.Database(dbPath, (err) => {
                if (err) {
                    console.error('SQLite connection error:', err);
                    reject(err);
                } else {
                    console.log('SQLite database connected');
                    resolve(db);
                }
            });

            // Enable foreign keys
            db.run('PRAGMA foreign_keys = ON');
        });
    }

    /**
     * Create MySQL connection
     */
    async createMySQLConnection() {
        try {
            const connection = await mysql.createConnection({
                host: this.config.host,
                port: this.config.port,
                user: this.config.user,
                password: this.config.password,
                database: this.config.database,
                charset: this.config.charset
            });

            console.log('MySQL database connected');
            return connection;
        } catch (error) {
            console.error('MySQL connection error:', error);
            throw error;
        }
    }

    /**
     * Get database information
     */
    async getDatabaseInfo() {
        try {
            const connection = await this.getConnection();
            
            if (this.config.type === 'sqlite') {
                return this.getSQLiteInfo(connection);
            } else {
                return this.getMySQLInfo(connection);
            }
        } catch (error) {
            console.error('Error getting database info:', error);
            throw error;
        }
    }

    /**
     * Get SQLite database information
     */
    async getSQLiteInfo(db) {
        return new Promise((resolve, reject) => {
            db.all("SELECT name FROM sqlite_master WHERE type='table'", (err, tables) => {
                if (err) {
                    reject(err);
                } else {
                    resolve({
                        type: 'SQLite',
                        environment: this.environment,
                        database: this.config.database,
                        tables: tables.map(t => t.name)
                    });
                }
            });
        });
    }

    /**
     * Get MySQL database information
     */
    async getMySQLInfo(connection) {
        const [tables] = await connection.execute("SHOW TABLES");
        const tableNames = tables.map(row => Object.values(row)[0]);

        return {
            type: 'MySQL',
            environment: this.environment,
            host: this.config.host,
            database: this.config.database,
            tables: tableNames
        };
    }

    /**
     * Test database connection
     */
    async testConnection() {
        try {
            const connection = await this.getConnection();
            
            if (this.config.type === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.get("SELECT 1 as test", (err, row) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve({ success: true, message: 'SQLite connection successful' });
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute("SELECT 1 as test");
                return { success: true, message: 'MySQL connection successful' };
            }
        } catch (error) {
            return { success: false, message: error.message };
        }
    }

    /**
     * Get environment
     */
    getEnvironment() {
        return this.environment;
    }

    /**
     * Get database type
     */
    getDatabaseType() {
        return this.config.type;
    }

    /**
     * Close database connection
     */
    async close() {
        if (this.connection) {
            if (this.config.type === 'sqlite') {
                return new Promise((resolve) => {
                    this.connection.close((err) => {
                        if (err) {
                            console.error('Error closing SQLite connection:', err);
                        } else {
                            console.log('SQLite connection closed');
                        }
                        resolve();
                    });
                });
            } else {
                await this.connection.end();
                console.log('MySQL connection closed');
            }
            this.connection = null;
        }
    }
}

module.exports = Database; 