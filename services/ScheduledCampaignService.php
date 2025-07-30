<?php
// ScheduledCampaignService.php - Handle scheduled email campaigns
// This service manages campaign scheduling, execution, and status updates

class ScheduledCampaignService {
    private $db;
    private $dbType;
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        $this->db = $database->getConnection();
        $this->dbType = $database->getDatabaseType();
    }
    
    /**
     * Get campaigns that are scheduled and ready to send
     */
    public function getReadyToSendCampaigns() {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "SELECT * FROM email_campaigns 
                    WHERE status = 'scheduled' 
                    AND schedule_date IS NOT NULL 
                    AND schedule_date <= :current_time
                    ORDER BY schedule_date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':current_time' => $currentTime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting ready to send campaigns: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all scheduled campaigns (for display)
     */
    public function getScheduledCampaigns() {
        try {
            $sql = "SELECT * FROM email_campaigns 
                    WHERE status = 'scheduled' 
                    AND schedule_date IS NOT NULL 
                    ORDER BY schedule_date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting scheduled campaigns: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update campaign status
     */
    public function updateCampaignStatus($campaignId, $status) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "UPDATE email_campaigns 
                    SET status = :status, updated_at = :updated_at 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':updated_at' => $currentTime,
                ':id' => $campaignId
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error updating campaign status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule a campaign for future sending
     */
    public function scheduleCampaign($campaignId, $scheduleDate, $scheduleType = 'once', $frequency = null) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "UPDATE email_campaigns 
                    SET status = 'scheduled', 
                        schedule_date = :schedule_date,
                        schedule_type = :schedule_type,
                        frequency = :frequency,
                        updated_at = :updated_at 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':schedule_date' => $scheduleDate,
                ':schedule_type' => $scheduleType,
                ':frequency' => $frequency,
                ':updated_at' => $currentTime,
                ':id' => $campaignId
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error scheduling campaign: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get campaign scheduling statistics
     */
    public function getSchedulingStats() {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "SELECT 
                    COUNT(*) as total_scheduled,
                    COUNT(CASE WHEN schedule_date <= :current_time THEN 1 END) as ready_to_send,
                    COUNT(CASE WHEN schedule_date > :current_time THEN 1 END) as future_scheduled
                    FROM email_campaigns 
                    WHERE status = 'scheduled'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':current_time' => $currentTime]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting scheduling stats: " . $e->getMessage());
            return [
                'total_scheduled' => 0,
                'ready_to_send' => 0,
                'future_scheduled' => 0
            ];
        }
    }
    
    /**
     * Process recurring campaigns
     */
    public function processRecurringCampaigns() {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            // Get completed campaigns that have recurring schedules
            $sql = "SELECT * FROM email_campaigns 
                    WHERE status = 'completed' 
                    AND frequency IS NOT NULL 
                    AND frequency != ''
                    ORDER BY updated_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $recurringCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processedCount = 0;
            
            foreach ($recurringCampaigns as $campaign) {
                $nextScheduleDate = $this->calculateNextScheduleDate(
                    $campaign['schedule_date'], 
                    $campaign['frequency']
                );
                
                if ($nextScheduleDate && $nextScheduleDate <= $currentTime) {
                    // Create a new campaign based on the recurring one
                    $newCampaignId = $this->createRecurringCampaign($campaign, $nextScheduleDate);
                    
                    if ($newCampaignId) {
                        $processedCount++;
                        error_log("Created recurring campaign ID: $newCampaignId from original: {$campaign['id']}");
                    }
                }
            }
            
            return [
                'success' => true,
                'processed' => $processedCount
            ];
            
        } catch (Exception $e) {
            error_log("Error processing recurring campaigns: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate next schedule date based on frequency
     */
    private function calculateNextScheduleDate($lastScheduleDate, $frequency) {
        try {
            $date = new DateTime($lastScheduleDate);
            
            switch ($frequency) {
                case 'daily':
                    $date->add(new DateInterval('P1D'));
                    break;
                case 'weekly':
                    $date->add(new DateInterval('P7D'));
                    break;
                case 'monthly':
                    $date->add(new DateInterval('P1M'));
                    break;
                case 'quarterly':
                    $date->add(new DateInterval('P3M'));
                    break;
                case 'yearly':
                    $date->add(new DateInterval('P1Y'));
                    break;
                default:
                    return null;
            }
            
            return $date->format('Y-m-d H:i:s');
            
        } catch (Exception $e) {
            error_log("Error calculating next schedule date: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new campaign based on a recurring campaign
     */
    private function createRecurringCampaign($originalCampaign, $scheduleDate) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO email_campaigns (
                user_id, name, subject, email_content, from_name, from_email,
                schedule_type, schedule_date, frequency, status, created_at, updated_at
            ) VALUES (
                :user_id, :name, :subject, :email_content, :from_name, :from_email,
                :schedule_type, :schedule_date, :frequency, :status, :created_at, :updated_at
            )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $originalCampaign['user_id'],
                ':name' => $originalCampaign['name'] . ' (Recurring)',
                ':subject' => $originalCampaign['subject'],
                ':email_content' => $originalCampaign['email_content'],
                ':from_name' => $originalCampaign['from_name'],
                ':from_email' => $originalCampaign['from_email'],
                ':schedule_type' => 'recurring',
                ':schedule_date' => $scheduleDate,
                ':frequency' => $originalCampaign['frequency'],
                ':status' => 'scheduled',
                ':created_at' => $currentTime,
                ':updated_at' => $currentTime
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error creating recurring campaign: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get upcoming scheduled campaigns (next 7 days)
     */
    public function getUpcomingScheduledCampaigns($days = 7) {
        try {
            $currentTime = date('Y-m-d H:i:s');
            $futureDate = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            $sql = "SELECT * FROM email_campaigns 
                    WHERE status = 'scheduled' 
                    AND schedule_date IS NOT NULL 
                    AND schedule_date > :current_time 
                    AND schedule_date <= :future_date
                    ORDER BY schedule_date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':current_time' => $currentTime,
                ':future_date' => $futureDate
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting upcoming scheduled campaigns: " . $e->getMessage());
            return [];
        }
    }
}
?> 