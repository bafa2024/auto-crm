<?php
require_once 'BaseController.php';

class ContactHistoryController extends BaseController {
    private $contactModel;
    
    public function __construct($database) {
        parent::__construct($database);
        $this->contactModel = new Contact($database);
    }
    
    /**
     * Get contact history for a specific contact
     */
    public function getContactHistory($contactId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $sql = "SELECT 
                        ch.*,
                        u.email as performed_by_email
                    FROM contact_history ch
                    LEFT JOIN users u ON ch.performed_by = u.id
                    WHERE ch.contact_id = ?
                    ORDER BY ch.performed_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$contactId]);
            $history = $stmt->fetchAll();
            
            $this->sendSuccess($history);
        } catch (Exception $e) {
            $this->sendError('Failed to get contact history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent uploads with statistics
     */
    public function getRecentUploads() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $days = $_GET['days'] ?? 30;
            $limit = $_GET['limit'] ?? 20;
            
            $sql = "SELECT * FROM recent_uploads 
                    WHERE days_ago <= ? 
                    ORDER BY uploaded_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days, $limit]);
            $uploads = $stmt->fetchAll();
            
            // Get summary statistics
            $summarySql = "SELECT 
                            COUNT(*) as total_uploads,
                            SUM(total_records) as total_records,
                            SUM(successful_uploads) as total_successful,
                            SUM(failed_uploads) as total_failed
                          FROM upload_sessions 
                          WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $summaryStmt = $this->db->prepare($summarySql);
            $summaryStmt->execute([$days]);
            $summary = $summaryStmt->fetch();
            
            $this->sendSuccess([
                'uploads' => $uploads,
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to get recent uploads: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get upload statistics by date range
     */
    public function getUploadStatistics() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            $sql = "SELECT 
                        DATE(uploaded_at) as date,
                        COUNT(*) as upload_count,
                        SUM(total_records) as total_records,
                        SUM(successful_uploads) as successful_uploads,
                        SUM(failed_uploads) as failed_uploads
                    FROM upload_sessions 
                    WHERE DATE(uploaded_at) BETWEEN ? AND ?
                    GROUP BY DATE(uploaded_at)
                    ORDER BY date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $statistics = $stmt->fetchAll();
            
            $this->sendSuccess($statistics);
        } catch (Exception $e) {
            $this->sendError('Failed to get upload statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get contacts by batch with management options
     */
    public function getContactsByBatch() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $days = $_GET['days'] ?? 30;
            $batchId = $_GET['batch_id'] ?? null;
            
            if ($batchId) {
                // Get specific batch
                $sql = "SELECT 
                            c.*,
                            cb.batch_name,
                            cb.created_at as batch_created_at,
                            cb.status as batch_status
                        FROM contacts c
                        LEFT JOIN contact_batches cb ON c.batch_id = cb.id
                        WHERE c.batch_id = ?
                        ORDER BY c.created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$batchId]);
            } else {
                // Get recent batches
                $sql = "SELECT 
                            cb.*,
                            COUNT(c.id) as contact_count,
                            COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_count
                        FROM contact_batches cb
                        LEFT JOIN contacts c ON cb.id = c.batch_id
                        WHERE cb.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        GROUP BY cb.id
                        ORDER BY cb.created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$days]);
            }
            
            $contacts = $stmt->fetchAll();
            $this->sendSuccess($contacts);
        } catch (Exception $e) {
            $this->sendError('Failed to get contacts by batch: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete contacts by batch
     */
    public function deleteContactsByBatch() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $batchId = $input['batch_id'] ?? null;
            $days = $input['days'] ?? null;
            
            if (!$batchId && !$days) {
                $this->sendError('Either batch_id or days parameter is required', 400);
            }
            
            $whereClause = "";
            $params = [];
            
            if ($batchId) {
                $whereClause = "batch_id = ?";
                $params = [$batchId];
            } elseif ($days) {
                $whereClause = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND batch_id IS NOT NULL";
                $params = [$days];
            }
            
            // Get count before deletion
            $countSql = "SELECT COUNT(*) as count FROM contacts WHERE $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $count = $countStmt->fetch()['count'];
            
            // Delete contacts
            $deleteSql = "DELETE FROM contacts WHERE $whereClause";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute($params);
            
            // Log the deletion
            $this->logBulkDeletion($count, $batchId, $days);
            
            $this->sendSuccess([
                'deleted_count' => $count,
                'message' => "Successfully deleted $count contacts"
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to delete contacts: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Archive contacts by batch (mark as inactive instead of deleting)
     */
    public function archiveContactsByBatch() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $batchId = $input['batch_id'] ?? null;
            $days = $input['days'] ?? null;
            
            if (!$batchId && !$days) {
                $this->sendError('Either batch_id or days parameter is required', 400);
            }
            
            $whereClause = "";
            $params = [];
            
            if ($batchId) {
                $whereClause = "batch_id = ?";
                $params = [$batchId];
            } elseif ($days) {
                $whereClause = "created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND batch_id IS NOT NULL";
                $params = [$days];
            }
            
            // Update contacts to inactive
            $updateSql = "UPDATE contacts SET status = 'inactive' WHERE $whereClause";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute($params);
            
            $affectedRows = $updateStmt->rowCount();
            
            $this->sendSuccess([
                'archived_count' => $affectedRows,
                'message' => "Successfully archived $affectedRows contacts"
            ]);
        } catch (Exception $e) {
            $this->sendError('Failed to archive contacts: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get data management dashboard statistics
     */
    public function getDataManagementStats() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            // Get statistics for different time periods
            $periods = [7, 30, 90];
            $stats = [];
            
            foreach ($periods as $days) {
                $sql = "SELECT 
                            COUNT(*) as total_contacts,
                            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_contacts,
                            COUNT(CASE WHEN batch_id IS NOT NULL THEN 1 END) as batch_contacts,
                            COUNT(DISTINCT batch_id) as unique_batches
                        FROM contacts 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$days]);
                $stats["last_{$days}_days"] = $stmt->fetch();
            }
            
            // Get upload statistics
            $uploadSql = "SELECT 
                            COUNT(*) as total_uploads,
                            SUM(total_records) as total_records,
                            SUM(successful_uploads) as successful_uploads,
                            SUM(failed_uploads) as failed_uploads
                        FROM upload_sessions 
                        WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $uploadStmt = $this->db->prepare($uploadSql);
            $uploadStmt->execute();
            $stats['upload_stats'] = $uploadStmt->fetch();
            
            $this->sendSuccess($stats);
        } catch (Exception $e) {
            $this->sendError('Failed to get data management stats: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Log bulk deletion for history tracking
     */
    private function logBulkDeletion($count, $batchId = null, $days = null) {
        try {
            $notes = "Bulk deletion: ";
            if ($batchId) {
                $notes .= "Batch ID: $batchId";
            } elseif ($days) {
                $notes .= "Last $days days";
            }
            $notes .= " - Deleted $count contacts";
            
            $sql = "INSERT INTO contact_history (contact_id, action, performed_by, notes, batch_id) 
                    VALUES (0, 'bulk_deleted', ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $_SESSION['user_id'] ?? 1,
                $notes,
                $batchId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log bulk deletion: " . $e->getMessage());
        }
    }
}
?> 