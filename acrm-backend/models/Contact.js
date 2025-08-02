const BaseModel = require('./BaseModel');

class Contact extends BaseModel {
    constructor(db) {
        super(db, 'contacts');
        this.fillable = [
            'first_name', 'last_name', 'email', 'phone', 
            'company', 'position', 'status', 'lead_source'
        ];
    }

    /**
     * Search contacts
     */
    async search(searchTerm) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT * FROM ${this.table} WHERE 
                first_name LIKE ? OR 
                last_name LIKE ? OR 
                email LIKE ? OR 
                company LIKE ? OR 
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
     * Get contacts by status
     */
    async getByStatus(status) {
        return await this.findAll({ where: { status } });
    }

    /**
     * Get contacts by company
     */
    async getByCompany(company) {
        return await this.findAll({ where: { company } });
    }

    /**
     * Get contacts by lead source
     */
    async getByLeadSource(leadSource) {
        return await this.findAll({ where: { lead_source: leadSource } });
    }

    /**
     * Get contacts statistics by status
     */
    async getStatsByStatus() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT status, COUNT(*) as count FROM ${this.table} GROUP BY status`;
            
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

    /**
     * Get contacts statistics by lead source
     */
    async getStatsByLeadSource() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT lead_source, COUNT(*) as count FROM ${this.table} GROUP BY lead_source`;
            
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

    /**
     * Get contacts statistics by company
     */
    async getStatsByCompany() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT company, COUNT(*) as count FROM ${this.table} GROUP BY company ORDER BY count DESC LIMIT 10`;
            
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

    /**
     * Bulk insert contacts
     */
    async bulkInsert(contacts) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            if (contacts.length === 0) {
                return { inserted: 0 };
            }

            const fields = this.fillable;
            const placeholders = fields.map(() => '?').join(', ');
            const sql = `INSERT INTO ${this.table} (${fields.join(', ')}) VALUES (${placeholders})`;
            
            let inserted = 0;
            
            if (dbType === 'sqlite') {
                for (const contact of contacts) {
                    const filteredData = this.filterFillable(contact);
                    const values = fields.map(field => filteredData[field] || null);
                    
                    await new Promise((resolve, reject) => {
                        connection.run(sql, values, function(err) {
                            if (err) {
                                reject(err);
                            } else {
                                inserted++;
                                resolve();
                            }
                        });
                    });
                }
            } else {
                for (const contact of contacts) {
                    const filteredData = this.filterFillable(contact);
                    const values = fields.map(field => filteredData[field] || null);
                    
                    await connection.execute(sql, values);
                    inserted++;
                }
            }
            
            return { inserted };
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Delete all contacts
     */
    async deleteAll() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `DELETE FROM ${this.table}`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.run(sql, [], function(err) {
                        if (err) {
                            reject(err);
                        } else {
                            resolve({ deleted: this.changes });
                        }
                    });
                });
            } else {
                const [result] = await connection.execute(sql);
                return { deleted: result.affectedRows };
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Get recent contacts
     */
    async getRecent(limit = 10) {
        return await this.findAll({ 
            orderBy: 'created_at DESC',
            limit 
        });
    }

    /**
     * Get contacts for export
     */
    async getForExport() {
        return await this.findAll({ orderBy: 'created_at DESC' });
    }
}

module.exports = Contact; 