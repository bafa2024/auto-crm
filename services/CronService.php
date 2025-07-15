<?php
class CronService {
    private $db;
    private $emailService;
    
    public function __construct($database) {
        $this->db = $database;
        $this->emailService = new EmailService($database);
    }
    
    public function processEmailQueue() {
        echo "Processing email queue...\n";
        
        $result = $this->emailService->processEmailQueue();
        
        if ($result['success']) {
            echo "Processed: {$result['processed']}, Sent: {$result['sent']}, Failed: {$result['failed']}\n";
        } else {
            echo "Error: {$result['message']}\n";
        }
        
        return $result;
    }
    
    public function cleanupOldFiles() {
        echo "Cleaning up old files...\n";
        
        $uploadPath = UPLOAD_PATH;
        $logPath = LOG_PATH;
        $tempPath = TEMP_PATH;
        
        $cleanupPaths = [$uploadPath, $logPath, $tempPath];
        $cleanedFiles = 0;
        
        foreach ($cleanupPaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '*');
                foreach ($files as $file) {
                    if (is_file($file) && (time() - filemtime($file)) > (7 * 24 * 3600)) { // 7 days old
                        unlink($file);
                        $cleanedFiles++;
                    }
                }
            }
        }
        
        echo "Cleaned up {$cleanedFiles} old files\n";
        return $cleanedFiles;
    }
    
    public function updateCampaignStatuses() {
        echo "Updating campaign statuses...\n";
        
        // Mark campaigns as completed if all emails are processed
        $stmt = $this->db->prepare("
            UPDATE email_campaigns 
            SET status = 'completed', updated_at = NOW()
            WHERE status = 'sending' 
            AND id NOT IN (
                SELECT DISTINCT campaign_id 
                FROM email_queue 
                WHERE status IN ('pending', 'processing')
            )
        ");
        
        $stmt->execute();
        $updatedCampaigns = $stmt->rowCount();
        
        echo "Updated {$updatedCampaigns} campaign statuses\n";
        return $updatedCampaigns;
    }
    
    public function generateDailyReport() {
        echo "Generating daily report...\n";
        
        $today = date('Y-m-d');
        
        // Get statistics for today
        $stats = [
            'date' => $today,
            'emails_sent' => 0,
            'emails_opened' => 0,
            'emails_clicked' => 0,
            'new_contacts' => 0,
            'active_campaigns' => 0
        ];
        
        // Email stats
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as sent FROM email_queue 
            WHERE status = 'sent' AND DATE(sent_at) = ?
        ");
        $stmt->execute([$today]);
        $stats['emails_sent'] = $stmt->fetch()['sent'];
        
        // Contact stats
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as new_contacts FROM contacts 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$today]);
        $stats['new_contacts'] = $stmt->fetch()['new_contacts'];
        
        // Campaign stats
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as active FROM email_campaigns 
            WHERE status IN ('sending', 'scheduled')
        ");
        $stmt->execute();
        $stats['active_campaigns'] = $stmt->fetch()['active'];
        
        // Save report to log
        $reportData = json_encode($stats, JSON_PRETTY_PRINT);
        file_put_contents(LOG_PATH . "daily_report_{$today}.json", $reportData);
        
        echo "Daily report generated and saved\n";
        return $stats;
    }
}
?> 