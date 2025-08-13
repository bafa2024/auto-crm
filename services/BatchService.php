<?php

class BatchService {
    private $db;
    private $dbType;
    private $batchSize = 200;
    
    public function __construct($database) {
        if ($database instanceof PDO) {
            // Direct PDO connection passed
            $this->db = $database;
            $this->dbType = 'mysql'; // Default for PDO connections
        } else {
            // Database object passed
            $this->db = $database->getConnection();
            $this->dbType = $database->getDatabaseType();
        }
    }
    
    /**
     * Create batches for a campaign
     */
    public function createBatchesForCampaign($campaignId, $recipientIds = null) {
        try {
            // Create batches table if it doesn't exist
            $this->createBatchesTable();
            
            error_log("createBatchesForCampaign called with campaignId: $campaignId, recipientIds: " . json_encode($recipientIds));
            
            // Get recipients for the campaign if not provided
            if ($recipientIds === null) {
                // Get all active recipients (allow re-sending)
                // Remove campaign_id filter since recipients might not have campaign_id set
                $sql = "SELECT DISTINCT r.id, LOWER(r.email) as email_lower 
                        FROM email_recipients r
                        GROUP BY LOWER(r.email)
                        ORDER BY r.id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Found " . count($recipients) . " recipients when recipientIds is null");
                
                // Extract just the IDs
                $recipientIds = array_column($recipients, 'id');
            } else {
                error_log("Filtering " . count($recipientIds) . " provided recipient IDs");
                // Filter provided IDs to ensure they exist and are unique by email
                $recipientIds = $this->filterUniqueRecipients($campaignId, $recipientIds);
                error_log("After filtering: " . count($recipientIds) . " recipient IDs remain");
            }
            
            if (empty($recipientIds)) {
                error_log("No recipients found after filtering. Returning error.");
                return [
                    'success' => false,
                    'message' => 'No recipients found for batching'
                ];
            }
            
            // Split recipients into batches
            $batches = array_chunk($recipientIds, $this->batchSize);
            $batchCount = 0;
            
            error_log("Creating " . count($batches) . " batches from " . count($recipientIds) . " recipients (batch size: " . $this->batchSize . ")");
            
            foreach ($batches as $batchIndex => $batchRecipients) {
                $batchNumber = $batchIndex + 1;
                $currentTime = date('Y-m-d H:i:s');
                
                error_log("Creating batch $batchNumber with " . count($batchRecipients) . " recipients");
                
                // Create batch record
                $sql = "INSERT INTO email_batches (campaign_id, batch_number, total_recipients, status, created_at) 
                        VALUES (?, ?, ?, 'pending', ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$campaignId, $batchNumber, count($batchRecipients), $currentTime]);
                $batchId = $this->db->lastInsertId();
                
                error_log("Created batch with ID: $batchId");
                
                // Link recipients to batch
                foreach ($batchRecipients as $recipientId) {
                    $sql = "INSERT INTO batch_recipients (batch_id, recipient_id) VALUES (?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$batchId, $recipientId]);
                }
                
                error_log("Added " . count($batchRecipients) . " recipients to batch $batchId");
                
                $batchCount++;
            }
            
            return [
                'success' => true,
                'batch_count' => $batchCount,
                'total_recipients' => count($recipientIds),
                'batch_size' => $this->batchSize
            ];
            
        } catch (Exception $e) {
            error_log("Error creating batches: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create batches: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Filter recipients to get only unique emails (allows re-sending)
     */
    private function filterUniqueRecipients($campaignId, $recipientIds) {
        if (empty($recipientIds)) {
            error_log("filterUniqueRecipients: Empty recipientIds provided");
            return [];
        }
        
        try {
            error_log("filterUniqueRecipients: Processing " . count($recipientIds) . " recipient IDs: " . json_encode($recipientIds));
            
            $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
            
            // Get recipients with unique emails (case-insensitive) - allows re-sending
            // Remove the campaign_id filter from WHERE clause since recipients might not have campaign_id set
            $sql = "SELECT r1.id 
                    FROM email_recipients r1
                    WHERE r1.id IN ($placeholders)
                    AND r1.id = (
                        SELECT MIN(r2.id) 
                        FROM email_recipients r2 
                        WHERE LOWER(r2.email) = LOWER(r1.email)
                    )
                    ORDER BY r1.id";
            
            error_log("filterUniqueRecipients SQL: " . $sql);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($recipientIds);
            
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("filterUniqueRecipients: Found " . count($result) . " unique recipients: " . json_encode($result));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error filtering unique recipients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Filter recipients to get only fresh, unique emails (legacy method - prevents re-sending)
     */
    private function filterFreshUniqueRecipients($campaignId, $recipientIds) {
        if (empty($recipientIds)) {
            return [];
        }
        
        try {
            $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
            
            // Get fresh recipients with unique emails (case-insensitive)
            $sql = "SELECT r1.id 
                    FROM email_recipients r1
                    LEFT JOIN campaign_sends cs ON r1.id = cs.recipient_id AND cs.campaign_id = ? AND cs.status = 'sent'
                    WHERE r1.id IN ($placeholders)
                    AND cs.id IS NULL
                    AND r1.id = (
                        SELECT MIN(r2.id) 
                        FROM email_recipients r2 
                        WHERE LOWER(r2.email) = LOWER(r1.email) 
                        AND r2.campaign_id = r1.campaign_id
                    )
                    ORDER BY r1.id";
            
            $params = array_merge([$campaignId], $recipientIds);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (Exception $e) {
            error_log("Error filtering recipients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get next pending batch for a campaign
     */
    public function getNextPendingBatch($campaignId) {
        try {
            $sql = "SELECT * FROM email_batches 
                    WHERE campaign_id = ? AND status = 'pending' 
                    ORDER BY batch_number ASC 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting next batch: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get batch recipients
     */
    public function getBatchRecipients($batchId) {
        try {
            $sql = "SELECT r.* FROM email_recipients r
                    INNER JOIN batch_recipients br ON r.id = br.recipient_id
                    WHERE br.batch_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$batchId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting batch recipients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update batch status
     */
    public function updateBatchStatus($batchId, $status, $additionalData = []) {
        try {
            $updates = ['status = ?'];
            $params = [$status];
            
            if ($status === 'processing') {
                $updates[] = 'started_at = ?';
                $params[] = date('Y-m-d H:i:s');
            } elseif ($status === 'completed') {
                $updates[] = 'completed_at = ?';
                $params[] = date('Y-m-d H:i:s');
                if (isset($additionalData['sent_count'])) {
                    $updates[] = 'sent_count = ?';
                    $params[] = $additionalData['sent_count'];
                }
                if (isset($additionalData['failed_count'])) {
                    $updates[] = 'failed_count = ?';
                    $params[] = $additionalData['failed_count'];
                }
            }
            
            $params[] = $batchId;
            
            $sql = "UPDATE email_batches SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Error updating batch status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get campaign batch statistics
     */
    public function getCampaignBatchStats($campaignId) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_batches,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_batches,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_batches,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_batches,
                    SUM(total_recipients) as total_recipients,
                    SUM(sent_count) as total_sent,
                    SUM(failed_count) as total_failed
                    FROM email_batches 
                    WHERE campaign_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting batch stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create batches table
     */
    private function createBatchesTable() {
        if ($this->dbType === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS email_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                batch_number INTEGER NOT NULL,
                total_recipients INTEGER DEFAULT 0,
                sent_count INTEGER DEFAULT 0,
                failed_count INTEGER DEFAULT 0,
                status TEXT DEFAULT 'pending',
                started_at DATETIME,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id)
            )";
            
            $sql2 = "CREATE TABLE IF NOT EXISTS batch_recipients (
                batch_id INTEGER NOT NULL,
                recipient_id INTEGER NOT NULL,
                PRIMARY KEY (batch_id, recipient_id),
                FOREIGN KEY (batch_id) REFERENCES email_batches(id),
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id)
            )";
        } else {
            // MySQL syntax
            $sql = "CREATE TABLE IF NOT EXISTS email_batches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                batch_number INT NOT NULL,
                total_recipients INT DEFAULT 0,
                sent_count INT DEFAULT 0,
                failed_count INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'pending',
                started_at DATETIME,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_campaign_status (campaign_id, status),
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $sql2 = "CREATE TABLE IF NOT EXISTS batch_recipients (
                batch_id INT NOT NULL,
                recipient_id INT NOT NULL,
                PRIMARY KEY (batch_id, recipient_id),
                FOREIGN KEY (batch_id) REFERENCES email_batches(id),
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $this->db->exec($sql);
        $this->db->exec($sql2);
        
        // Create indices for better performance
        try {
            if ($this->dbType === 'sqlite') {
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_batch_campaign ON email_batches(campaign_id)");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_batch_status ON email_batches(status)");
            }
        } catch (Exception $e) {
            // Indices might already exist
        }
    }
    
    /**
     * Set batch size
     */
    public function setBatchSize($size) {
        $this->batchSize = max(1, min(1000, $size)); // Limit between 1-1000
    }
    
    /**
     * Get batch size
     */
    public function getBatchSize() {
        return $this->batchSize;
    }
}
?>