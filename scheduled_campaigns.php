<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';
require_once 'services/SimpleCampaignScheduler.php';

$database = new Database();
$scheduler = new SimpleCampaignScheduler($database);

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'cancel' && isset($_POST['scheduled_id'])) {
            $result = $scheduler->cancelScheduledCampaign((int)$_POST['scheduled_id']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        } elseif ($_POST['action'] === 'process_pending') {
            $result = $scheduler->processPendingCampaigns();
            if ($result['success']) {
                $message = "Processed {$result['processed']} campaigns";
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        }
    }
}

// Get scheduled campaigns
$scheduledCampaigns = $scheduler->getScheduledCampaigns(50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Campaigns - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-calendar-event"></i> Scheduled Campaigns</h2>
                    <div>
                        <a href="campaigns.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Campaigns
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="process_pending">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-circle"></i> Process Pending
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Scheduled Campaigns List</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($scheduledCampaigns) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Campaign</th>
                                            <th>Type</th>
                                            <th>Next Send</th>
                                            <th>Status</th>
                                            <th>Sent/Failed</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scheduledCampaigns as $scheduled): ?>
                                            <tr>
                                                <td><?php echo $scheduled['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($scheduled['campaign_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($scheduled['subject']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($scheduled['schedule_type']); ?>
                                                        <?php if ($scheduled['frequency'] && $scheduled['frequency'] !== 'once'): ?>
                                                            (<?php echo ucfirst($scheduled['frequency']); ?>)
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($scheduled['next_send_at']): ?>
                                                        <?php echo date('M j, Y H:i', strtotime($scheduled['next_send_at'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'running' => 'primary',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'cancelled' => 'secondary'
                                                    ];
                                                    $statusColor = $statusColors[$scheduled['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                                        <?php echo ucfirst($scheduled['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        📧 <?php echo $scheduled['sent_count']; ?> sent<br>
                                                        ❌ <?php echo $scheduled['failed_count']; ?> failed
                                                    </small>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y H:i', strtotime($scheduled['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($scheduled['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to cancel this scheduled campaign?')">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="scheduled_id" value="<?php echo $scheduled['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-x-circle"></i> Cancel
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                                <h5 class="mt-3">No Scheduled Campaigns</h5>
                                <p class="text-muted">No campaigns have been scheduled yet.</p>
                                <a href="campaigns.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Schedule a Campaign
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h3 class="text-warning">
                                    <?php 
                                    echo count(array_filter($scheduledCampaigns, function($s) { 
                                        return $s['status'] === 'pending'; 
                                    })); 
                                    ?>
                                </h3>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h3 class="text-success">
                                    <?php 
                                    echo count(array_filter($scheduledCampaigns, function($s) { 
                                        return $s['status'] === 'completed'; 
                                    })); 
                                    ?>
                                </h3>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger bg-opacity-10">
                            <div class="card-body text-center">
                                <h3 class="text-danger">
                                    <?php 
                                    echo count(array_filter($scheduledCampaigns, function($s) { 
                                        return $s['status'] === 'failed'; 
                                    })); 
                                    ?>
                                </h3>
                                <p class="mb-0">Failed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h3 class="text-info">
                                    <?php 
                                    echo array_sum(array_column($scheduledCampaigns, 'sent_count')); 
                                    ?>
                                </h3>
                                <p class="mb-0">Total Sent</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
