<?php
// BulkEmailsController.php
// Controller for handling bulk email operations

require_once __DIR__ . '/../services/EmailService.php';

class BulkEmailsController
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function sendBulkEmail($subject, $body, $recipients, $fromName = 'AutoDial Pro', $fromEmail = 'noreply@acrm.regrowup.ca')
    {
        // Initialize email service
        $emailService = new EmailService($this->db);
        
        $successCount = 0;
        $failCount = 0;
        $results = [];
        
        // Send emails to each recipient
        foreach ($recipients as $recipient) {
            $result = $emailService->sendInstantEmail([
                'to' => $recipient,
                'subject' => $subject,
                'message' => $body,
                'from_name' => $fromName,
                'from_email' => $fromEmail
            ]);
            
            if ($result === true) {
                $successCount++;
                $results[] = "✓ Sent to: $recipient";
            } else {
                $failCount++;
                $results[] = "✗ Failed to send to: $recipient";
            }
        }
        
        return [
            'successCount' => $successCount,
            'failCount' => $failCount,
            'results' => $results,
            'total' => count($recipients)
        ];
    }

    //get all the contacts from the database to list for selection
    public function getAllContacts()
    {
        $query = "SELECT id, name, email FROM email_recipients ORDER BY name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a contact by ID
    public function getContactById($id)
    {
        $query = "SELECT id, name, email FROM email_recipients WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get email templates (placeholder - can be extended later)
    public function getEmailTemplates()
    {
        // For now, return some basic templates
        return [
            [
                'id' => 1,
                'name' => 'Welcome Email',
                'subject' => 'Welcome to AutoDial Pro!',
                'body' => 'Dear Customer,\n\nWelcome to AutoDial Pro! We\'re excited to have you on board.\n\nBest regards,\nThe AutoDial Pro Team'
            ],
            [
                'id' => 2,
                'name' => 'Newsletter',
                'subject' => 'Monthly Newsletter',
                'body' => 'Dear Subscribers,\n\nHere\'s what\'s new this month...\n\nBest regards,\nThe AutoDial Pro Team'
            ]
        ];
    }

    // Get email history (placeholder - can be extended later)
    public function getEmailHistory($limit = 10)
    {
        // For now, return empty array - can be implemented later with email_logs table
        return [];
    }

    // Save email template (placeholder - can be extended later)
    public function saveEmailTemplate($name, $subject, $body)
    {
        // For now, just return success - can be implemented later with templates table
        return ['id' => time(), 'message' => 'Template saved successfully'];
    }

    // Update email template (placeholder - can be extended later)
    public function updateEmailTemplate($id, $name, $subject, $body)
    {
        // For now, just return success - can be implemented later
        return ['message' => 'Template updated successfully'];
    }

    // Delete email template (placeholder - can be extended later)
    public function deleteEmailTemplate($id)
    {
        // For now, just return success - can be implemented later
        return ['message' => 'Template deleted successfully'];
    }

    // Validate email addresses
    public function validateEmails($emails)
    {
        $valid = [];
        $invalid = [];
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            } else {
                $invalid[] = $email;
            }
        }
        
        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'validCount' => count($valid),
            'invalidCount' => count($invalid)
        ];
    }
}

