<?php
require_once "BaseModel.php";

class EmailCampaign extends BaseModel {
    protected $table = "email_campaigns";
    protected $fillable = [
        "name", "subject", "content", "sender_name", "sender_email", "reply_to_email",
        "campaign_type", "status", "scheduled_at", "created_by"
    ];
    
    public function addRecipients($campaignId, $recipients) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            INSERT INTO email_recipients (campaign_id, email, name, company, custom_data, tracking_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $added = 0;
        foreach ($recipients as $recipient) {
            $trackingId = uniqid("track_", true);
            $customData = isset($recipient["custom_data"]) ? json_encode($recipient["custom_data"]) : null;
            
            try {
                $stmt->execute([
                    $campaignId,
                    $recipient["email"],
                    $recipient["name"] ?? null,
                    $recipient["company"] ?? null,
                    $customData,
                    $trackingId
                ]);
                $added++;
            } catch (Exception $e) {
                // Skip duplicate emails
                continue;
            }
        }
        
        // Update total recipients count
        $this->updateRecipientCount($campaignId);
        
        return $added;
    }
    
    public function updateRecipientCount($campaignId) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            UPDATE email_campaigns 
            SET total_recipients = (
                SELECT COUNT(*) FROM email_recipients WHERE campaign_id = ?
            )
            WHERE id = ?
        ");
        
        return $stmt->execute([$campaignId, $campaignId]);
    }
    
    public function getWithStats($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT r.id) as total_recipients,
                SUM(CASE WHEN r.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN r.status = 'opened' THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN r.status = 'clicked' THEN 1 ELSE 0 END) as clicked_count,
                SUM(CASE WHEN r.status = 'bounced' THEN 1 ELSE 0 END) as bounced_count,
                SUM(CASE WHEN r.status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed_count
            FROM email_campaigns c
            LEFT JOIN email_recipients r ON c.id = r.campaign_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getRecipients($campaignId, $status = null) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM email_recipients WHERE campaign_id = ?";
        $params = [$campaignId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}