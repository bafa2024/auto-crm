<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/base_path.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit();
}

$campaignId = $_GET['id'] ?? 0;

$database = new Database();
$db = $database->getConnection();

// Get campaign details
$stmt = $db->prepare("
    SELECT c.*, u.first_name as creator_first_name, u.last_name as creator_last_name
    FROM email_campaigns c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ? AND c.created_by = ?
");
$stmt->execute([$campaignId, $_SESSION["user_id"]]);
$campaign = $stmt->fetch();

if (!$campaign) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/campaigns'));
    exit();
}

// Get permissions
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Get campaign statistics
$stats = [];
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT id) as total_recipients,
        COUNT(DISTINCT CASE WHEN status = 'sent' THEN id END) as sent_count,
        COUNT(DISTINCT CASE WHEN opened_at IS NOT NULL THEN id END) as opened_count,
        COUNT(DISTINCT CASE WHEN clicked_at IS NOT NULL THEN id END) as clicked_count,
        COUNT(DISTINCT CASE WHEN bounced_at IS NOT NULL THEN id END) as bounced_count,
        COUNT(DISTINCT CASE WHEN unsubscribed_at IS NOT NULL THEN id END) as unsubscribed_count,
        COUNT(DISTINCT CASE WHEN status = 'failed' THEN id END) as failed_count
    FROM email_recipients
    WHERE campaign_id = ?
");
$stmt->execute([$campaignId]);
$stats = $stmt->fetch();

// Calculate rates
$stats['open_rate'] = $stats['sent_count'] > 0 
    ? round(($stats['opened_count'] / $stats['sent_count']) * 100, 2) 
    : 0;
    
$stats['click_rate'] = $stats['sent_count'] > 0 
    ? round(($stats['clicked_count'] / $stats['sent_count']) * 100, 2) 
    : 0;
    
$stats['bounce_rate'] = $stats['sent_count'] > 0 
    ? round(($stats['bounced_count'] / $stats['sent_count']) * 100, 2) 
    : 0;

// Get recent activity
$stmt = $db->prepare("
    SELECT 
        'opened' as action,
        r.email,
        r.opened_at as action_time,
        c.first_name,
        c.last_name
    FROM email_recipients r
    LEFT JOIN contacts c ON r.contact_id = c.id
    WHERE r.campaign_id = ? AND r.opened_at IS NOT NULL
    UNION ALL
    SELECT 
        'clicked' as action,
        r.email,
        r.clicked_at as action_time,
        c.first_name,
        c.last_name
    FROM email_recipients r
    LEFT JOIN contacts c ON r.contact_id = c.id
    WHERE r.campaign_id = ? AND r.clicked_at IS NOT NULL
    UNION ALL
    SELECT 
        'unsubscribed' as action,
        r.email,
        r.unsubscribed_at as action_time,
        c.first_name,
        c.last_name
    FROM email_recipients r
    LEFT JOIN contacts c ON r.contact_id = c.id
    WHERE r.campaign_id = ? AND r.unsubscribed_at IS NOT NULL
    ORDER BY action_time DESC
    LIMIT 50
");
$stmt->execute([$campaignId, $campaignId, $campaignId]);
$recentActivity = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($campaign['name']); ?> - Campaign Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 1rem;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .activity-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .progress-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <i class="fas fa-user-circle fa-3x"></i>
                        <h6 class="mt-2"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></h6>
                        <small><?php echo ucfirst($_SESSION["user_role"]); ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path('employee/email-dashboard'); ?>">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?php echo base_path('employee/campaigns'); ?>">
                                <i class="fas fa-envelope me-2"></i> My Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path('employee/campaigns/create'); ?>">
                                <i class="fas fa-plus-circle me-2"></i> Create Campaign
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path('employee/contacts'); ?>">
                                <i class="fas fa-address-book me-2"></i> Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path('employee/profile'); ?>">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="<?php echo base_path('employee/logout'); ?>">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($campaign['name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($campaign['status'] === 'draft' && $permissions['can_send_campaigns']): ?>
                            <button class="btn btn-success me-2" onclick="sendCampaign()">
                                <i class="fas fa-paper-plane me-2"></i>Send Campaign
                            </button>
                        <?php elseif ($campaign['status'] === 'active' && $permissions['can_send_campaigns']): ?>
                            <button class="btn btn-warning me-2" onclick="pauseCampaign()">
                                <i class="fas fa-pause me-2"></i>Pause
                            </button>
                        <?php elseif ($campaign['status'] === 'paused' && $permissions['can_send_campaigns']): ?>
                            <button class="btn btn-success me-2" onclick="resumeCampaign()">
                                <i class="fas fa-play me-2"></i>Resume
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo base_path('employee/campaigns'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>

                <!-- Campaign Status -->
                <div class="alert alert-<?php 
                    echo $campaign['status'] === 'active' || $campaign['status'] === 'sending' ? 'success' : 
                        ($campaign['status'] === 'paused' ? 'warning' : 
                        ($campaign['status'] === 'completed' ? 'info' : 
                        ($campaign['status'] === 'failed' || $campaign['status'] === 'completed_with_errors' ? 'danger' : 'secondary'))); 
                ?> d-flex align-items-center">
                    <i class="fas fa-<?php 
                        echo $campaign['status'] === 'active' ? 'rocket' : 
                            ($campaign['status'] === 'sending' ? 'spinner fa-spin' : 
                            ($campaign['status'] === 'paused' ? 'pause-circle' : 
                            ($campaign['status'] === 'completed' ? 'check-circle' : 
                            ($campaign['status'] === 'draft' ? 'edit' : 'exclamation-circle')))); 
                    ?> me-2"></i>
                    <strong>Status: <?php echo ucfirst(str_replace('_', ' ', $campaign['status'])); ?></strong>
                    <?php if ($campaign['scheduled_at'] && $campaign['status'] === 'scheduled'): ?>
                        - Scheduled for <?php echo date('M d, Y g:i A', strtotime($campaign['scheduled_at'])); ?>
                    <?php endif; ?>
                </div>

                <!-- Campaign Progress -->
                <?php if (in_array($campaign['status'], ['active', 'sending', 'completed', 'completed_with_errors'])): ?>
                <div class="progress-section">
                    <h5>Sending Progress</h5>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $stats['total_recipients'] > 0 ? ($stats['sent_count'] / $stats['total_recipients']) * 100 : 0; ?>%">
                            <?php echo $stats['sent_count']; ?> / <?php echo $stats['total_recipients']; ?> sent
                        </div>
                    </div>
                    <?php if ($stats['failed_count'] > 0): ?>
                        <small class="text-danger mt-2 d-block">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $stats['failed_count']; ?> failed
                        </small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card primary h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                            Total Recipients
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['total_recipients']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card success h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                            Delivered
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['sent_count']); ?></div>
                                        <small class="text-muted"><?php echo $stats['total_recipients'] > 0 ? round(($stats['sent_count'] / $stats['total_recipients']) * 100, 1) : 0; ?>%</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card info h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                            Opened
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['opened_count']); ?></div>
                                        <small class="text-muted"><?php echo $stats['open_rate']; ?>%</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-envelope-open fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card warning h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                            Clicked
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['clicked_count']); ?></div>
                                        <small class="text-muted"><?php echo $stats['click_rate']; ?>%</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-mouse-pointer fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Details -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Campaign Details</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Subject:</th>
                                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>From Name:</th>
                                        <td><?php echo htmlspecialchars($campaign['sender_name'] ?? $campaign['from_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>From Email:</th>
                                        <td><?php echo htmlspecialchars($campaign['sender_email'] ?? $campaign['from_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Created:</th>
                                        <td><?php echo date('M d, Y g:i A', strtotime($campaign['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Send Type:</th>
                                        <td>
                                            <span class="badge bg-<?php echo $campaign['send_type'] === 'immediate' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($campaign['send_type'] ?? 'immediate'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Performance Metrics</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Bounce Rate:</th>
                                        <td><?php echo $stats['bounce_rate']; ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Unsubscribe Rate:</th>
                                        <td><?php echo $stats['sent_count'] > 0 ? round(($stats['unsubscribed_count'] / $stats['sent_count']) * 100, 2) : 0; ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Failed:</th>
                                        <td class="<?php echo $stats['failed_count'] > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo $stats['failed_count']; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Bounced:</th>
                                        <td><?php echo $stats['bounced_count']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Unsubscribed:</th>
                                        <td><?php echo $stats['unsubscribed_count']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted text-center py-4">No activity yet</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Contact</th>
                                            <th>Email</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <tr>
                                                <td>
                                                    <span class="activity-icon bg-<?php 
                                                        echo $activity['action'] === 'opened' ? 'info' : 
                                                            ($activity['action'] === 'clicked' ? 'warning' : 'danger'); 
                                                    ?> text-white">
                                                        <i class="fas fa-<?php 
                                                            echo $activity['action'] === 'opened' ? 'envelope-open' : 
                                                                ($activity['action'] === 'clicked' ? 'mouse-pointer' : 'user-times'); 
                                                        ?>"></i>
                                                    </span>
                                                    <?php echo ucfirst($activity['action']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(trim($activity['first_name'] . ' ' . $activity['last_name']) ?: 'Unknown'); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['email']); ?></td>
                                                <td><?php echo date('M d, g:i A', strtotime($activity['action_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const campaignId = <?php echo $campaignId; ?>;
        const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
        
        function sendCampaign() {
            if (confirm('Are you sure you want to send this campaign now?')) {
                fetch(`${basePath}/api/campaigns/${campaignId}/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ immediate: true })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Campaign is being sent!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to send campaign');
                    }
                })
                .catch(error => {
                    alert('Network error. Please try again.');
                });
            }
        }
        
        function pauseCampaign() {
            updateStatus('paused');
        }
        
        function resumeCampaign() {
            updateStatus('active');
        }
        
        function updateStatus(status) {
            fetch(`${basePath}/api/campaigns/${campaignId}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update campaign');
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
            });
        }
        
        // Auto-refresh if campaign is sending
        <?php if ($campaign['status'] === 'sending' || $campaign['status'] === 'active'): ?>
        setTimeout(() => {
            location.reload();
        }, 10000); // Refresh every 10 seconds
        <?php endif; ?>
    </script>
</body>
</html>