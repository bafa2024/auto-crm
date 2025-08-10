<?php
// campaign_progress.php - View campaign progress and delivery stats
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
require_once 'services/EmailCampaignService.php';

$database = new Database();
$db = $database->getConnection();
$campaignService = new EmailCampaignService($database);

$campaignId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$campaignId) {
    header("Location: campaigns.php?error=No campaign ID specified");
    exit;
}

$campaign = $campaignService->getCampaignById($campaignId);
if (!$campaign) {
    header("Location: campaigns.php?error=Campaign not found");
    exit;
}

// Get detailed send statistics
$sql = "SELECT 
        COUNT(*) as total_sends,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        MIN(sent_at) as first_sent,
        MAX(sent_at) as last_sent
        FROM campaign_sends 
        WHERE campaign_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$campaignId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed recipient status with email validation
$sql = "SELECT 
        cs.id,
        cs.recipient_id,
        cs.recipient_email,
        cs.status,
        cs.sent_at,
        cs.opened_at,
        cs.clicked_at,
        cs.tracking_id,
        r.name,
        r.company,
        CASE 
            WHEN cs.recipient_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$' THEN 'invalid'
            WHEN cs.status = 'failed' AND cs.recipient_email LIKE '%@%.%' THEN 'failed_delivery'
            WHEN cs.status = 'failed' THEN 'invalid_domain'
            ELSE cs.status
        END as detailed_status
        FROM campaign_sends cs
        LEFT JOIN email_recipients r ON cs.recipient_id = r.id
        WHERE cs.campaign_id = ?
        ORDER BY cs.sent_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$campaignId]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group recipients by status
$recipientsByStatus = [
    'sent' => [],
    'failed' => [],
    'invalid' => [],
    'pending' => []
];

foreach ($recipients as $recipient) {
    if ($recipient['detailed_status'] === 'invalid') {
        $recipientsByStatus['invalid'][] = $recipient;
    } elseif ($recipient['status'] === 'sent') {
        $recipientsByStatus['sent'][] = $recipient;
    } elseif ($recipient['status'] === 'failed') {
        $recipientsByStatus['failed'][] = $recipient;
    } else {
        $recipientsByStatus['pending'][] = $recipient;
    }
}

// Calculate percentages
$totalRecipients = $stats['total_sends'] ?: 1;
$sentPercentage = ($stats['sent_count'] / $totalRecipients) * 100;
$failedPercentage = ($stats['failed_count'] / $totalRecipients) * 100;
$pendingPercentage = ($stats['pending_count'] / $totalRecipients) * 100;
$invalidCount = count($recipientsByStatus['invalid']);
$invalidPercentage = ($invalidCount / $totalRecipients) * 100;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Progress - <?php echo htmlspecialchars($campaign['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        .progress-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .progress-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        .status-sent {
            background-color: #d4edda;
            color: #155724;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-invalid {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-pending {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .recipient-row {
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 0;
        }
        .recipient-row:last-child {
            border-bottom: none;
        }
        .email-invalid {
            text-decoration: line-through;
            color: #dc3545;
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #6c757d;
        }
        .tab-content {
            padding: 1.5rem;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
        }
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.success::before {
            background: #28a745;
        }
        .timeline-item.danger::before {
            background: #dc3545;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'views/components/header.php'; ?>
    <?php include 'views/components/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Campaign Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><?php echo htmlspecialchars($campaign['name']); ?></h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar"></i> Created: <?php echo date('M d, Y g:i A', strtotime($campaign['created_at'])); ?>
                        <?php if ($stats['first_sent']): ?>
                            | <i class="bi bi-send"></i> First sent: <?php echo date('M d, Y g:i A', strtotime($stats['first_sent'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="campaigns.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Campaigns
                    </a>
                    <button class="btn btn-primary" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-number"><?php echo number_format($stats['total_sends']); ?></div>
                        <div class="stat-label">Total Recipients</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-number"><?php echo number_format($stats['sent_count']); ?></div>
                        <div class="stat-label">Delivered (<?php echo number_format($sentPercentage, 1); ?>%)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-danger text-white">
                        <div class="stat-number"><?php echo number_format($stats['failed_count']); ?></div>
                        <div class="stat-label">Failed (<?php echo number_format($failedPercentage, 1); ?>%)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning text-white">
                        <div class="stat-number"><?php echo number_format($invalidCount); ?></div>
                        <div class="stat-label">Invalid Emails (<?php echo number_format($invalidPercentage, 1); ?>%)</div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Overall Progress</h5>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $sentPercentage; ?>%">
                            <?php echo number_format($sentPercentage, 1); ?>% Delivered
                        </div>
                        <div class="progress-bar bg-danger" style="width: <?php echo $failedPercentage; ?>%">
                            <?php echo number_format($failedPercentage, 1); ?>% Failed
                        </div>
                        <div class="progress-bar bg-warning" style="width: <?php echo $invalidPercentage; ?>%">
                            <?php echo number_format($invalidPercentage, 1); ?>% Invalid
                        </div>
                        <div class="progress-bar bg-secondary" style="width: <?php echo $pendingPercentage; ?>%">
                            <?php echo number_format($pendingPercentage, 1); ?>% Pending
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Recipients List -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#all">
                                All Recipients <span class="badge bg-secondary"><?php echo $stats['total_sends']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#delivered">
                                Delivered <span class="badge bg-success"><?php echo $stats['sent_count']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#failed">
                                Failed <span class="badge bg-danger"><?php echo $stats['failed_count']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#invalid">
                                Invalid Emails <span class="badge bg-warning"><?php echo $invalidCount; ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- All Recipients Tab -->
                        <div class="tab-pane fade show active" id="all">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recipients as $recipient): ?>
                                        <tr>
                                            <td class="<?php echo $recipient['detailed_status'] === 'invalid' ? 'email-invalid' : ''; ?>">
                                                <?php echo htmlspecialchars($recipient['recipient_email']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($recipient['name'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['company'] ?: '-'); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = 'status-pending';
                                                $statusText = 'Pending';
                                                if ($recipient['detailed_status'] === 'invalid') {
                                                    $statusClass = 'status-invalid';
                                                    $statusText = 'Invalid Email';
                                                } elseif ($recipient['status'] === 'sent') {
                                                    $statusClass = 'status-sent';
                                                    $statusText = 'Delivered';
                                                } elseif ($recipient['status'] === 'failed') {
                                                    $statusClass = 'status-failed';
                                                    $statusText = 'Failed';
                                                }
                                                ?>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $recipient['sent_at'] ? date('M d, Y g:i A', strtotime($recipient['sent_at'])) : '-'; ?></td>
                                            <td>
                                                <?php if ($recipient['opened_at']): ?>
                                                    <span class="text-primary" title="Opened at <?php echo date('M d, Y g:i A', strtotime($recipient['opened_at'])); ?>">
                                                        <i class="bi bi-envelope-open"></i> Opened
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($recipient['clicked_at']): ?>
                                                    <span class="text-success" title="Clicked at <?php echo date('M d, Y g:i A', strtotime($recipient['clicked_at'])); ?>">
                                                        <i class="bi bi-link-45deg"></i> Clicked
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Delivered Tab -->
                        <div class="tab-pane fade" id="delivered">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Delivered At</th>
                                            <th>Engagement</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recipientsByStatus['sent'] as $recipient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($recipient['recipient_email']); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['name'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['company'] ?: '-'); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($recipient['sent_at'])); ?></td>
                                            <td>
                                                <?php if ($recipient['opened_at']): ?>
                                                    <span class="text-primary">
                                                        <i class="bi bi-envelope-open"></i> Opened
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($recipient['clicked_at']): ?>
                                                    <span class="text-success ms-2">
                                                        <i class="bi bi-link-45deg"></i> Clicked
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!$recipient['opened_at'] && !$recipient['clicked_at']): ?>
                                                    <span class="text-muted">Not opened</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Failed Tab -->
                        <div class="tab-pane fade" id="failed">
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> These emails failed to deliver. Common reasons include: invalid domain, mailbox full, or server rejection.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Failed At</th>
                                            <th>Likely Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recipientsByStatus['failed'] as $recipient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($recipient['recipient_email']); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['name'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['company'] ?: '-'); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($recipient['sent_at'])); ?></td>
                                            <td>
                                                <?php
                                                // Simple domain validation
                                                $email = $recipient['recipient_email'];
                                                $domain = substr(strrchr($email, "@"), 1);
                                                if (!checkdnsrr($domain, "MX")) {
                                                    echo '<span class="text-danger">Invalid domain</span>';
                                                } else {
                                                    echo '<span class="text-warning">Delivery rejected</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Invalid Emails Tab -->
                        <div class="tab-pane fade" id="invalid">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-circle"></i> These email addresses have invalid formats and cannot be delivered.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invalid Email</th>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Issue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recipientsByStatus['invalid'] as $recipient): ?>
                                        <tr>
                                            <td class="email-invalid"><?php echo htmlspecialchars($recipient['recipient_email']); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['name'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($recipient['company'] ?: '-'); ?></td>
                                            <td>
                                                <?php
                                                $email = $recipient['recipient_email'];
                                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                                    echo '<span class="text-danger">Invalid email format</span>';
                                                } else {
                                                    echo '<span class="text-warning">Format appears valid but failed validation</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="mt-4 text-center">
                <button class="btn btn-outline-primary" onclick="exportReport('csv')">
                    <i class="bi bi-download"></i> Export as CSV
                </button>
                <button class="btn btn-outline-primary ms-2" onclick="exportReport('pdf')">
                    <i class="bi bi-file-pdf"></i> Export as PDF
                </button>
                <button class="btn btn-outline-primary ms-2" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto-refresh every 30 seconds if campaign is still sending
    <?php if ($campaign['status'] === 'sending'): ?>
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    <?php endif; ?>
    
    // Export functionality
    function exportReport(format) {
        const campaignId = <?php echo $campaignId; ?>;
        window.location.href = `export_campaign_report.php?id=${campaignId}&format=${format}`;
    }
    
    // Print styling
    window.addEventListener('beforeprint', function() {
        document.querySelectorAll('.btn').forEach(btn => btn.style.display = 'none');
        document.querySelector('.main-content').style.marginLeft = '0';
    });
    
    window.addEventListener('afterprint', function() {
        document.querySelectorAll('.btn').forEach(btn => btn.style.display = '');
        document.querySelector('.main-content').style.marginLeft = '';
    });
    </script>
</body>
</html>