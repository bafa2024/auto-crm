<?php
require_once "BaseController.php";

class EmailTrackingController extends BaseController {
    
    /**
     * Track email open
     */
    public function trackOpen($trackingId) {
        try {
            // Update recipient record
            $stmt = $this->db->prepare("
                UPDATE email_recipients 
                SET opened_at = CASE 
                    WHEN opened_at IS NULL THEN NOW() 
                    ELSE opened_at 
                END,
                open_count = COALESCE(open_count, 0) + 1
                WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            
            // Get campaign ID for stats update
            $stmt = $this->db->prepare("
                SELECT campaign_id FROM email_recipients WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            $recipient = $stmt->fetch();
            
            if ($recipient) {
                // Update campaign open count
                $stmt = $this->db->prepare("
                    UPDATE email_campaigns 
                    SET opened_count = (
                        SELECT COUNT(DISTINCT id) 
                        FROM email_recipients 
                        WHERE campaign_id = ? AND opened_at IS NOT NULL
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$recipient['campaign_id'], $recipient['campaign_id']]);
            }
            
            // Return 1x1 transparent pixel
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            
        } catch (Exception $e) {
            error_log("Email tracking error: " . $e->getMessage());
            http_response_code(404);
        }
    }
    
    /**
     * Track email click
     */
    public function trackClick($trackingId) {
        try {
            $url = $_GET['url'] ?? '';
            
            if (empty($url)) {
                throw new Exception("No URL provided");
            }
            
            // Decode URL
            $url = urldecode($url);
            
            // Update recipient record
            $stmt = $this->db->prepare("
                UPDATE email_recipients 
                SET clicked_at = CASE 
                    WHEN clicked_at IS NULL THEN NOW() 
                    ELSE clicked_at 
                END,
                click_count = COALESCE(click_count, 0) + 1
                WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            
            // Get campaign ID for stats update
            $stmt = $this->db->prepare("
                SELECT campaign_id FROM email_recipients WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            $recipient = $stmt->fetch();
            
            if ($recipient) {
                // Update campaign click count
                $stmt = $this->db->prepare("
                    UPDATE email_campaigns 
                    SET clicked_count = (
                        SELECT COUNT(DISTINCT id) 
                        FROM email_recipients 
                        WHERE campaign_id = ? AND clicked_at IS NOT NULL
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$recipient['campaign_id'], $recipient['campaign_id']]);
                
                // Log click details
                $stmt = $this->db->prepare("
                    INSERT INTO email_clicks (recipient_id, campaign_id, url, clicked_at)
                    SELECT id, campaign_id, ?, NOW() 
                    FROM email_recipients 
                    WHERE tracking_id = ?
                ");
                $stmt->execute([$url, $trackingId]);
            }
            
            // Redirect to actual URL
            header("Location: " . $url);
            exit;
            
        } catch (Exception $e) {
            error_log("Email click tracking error: " . $e->getMessage());
            header("Location: /");
            exit;
        }
    }
    
    /**
     * Handle unsubscribe
     */
    public function unsubscribe($token) {
        try {
            // Decode token
            $decoded = base64_decode($token);
            list($email, $campaignId, $timestamp) = explode('|', $decoded);
            
            // Mark as unsubscribed in recipients table
            $stmt = $this->db->prepare("
                UPDATE email_recipients 
                SET unsubscribed_at = NOW(), status = 'unsubscribed'
                WHERE email = ? AND campaign_id = ?
            ");
            $stmt->execute([$email, $campaignId]);
            
            // Add to global unsubscribe list
            $stmt = $this->db->prepare("
                INSERT INTO unsubscribed_emails (email, campaign_id, unsubscribed_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE unsubscribed_at = NOW()
            ");
            $stmt->execute([$email, $campaignId]);
            
            // Show unsubscribe confirmation page
            $this->showUnsubscribePage($email, true);
            
        } catch (Exception $e) {
            error_log("Unsubscribe error: " . $e->getMessage());
            $this->showUnsubscribePage('', false);
        }
    }
    
    /**
     * Show unsubscribe page
     */
    private function showUnsubscribePage($email, $success) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Unsubscribe</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($success): ?>
                                    <h4 class="text-success">Successfully Unsubscribed</h4>
                                    <p>You have been unsubscribed from our mailing list.</p>
                                    <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
                                    <p>You will no longer receive emails from us.</p>
                                <?php else: ?>
                                    <h4 class="text-danger">Unsubscribe Failed</h4>
                                    <p>We couldn't process your unsubscribe request.</p>
                                    <p>Please contact support if you continue to have issues.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}