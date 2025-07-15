<?php
class EmailCampaign extends BaseModel {
    protected $table = 'email_campaigns';
    protected $fillable = [
        'name', 'subject', 'content', 'sender_name', 'sender_email', 'reply_to_email',
        'campaign_type', 'status', 'scheduled_at', 'created_by'
    ];
    
    public function getByStatus($status) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE status = ?");
        $stmt->execute([$status]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function getWithStats($id) {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                COUNT(cr.id) as total_recipients,
                SUM(CASE WHEN cr.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN cr.status = 'opened' THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN cr.status = 'clicked' THEN 1 ELSE 0 END) as clicked_count,
                SUM(CASE WHEN cr.status = 'replied' THEN 1 ELSE 0 END) as replied_count,
                SUM(CASE WHEN cr.status = 'bounced' THEN 1 ELSE 0 END) as bounced_count,
                SUM(CASE WHEN cr.status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed_count
            FROM {$this->table} c
            LEFT JOIN campaign_recipients cr ON c.id = cr.campaign_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function addRecipients($campaignId, $contactIds) {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_recipients (campaign_id, contact_id, tracking_id, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            
            foreach ($contactIds as $contactId) {
                $trackingId = uniqid('track_');
                $stmt->execute([$campaignId, $contactId, $trackingId]);
            }
            
            // Update campaign recipient count
            $updateStmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET total_recipients = (
                    SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ?
                )
                WHERE id = ?
            ");
            $updateStmt->execute([$campaignId, $campaignId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function getRecipients($campaignId, $status = null) {
        $sql = "
            SELECT cr.*, c.first_name, c.last_name, c.email, c.phone, c.company
            FROM campaign_recipients cr
            JOIN contacts c ON cr.contact_id = c.id
            WHERE cr.campaign_id = ?
        ";
        
        $params = [$campaignId];
        
        if ($status) {
            $sql .= " AND cr.status = ?";
            $params[] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getCampaignStats() {
        $stmt = $this->db->query("
            SELECT 
                campaign_type,
                COUNT(*) as total_campaigns,
                AVG(opened_count) as avg_open_rate,
                AVG(clicked_count) as avg_click_rate,
                SUM(sent_count) as total_emails_sent
            FROM {$this->table}
            WHERE status = 'completed'
            GROUP BY campaign_type
        ");
        return $stmt->fetchAll();
    }
}
