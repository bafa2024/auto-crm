<?php
require_once 'BaseController.php';

class EmailRecipientController extends BaseController {
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    public function getRecipient($id) {
        $sql = "SELECT * FROM email_recipients WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recipient) {
            $this->sendSuccess($recipient);
        } else {
            $this->sendError('Recipient not found', 404);
        }
    }
    
    public function updateRecipient($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        // Validate required fields
        if (empty($data['email']) || empty($data['customer_name'])) {
            $this->sendError('Email and Customer Name are required', 400);
            return;
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Please enter a valid email address', 400);
            return;
        }
        
        // Normalize email to lowercase
        $normalizedEmail = strtolower(trim($data['email']));
        
        // Check if email already exists for other recipients (case-insensitive)
        $sql = "SELECT id FROM email_recipients WHERE LOWER(email) = ? AND id != ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$normalizedEmail, $id]);
        if ($stmt->fetch()) {
            $this->sendError('A contact with this email address already exists', 400);
            return;
        }
        
        // Update the recipient
        $sql = "UPDATE email_recipients SET 
                email = ?, 
                name = ?, 
                company = ?, 
                dot = ?, 
                campaign_id = ?,
                updated_at = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $normalizedEmail,
            $data['customer_name'],
            $data['company_name'] ?? '',
            $data['dot'] ?? '',
            empty($data['campaign_id']) ? null : $data['campaign_id'],
            date('Y-m-d H:i:s'),
            $id
        ]);
        
        if ($result) {
            // Get the updated recipient
            $sql = "SELECT * FROM email_recipients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $updatedRecipient = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->sendSuccess($updatedRecipient, 'Recipient updated successfully');
        } else {
            $this->sendError('Failed to update recipient', 500);
        }
    }
    
    public function deleteRecipient($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            // Start a transaction to ensure data consistency
            $this->db->beginTransaction();
            
            // Get the current user ID from session
            $deletedBy = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // First, fetch the recipient data before deletion
            $sql = "SELECT * FROM email_recipients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipient) {
                $this->db->rollBack();
                $this->sendError('Recipient not found', 404);
                return;
            }
            
            // Move campaign_sends records to deleted_campaign_sends table
            try {
                $sql = "INSERT INTO deleted_campaign_sends (campaign_id, recipient_id, recipient_email, status, sent_at, opened_at, clicked_at, tracking_id, original_id)
                        SELECT campaign_id, recipient_id, recipient_email, status, sent_at, opened_at, clicked_at, tracking_id, id
                        FROM campaign_sends WHERE recipient_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$id]);
                
                // Now delete from campaign_sends
                $sql = "DELETE FROM campaign_sends WHERE recipient_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$id]);
            } catch (Exception $e) {
                // Ignore error if table doesn't exist
            }
            
            // Delete from batch_recipients if it exists
            try {
                $sql = "DELETE FROM batch_recipients WHERE recipient_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$id]);
            } catch (Exception $e) {
                // Ignore error if table doesn't exist
            }
            
            // Move the recipient to deleted_email_recipients table
            try {
                $sql = "INSERT INTO deleted_email_recipients (email, name, company, dot, campaign_id, created_at, updated_at, deleted_by, deletion_reason, original_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $recipient['email'],
                    $recipient['name'],
                    $recipient['company'],
                    $recipient['dot'],
                    $recipient['campaign_id'],
                    $recipient['created_at'],
                    $recipient['updated_at'],
                    $deletedBy,
                    'Manual deletion',
                    $id
                ]);
            } catch (Exception $e) {
                // If the deleted_email_recipients table doesn't exist, just log the error and continue
                error_log("Failed to archive recipient to deleted_email_recipients: " . $e->getMessage());
                // Continue with deletion even if archiving fails
            }
            
            // Finally delete the email_recipient
            $sql = "DELETE FROM email_recipients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Commit the transaction
                $this->db->commit();
                $this->sendSuccess(['archived' => true], 'Recipient moved to archive successfully');
            } else {
                // Rollback on failure
                $this->db->rollBack();
                $this->sendError('Failed to delete recipient', 500);
            }
        } catch (Exception $e) {
            // Rollback on any exception
            $this->db->rollBack();
            $this->sendError('Failed to delete recipient: ' . $e->getMessage(), 500);
        }
    }
    
    public function deleteAllRecipients() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            // Start a transaction to ensure data consistency
            $this->db->beginTransaction();
            
            // Get the current user ID from session
            $deletedBy = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // Get count of contacts before deletion
            $countStmt = $this->db->query("SELECT COUNT(*) as total FROM email_recipients");
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($totalCount == 0) {
                $this->db->commit();
                $this->sendSuccess(['archived_count' => 0], "No contacts to delete");
                return;
            }
            
            // Move all campaign_sends records to deleted_campaign_sends table
            try {
                $sql = "INSERT INTO deleted_campaign_sends (campaign_id, recipient_id, recipient_email, status, sent_at, opened_at, clicked_at, tracking_id, original_id)
                        SELECT campaign_id, recipient_id, recipient_email, status, sent_at, opened_at, clicked_at, tracking_id, id
                        FROM campaign_sends";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                
                // Now delete from campaign_sends
                $sql = "DELETE FROM campaign_sends";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
            
            // Delete from email_clicks (they have ON DELETE CASCADE but let's be explicit)
            try {
                $sql = "DELETE FROM email_clicks";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
            
            // Delete from batch_recipients if it exists
            try {
                $sql = "DELETE FROM batch_recipients";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
            } catch (Exception $e) {
                // Ignore error if table doesn't exist
            }
            
            // Move all email_recipients to deleted_email_recipients table
            $archivedCount = 0;
            try {
                $sql = "INSERT INTO deleted_email_recipients (email, name, company, dot, campaign_id, created_at, updated_at, deleted_by, deletion_reason, original_id)
                        SELECT email, name, company, dot, campaign_id, created_at, updated_at, ?, 'Bulk deletion', id
                        FROM email_recipients";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$deletedBy]);
                
                // Get count of successfully archived records
                $archivedCount = $stmt->rowCount();
            } catch (Exception $e) {
                // If the deleted_email_recipients table doesn't exist, just log the error and continue
                error_log("Failed to archive recipients to deleted_email_recipients: " . $e->getMessage());
                // Continue with deletion even if archiving fails
            }
            
            // Finally, delete all email_recipients
            $sql = "DELETE FROM email_recipients";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute();
            
            if ($result) {
                // Commit the transaction
                $this->db->commit();
                $this->sendSuccess([
                    'archived_count' => $archivedCount,
                    'deleted_count' => $totalCount
                ], "Successfully archived and removed {$totalCount} contacts");
            } else {
                // Rollback on failure
                $this->db->rollBack();
                $this->sendError('Failed to delete all contacts', 500);
            }
        } catch (Exception $e) {
            // Rollback on any exception
            $this->db->rollBack();
            $this->sendError('Failed to delete all contacts: ' . $e->getMessage(), 500);
        }
    }
} 