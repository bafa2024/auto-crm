class BaseModel {
    constructor(db, table) {
        this.db = db;
        this.table = table;
        this.fillable = [];
        this.hidden = [];
    }

    /**
     * Create a new record
     */
    async create(data) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const filteredData = this.filterFillable(data);
            const fields = Object.keys(filteredData);
            const values = Object.values(filteredData);
            const placeholders = fields.map(() => '?').join(', ');
            
            const sql = `INSERT INTO ${this.table} (${fields.join(', ')}) VALUES (${placeholders})`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.run(sql, values, function(err) {
                        if (err) {
                            reject(err);
                        } else {
                            resolve({ id: this.lastID, ...filteredData });
                        }
                    });
                });
            } else {
                const [result] = await connection.execute(sql, values);
                return { id: result.insertId, ...filteredData };
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Find a record by ID
     */
    async findById(id) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT * FROM ${this.table} WHERE id = ?`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.get(sql, [id], (err, row) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(row ? this.hideFields(row) : null);
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, [id]);
                return rows.length > 0 ? this.hideFields(rows[0]) : null;
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Find all records
     */
    async findAll(options = {}) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            let sql = `SELECT * FROM ${this.table}`;
            const params = [];
            
            // Add WHERE clause if conditions provided
            if (options.where) {
                const whereClause = Object.keys(options.where)
                    .map(key => `${key} = ?`)
                    .join(' AND ');
                sql += ` WHERE ${whereClause}`;
                params.push(...Object.values(options.where));
            }
            
            // Add ORDER BY
            if (options.orderBy) {
                sql += ` ORDER BY ${options.orderBy}`;
            }
            
            // Add LIMIT
            if (options.limit) {
                sql += ` LIMIT ${options.limit}`;
            }
            
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
     * Update a record
     */
    async update(id, data) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const filteredData = this.filterFillable(data);
            const fields = Object.keys(filteredData);
            const values = Object.values(filteredData);
            
            if (fields.length === 0) {
                throw new Error('No valid fields to update');
            }
            
            const setClause = fields.map(field => `${field} = ?`).join(', ');
            const sql = `UPDATE ${this.table} SET ${setClause} WHERE id = ?`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.run(sql, [...values, id], function(err) {
                        if (err) {
                            reject(err);
                        } else {
                            resolve({ id, ...filteredData });
                        }
                    });
                });
            } else {
                await connection.execute(sql, [...values, id]);
                return { id, ...filteredData };
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Delete a record
     */
    async delete(id) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `DELETE FROM ${this.table} WHERE id = ?`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.run(sql, [id], function(err) {
                        if (err) {
                            reject(err);
                        } else {
                            resolve({ deleted: this.changes > 0 });
                        }
                    });
                });
            } else {
                const [result] = await connection.execute(sql, [id]);
                return { deleted: result.affectedRows > 0 };
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Find one record by conditions
     */
    async findOne(conditions) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const whereClause = Object.keys(conditions)
                .map(key => `${key} = ?`)
                .join(' AND ');
            const sql = `SELECT * FROM ${this.table} WHERE ${whereClause} LIMIT 1`;
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.get(sql, Object.values(conditions), (err, row) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(row ? this.hideFields(row) : null);
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, Object.values(conditions));
                return rows.length > 0 ? this.hideFields(rows[0]) : null;
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Count records
     */
    async count(conditions = {}) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            let sql = `SELECT COUNT(*) as count FROM ${this.table}`;
            const params = [];
            
            if (Object.keys(conditions).length > 0) {
                const whereClause = Object.keys(conditions)
                    .map(key => `${key} = ?`)
                    .join(' AND ');
                sql += ` WHERE ${whereClause}`;
                params.push(...Object.values(conditions));
            }
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.get(sql, params, (err, row) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(row ? parseInt(row.count) : 0);
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, params);
                return rows.length > 0 ? parseInt(rows[0].count) : 0;
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Paginate records
     */
    async paginate(page = 1, perPage = 10, conditions = {}) {
        const offset = (page - 1) * perPage;
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            let whereClause = '';
            const params = [];
            
            if (Object.keys(conditions).length > 0) {
                whereClause = 'WHERE ' + Object.keys(conditions)
                    .map(key => `${key} = ?`)
                    .join(' AND ');
                params.push(...Object.values(conditions));
            }
            
            // Get total count
            const countSql = `SELECT COUNT(*) as total FROM ${this.table} ${whereClause}`;
            let total;
            
            if (dbType === 'sqlite') {
                total = await new Promise((resolve, reject) => {
                    connection.get(countSql, params, (err, row) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(row ? parseInt(row.total) : 0);
                        }
                    });
                });
            } else {
                const [countRows] = await connection.execute(countSql, params);
                total = countRows.length > 0 ? parseInt(countRows[0].total) : 0;
            }
            
            // Get paginated data
            const dataSql = `SELECT * FROM ${this.table} ${whereClause} ORDER BY created_at DESC LIMIT ${perPage} OFFSET ${offset}`;
            let data;
            
            if (dbType === 'sqlite') {
                data = await new Promise((resolve, reject) => {
                    connection.all(dataSql, params, (err, rows) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(rows.map(row => this.hideFields(row)));
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(dataSql, params);
                data = rows.map(row => this.hideFields(row));
            }
            
            return {
                data,
                total,
                page,
                per_page: perPage,
                total_pages: Math.ceil(total / perPage)
            };
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Filter data to only include fillable fields
     */
    filterFillable(data) {
        if (this.fillable.length === 0) {
            return data;
        }
        
        const filtered = {};
        for (const field of this.fillable) {
            if (data.hasOwnProperty(field)) {
                filtered[field] = data[field];
            }
        }
        return filtered;
    }

    /**
     * Hide sensitive fields from response
     */
    hideFields(data) {
        if (this.hidden.length === 0) {
            return data;
        }
        
        const filtered = { ...data };
        for (const field of this.hidden) {
            delete filtered[field];
        }
        return filtered;
    }

    /**
     * Execute raw SQL query
     */
    async raw(sql, params = []) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.all(sql, params, (err, rows) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(rows);
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, params);
                return rows;
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }
}

module.exports = BaseModel; 