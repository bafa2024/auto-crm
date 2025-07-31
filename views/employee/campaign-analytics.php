<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/base_path.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get overall statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_campaigns,
        COUNT(DISTINCT CASE WHEN c.status = 'completed' THEN c.id END) as completed_campaigns,
        SUM(c.total_recipients) as total_recipients,
        SUM(c.sent_count) as total_sent,
        SUM(c.opened_count) as total_opened,
        SUM(c.clicked_count) as total_clicked,
        SUM(c.bounced_count) as total_bounced,
        COUNT(DISTINCT CASE WHEN r.unsubscribed_at IS NOT NULL THEN r.id END) as total_unsubscribed
    FROM email_campaigns c
    LEFT JOIN email_recipients r ON c.id = r.campaign_id
    WHERE c.created_by = ? 
    AND DATE(c.created_at) BETWEEN ? AND ?
");
$stmt->execute([$_SESSION["user_id"], $startDate, $endDate]);
$overallStats = $stmt->fetch();

// Calculate overall rates
$overallStats['avg_open_rate'] = $overallStats['total_sent'] > 0 
    ? round(($overallStats['total_opened'] / $overallStats['total_sent']) * 100, 2) 
    : 0;
    
$overallStats['avg_click_rate'] = $overallStats['total_sent'] > 0 
    ? round(($overallStats['total_clicked'] / $overallStats['total_sent']) * 100, 2) 
    : 0;

// Get daily statistics for chart
$stmt = $db->prepare("
    SELECT 
        DATE(c.created_at) as date,
        COUNT(DISTINCT c.id) as campaigns,
        SUM(c.sent_count) as sent,
        SUM(c.opened_count) as opened,
        SUM(c.clicked_count) as clicked
    FROM email_campaigns c
    WHERE c.created_by = ? 
    AND DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY DATE(c.created_at)
    ORDER BY date
");
$stmt->execute([$_SESSION["user_id"], $startDate, $endDate]);
$dailyStats = $stmt->fetchAll();

// Get top performing campaigns
$stmt = $db->prepare("
    SELECT 
        c.*,
        (c.opened_count / NULLIF(c.sent_count, 0)) * 100 as open_rate,
        (c.clicked_count / NULLIF(c.sent_count, 0)) * 100 as click_rate
    FROM email_campaigns c
    WHERE c.created_by = ? 
    AND c.sent_count > 0
    AND DATE(c.created_at) BETWEEN ? AND ?
    ORDER BY open_rate DESC
    LIMIT 5
");
$stmt->execute([$_SESSION["user_id"], $startDate, $endDate]);
$topCampaigns = $stmt->fetchAll();

// Get engagement by hour
$stmt = $db->prepare("
    SELECT 
        HOUR(r.opened_at) as hour,
        COUNT(*) as opens
    FROM email_recipients r
    JOIN email_campaigns c ON r.campaign_id = c.id
    WHERE c.created_by = ? 
    AND r.opened_at IS NOT NULL
    AND DATE(r.opened_at) BETWEEN ? AND ?
    GROUP BY HOUR(r.opened_at)
    ORDER BY hour
");
$stmt->execute([$_SESSION["user_id"], $startDate, $endDate]);
$hourlyEngagement = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Analytics - Email Campaign Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            position: relative;
            height: 300px;
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
                            <a class="nav-link" href="<?php echo base_path('employee/campaigns'); ?>">
                                <i class="fas fa-envelope me-2"></i> My Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path('employee/campaigns/create'); ?>">
                                <i class="fas fa-plus-circle me-2"></i> Create Campaign
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?php echo base_path('employee/campaigns/analytics'); ?>">
                                <i class="fas fa-chart-line me-2"></i> Analytics
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
                    <h1 class="h2">Campaign Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form class="d-flex gap-2" method="GET">
                            <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                            <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card primary h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                            Total Campaigns
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($overallStats['total_campaigns']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-envelope fa-2x text-gray-300"></i>
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
                                            Emails Sent
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo number_format($overallStats['total_sent']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-paper-plane fa-2x text-gray-300"></i>
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
                                            Avg Open Rate
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo $overallStats['avg_open_rate']; ?>%</div>
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
                                            Avg Click Rate
                                        </div>
                                        <div class="h5 mb-0 fw-bold"><?php echo $overallStats['avg_click_rate']; ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-mouse-pointer fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Campaign Performance Over Time</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Best Time to Send</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Campaigns -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Performing Campaigns</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topCampaigns)): ?>
                            <p class="text-muted text-center">No campaign data available for the selected period.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Campaign Name</th>
                                            <th>Sent</th>
                                            <th>Open Rate</th>
                                            <th>Click Rate</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topCampaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td><?php echo number_format($campaign['sent_count']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $campaign['open_rate']; ?>%">
                                                            <?php echo round($campaign['open_rate'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-warning" style="width: <?php echo $campaign['click_rate']; ?>%">
                                                            <?php echo round($campaign['click_rate'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></td>
                                                <td>
                                                    <a href="<?php echo base_path('employee/campaigns/view/' . $campaign['id']); ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
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
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceData = <?php echo json_encode($dailyStats); ?>;
        
        new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: performanceData.map(d => d.date),
                datasets: [{
                    label: 'Sent',
                    data: performanceData.map(d => d.sent),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Opened',
                    data: performanceData.map(d => d.opened),
                    borderColor: 'rgb(54, 162, 235)',
                    tension: 0.1
                }, {
                    label: 'Clicked',
                    data: performanceData.map(d => d.clicked),
                    borderColor: 'rgb(255, 205, 86)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Hourly Engagement Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyData = <?php echo json_encode($hourlyEngagement); ?>;
        
        // Fill in missing hours
        const hoursData = Array(24).fill(0);
        hourlyData.forEach(h => {
            hoursData[h.hour] = h.opens;
        });
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Email Opens',
                    data: hoursData,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>