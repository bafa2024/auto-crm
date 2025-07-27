<?php
require_once "BaseController.php";

class EmailWebhookController extends BaseController {
    
    /**
     * Handle bounce webhook from email service providers
     * This is a generic handler that can be adapted for different providers
     */
    public function handleBounce() {
        try {
            // Get webhook data
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // Log webhook for debugging
            error_log("Email bounce webhook: " . $input);
            
            // Different providers send data differently
            // Example for common providers:
            
            // SendGrid format
            if (isset($data['event']) && $data['event'] === 'bounce') {
                $this->processBounce($data['email'], $data['reason'] ?? 'Hard bounce');
            }
            
            // Mailgun format
            elseif (isset($data['event-data']) && $data['event-data']['event'] === 'failed') {
                $this->processBounce(
                    $data['event-data']['recipient'],
                    $data['event-data']['delivery-status']['message'] ?? 'Bounce'
                );
            }
            
            // Generic format
            elseif (isset($data['email']) && isset($data['type']) && $data['type'] === 'bounce') {
                $this->processBounce($data['email'], $data['reason'] ?? 'Unknown');
            }
            
            // Return success to webhook
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            
        } catch (Exception $e) {
            error_log("Webhook error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
        }
    }
    
    /**
     * Process a bounce
     */
    private function processBounce($email, $reason) {
        // Update recipient status
        $stmt = $this->db->prepare("
            UPDATE email_recipients 
            SET status = 'bounced', 
                bounced_at = NOW(),
                error_message = ?
            WHERE email = ? 
            AND bounced_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$reason, $email]);
        
        // Add to bounce list
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_bounces (email, reason, bounced_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$email, $reason]);
        } catch (Exception $e) {
            // Table might not exist yet
        }
        
        // Update campaign stats
        $stmt = $this->db->prepare("
            UPDATE email_campaigns c
            SET bounced_count = (
                SELECT COUNT(*) 
                FROM email_recipients r 
                WHERE r.campaign_id = c.id 
                AND r.status = 'bounced'
            )
            WHERE c.id IN (
                SELECT DISTINCT campaign_id 
                FROM email_recipients 
                WHERE email = ?
            )
        ");
        $stmt->execute([$email]);
    }
    
    /**
     * Handle complaint/spam report webhook
     */
    public function handleComplaint() {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            error_log("Email complaint webhook: " . $input);
            
            $email = null;
            
            // Extract email based on provider format
            if (isset($data['email'])) {
                $email = $data['email'];
            } elseif (isset($data['event-data']['recipient'])) {
                $email = $data['event-data']['recipient'];
            }
            
            if ($email) {
                // Mark as spam complaint
                $stmt = $this->db->prepare("
                    UPDATE email_recipients 
                    SET status = 'complained',
                        error_message = 'Marked as spam'
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
                
                // Add to unsubscribe list
                $stmt = $this->db->prepare("
                    INSERT INTO unsubscribed_emails (email, campaign_id, unsubscribed_at)
                    VALUES (?, NULL, NOW())
                    ON DUPLICATE KEY UPDATE unsubscribed_at = NOW()
                ");
                $stmt->execute([$email]);
            }
            
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            
        } catch (Exception $e) {
            error_log("Complaint webhook error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
        }
    }
}