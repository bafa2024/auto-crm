<?php
// campaign_progress.php - View campaign progress and delivery stats
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'services/EmailCampaignService.php';

$database = new Database();
$campaignService = new EmailCampaignService($database);

$campaignId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$campaignId) {
    echo '<div class="alert alert-danger">No campaign ID specified.</div>';
    exit;
}

$campaign = $campaignService->getCampaignById($campaignId);
$stats = $campaignService->getCampaignStats($campaignId);

if (!$campaign) {
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

// Timeline events
$timeline = [];
$timeline[] = [
    'label' => 'Created',
    'time' => $campaign['created_at'],
    'desc' => 'Campaign was created.'
];
if ($campaign['status'] === 'sending' || $campaign['status'] === 'completed') {
    $timeline[] = [
        'label' => 'Sending Started',
        'time' => $campaign['updated_at'],
        'desc' => 'Sending started.'
    ];
}
if ($campaign['status'] === 'completed') {
    $timeline[] = [
        'label' => 'Completed',
        'time' => $campaign['updated_at'],
        'desc' => 'All emails processed.'
    ];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campaign Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timeline { border-left: 3px solid #5B5FDE; margin-left: 20px; padding-left: 20px; }
        .timeline-event { margin-bottom: 30px; }
        .timeline-label { font-weight: bold; color: #5B5FDE; }
        .timeline-time { font-size: 0.95em; color: #888; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Campaign Progress: <?php echo htmlspecialchars($campaign['name']); ?></h2>
    <div class="mb-3">
        <span class="badge bg-<?php echo $campaign['status'] === 'completed' ? 'success' : ($campaign['status'] === 'sending' ? 'primary' : 'secondary'); ?>">
            <?php echo ucfirst($campaign['status']); ?>
        </span>
        <span class="ms-3">Created: <?php echo date('M d, Y H:i', strtotime($campaign['created_at'])); ?></span>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Delivery Stats</div>
                <div class="card-body">
                    <p><strong>Total Recipients:</strong> <?php echo $stats['total_sends'] ?? 0; ?></p>
                    <p><strong>Sent:</strong> <?php echo $stats['sent_count'] ?? 0; ?></p>
                    <p><strong>Failed:</strong> <?php echo $stats['failed_count'] ?? 0; ?></p>
                    <p><strong>Opened:</strong> <?php echo $stats['opened_count'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Timeline</div>
                <div class="card-body timeline">
                    <?php foreach ($timeline as $event): ?>
                        <div class="timeline-event">
                            <div class="timeline-label"><?php echo $event['label']; ?></div>
                            <div class="timeline-time"><?php echo date('M d, Y H:i', strtotime($event['time'])); ?></div>
                            <div><?php echo $event['desc']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Confirmation</div>
        <div class="card-body">
            <p>
                <?php if (($stats['sent_count'] ?? 0) > 0): ?>
                    <span class="text-success">Emails were sent to recipients.</span><br>
                <?php else: ?>
                    <span class="text-danger">No emails sent yet.</span><br>
                <?php endif; ?>
                <?php if (($stats['opened_count'] ?? 0) > 0): ?>
                    <span class="text-success">At least one recipient opened the email (inbox confirmed).</span>
                <?php else: ?>
                    <span class="text-warning">No opens tracked yet. (May still be in spam or not opened)</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <a href="campaigns.php" class="btn btn-secondary mt-4">Back to Campaigns</a>
</div>
</body>
</html> 