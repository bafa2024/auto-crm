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
            
            // First, delete related records from batch_recipients table
            $sql = "DELETE FROM batch_recipients WHERE recipient_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            // Then delete the email_recipient
            $sql = "DELETE FROM email_recipients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Commit the transaction
                $this->db->commit();
                $this->sendSuccess([], 'Recipient deleted successfully');
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
} 