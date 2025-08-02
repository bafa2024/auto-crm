const BaseModel = require('./BaseModel');

class EmailCampaign extends BaseModel {
    constructor(db) {
        super(db, 'email_campaigns');
        this.fillable = [
            'name', 'subject', 'content', 'status', 'scheduled_at', 
            'sent_at', 'total_recipients', 'sent_count', 'opened_count', 
            'clicked_count', 'user_id', 'template_id'
        ];
    }

    /**
     * Get campaigns by status
     */
    async getByStatus(status) {
        return await this.findAll({ where: { status } });
    }

    /**
     * Get campaigns by user
     */
    async getByUser(userId) {
        return await this.findAll({ where: { user_id: userId } });
    }

    /**
     * Get active campaigns
     */
    async getActive() {
        return await this.findAll({ where: { status: 'active' } });
    }

    /**
     * Get scheduled campaigns
     */
    async getScheduled() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT * FROM ${this.table} WHERE status = 'scheduled' AND scheduled_at <= ?`;
            const now = new Date().toISOString();
            
            if (dbType === 'sqlite') {
                return new Promise((resolve, reject) => {
                    connection.all(sql, [now], (err, rows) => {
                        if (err) {
                            reject(err);
                        } else {
                            resolve(rows.map(row => this.hideFields(row)));
                        }
                    });
                });
            } else {
                const [rows] = await connection.execute(sql, [now]);
                return rows.map(row => this.hideFields(row));
            }
        } finally {
            if (dbType === 'mysql') {
                connection.release();
            }
        }
    }

    /**
     * Get campaign statistics
     */
    async getStats() {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT 
                status, 
                COUNT(*) as count,
                SUM(total_recipients) as total_recipients,
                SUM(sent_count) as total_sent,
                SUM(opened_count) as total_opened,
                SUM(clicked_count) as total_clicked
                FROM ${this.table} 
                GROUP BY status`;
            
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
     * Update campaign status
     */
    async updateStatus(id, status) {
        return await this.update(id, { status });
    }

    /**
     * Mark campaign as sent
     */
    async markAsSent(id, sentCount) {
        return await this.update(id, { 
            status: 'sent', 
            sent_at: new Date().toISOString(),
            sent_count: sentCount
        });
    }

    /**
     * Update campaign metrics
     */
    async updateMetrics(id, metrics) {
        const updateData = {};
        if (metrics.opened_count !== undefined) updateData.opened_count = metrics.opened_count;
        if (metrics.clicked_count !== undefined) updateData.clicked_count = metrics.clicked_count;
        
        return await this.update(id, updateData);
    }

    /**
     * Get campaign performance
     */
    async getPerformance(id) {
        const campaign = await this.findById(id);
        if (!campaign) return null;

        const openRate = campaign.total_recipients > 0 
            ? (campaign.opened_count / campaign.total_recipients) * 100 
            : 0;
        const clickRate = campaign.total_recipients > 0 
            ? (campaign.clicked_count / campaign.total_recipients) * 100 
            : 0;

        return {
            ...campaign,
            open_rate: openRate.toFixed(2),
            click_rate: clickRate.toFixed(2)
        };
    }

    /**
     * Get recent campaigns
     */
    async getRecent(limit = 10) {
        return await this.findAll({ 
            orderBy: 'created_at DESC',
            limit 
        });
    }

    /**
     * Search campaigns
     */
    async search(searchTerm) {
        const connection = await this.db.getConnection();
        const dbType = this.db.getDatabaseType();
        
        try {
            const sql = `SELECT * FROM ${this.table} WHERE 
                name LIKE ? OR 
                subject LIKE ? OR 
                content LIKE ?`;
            
            const searchLike = `%${searchTerm}%`;
            const params = [searchLike, searchLike, searchLike];
            
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
}

module.exports = EmailCampaign; 