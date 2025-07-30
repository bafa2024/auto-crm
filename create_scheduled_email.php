<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/EmailCampaignService.php';
require_once __DIR__ . '/services/ScheduledCampaignService.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $emailCampaignService = new EmailCampaignService($database);
        $scheduledCampaignService = new ScheduledCampaignService($database);
        
        // Create campaign data
        $campaignData = [
            'name' => $_POST['campaign_name'],
            'subject' => $_POST['subject'],
            'content' => $_POST['content'],
            'sender_name' => $_POST['sender_name'],
            'sender_email' => $_POST['sender_email'],
            'status' => 'draft'
        ];
        
        // Create the campaign
        $result = $emailCampaignService->createCampaign($campaignData);
        
        if ($result['success']) {
            $campaignId = $result['campaign_id'];
            
            // Schedule the campaign
            $scheduleDate = $_POST['schedule_date'] . ' ' . $_POST['schedule_time'];
            $scheduled = $scheduledCampaignService->scheduleCampaign(
                $campaignId, 
                $scheduleDate, 
                'once'
            );
            
            if ($scheduled) {
                $message = "Campaign scheduled successfully! It will be sent at $scheduleDate";
            } else {
                $error = "Failed to schedule campaign";
            }
        } else {
            $error = "Failed to create campaign: " . $result['message'];
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Scheduled Email - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-envelope-plus me-2"></i>
                            Create Scheduled Email Campaign
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="campaign_name" class="form-label">Campaign Name</label>
                                    <input type="text" class="form-control" id="campaign_name" name="campaign_name" 
                                           value="Test Campaign <?php echo date('Y-m-d H:i'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="sender_name" class="form-label">Sender Name</label>
                                    <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                           value="AutoDial Pro" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sender_email" class="form-label">Sender Email</label>
                                    <input type="email" class="form-control" id="sender_email" name="sender_email" 
                                           value="noreply@autocrm.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="subject" class="form-label">Subject Line</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                           value="Test Email from AutoDial Pro" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="schedule_date" class="form-label">Schedule Date</label>
                                    <input type="date" class="form-control" id="schedule_date" name="schedule_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="schedule_time" class="form-label">Schedule Time</label>
                                    <input type="time" class="form-control" id="schedule_time" name="schedule_time" 
                                           value="<?php echo date('H:i', strtotime('+2 minutes')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">Email Content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" required>Hello {{first_name}},

This is a test email from AutoDial Pro CRM.

You can use merge tags like:
- {{first_name}} - First name
- {{name}} - Full name
- {{email}} - Email address
- {{company}} - Company name

Best regards,
AutoDial Pro Team</textarea>
                                <div class="form-text">
                                    Use merge tags like {{first_name}}, {{name}}, {{email}}, {{company}} to personalize emails.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard/" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Schedule Campaign
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Instructions
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Fill out the form above to create a scheduled email campaign</li>
                            <li>The campaign will be scheduled to send at the specified date and time</li>
                            <li>Make sure the cron job is running: <code>* * * * * /usr/bin/php /path/to/cron/process_scheduled_campaigns.php</code></li>
                            <li>Check the logs at <code>logs/cron_errors.log</code> for processing details</li>
                            <li>Monitor campaign status in the dashboard</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <strong>Note:</strong> For testing, you can schedule the email to send in 2-3 minutes from now.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 