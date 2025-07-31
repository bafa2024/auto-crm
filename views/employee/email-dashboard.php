<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . "/../../models/Contact.php";
require_once __DIR__ . "/../../config/base_path.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: " . base_path() . "/employee/login");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$campaignModel = new EmailCampaign($db);
$contactModel = new Contact($db);

// Get user permissions
require_once __DIR__ . "/../../models/EmployeePermission.php";
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Get statistics for the employee
$userId = $_SESSION["user_id"];

// Get total campaigns created by this employee
$stmt = $db->prepare("SELECT COUNT(*) as total FROM email_campaigns WHERE created_by = ?");
$stmt->execute([$userId]);
$totalCampaigns = $stmt->fetch()['total'];

// Get active campaigns
$stmt = $db->prepare("SELECT COUNT(*) as active FROM email_campaigns WHERE created_by = ? AND status = 'active'");
$stmt->execute([$userId]);
$activeCampaigns = $stmt->fetch()['active'];

// Get total emails sent by this employee's campaigns
$stmt = $db->prepare("
    SELECT COUNT(*) as sent 
    FROM email_recipients er 
    JOIN email_campaigns ec ON er.campaign_id = ec.id 
    WHERE ec.created_by = ? AND er.status = 'sent'
");
$stmt->execute([$userId]);
$totalEmailsSent = $stmt->fetch()['sent'];

// Get recent campaigns
$stmt = $db->prepare("
    SELECT * FROM email_campaigns 
    WHERE created_by = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentCampaigns = $stmt->fetchAll();

// Get total contacts available
$stmt = $db->query("SELECT COUNT(*) as total FROM contacts");
$totalContacts = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaign Dashboard - <?php echo htmlspecialchars($_SESSION["user_name"]); ?></title>
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
                            <a class="nav-link active" href="<?php echo base_path(); ?>/employee/email-dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path(); ?>/employee/campaigns">
                                <i class="fas fa-envelope me-2"></i> My Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path(); ?>/employee/campaigns/create">
                                <i class="fas fa-plus-circle me-2"></i> Create Campaign
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path(); ?>/employee/contacts">
                                <i class="fas fa-address-book me-2"></i> Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_path(); ?>/employee/profile">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="<?php echo base_path(); ?>/employee/logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Email Campaign Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($permissions['can_create_campaigns']): ?>
                        <a href="<?php echo base_path(); ?>/employee/campaigns/create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Campaign
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card primary h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                            Total Campaigns
                                        </div>
                                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $totalCampaigns; ?></div>
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
                                            Active Campaigns
                                        </div>
                                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $activeCampaigns; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-rocket fa-2x text-gray-300"></i>
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
                                            Emails Sent
                                        </div>
                                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $totalEmailsSent; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-paper-plane fa-2x text-gray-300"></i>
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
                                            Total Contacts
                                        </div>
                                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $totalContacts; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Campaigns -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent Campaigns
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentCampaigns)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>No campaigns created yet.</p>
                                        <a href="<?php echo base_path(); ?>/employee/campaigns/create" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Create Your First Campaign
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Campaign Name</th>
                                                    <th>Status</th>
                                                    <th>Recipients</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentCampaigns as $campaign): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = '';
                                                            switch ($campaign['status']) {
                                                                case 'active':
                                                                    $statusClass = 'badge bg-success';
                                                                    break;
                                                                case 'paused':
                                                                    $statusClass = 'badge bg-warning';
                                                                    break;
                                                                case 'completed':
                                                                    $statusClass = 'badge bg-info';
                                                                    break;
                                                                default:
                                                                    $statusClass = 'badge bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $statusClass; ?>">
                                                                <?php echo ucfirst($campaign['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo number_format($campaign['recipient_count'] ?? 0); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></td>
                                                        <td>
                                                            <a href="<?php echo base_path(); ?>/employee/campaigns/view/<?php echo $campaign['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="<?php echo base_path(); ?>/employee/campaigns/edit/<?php echo $campaign['id']; ?>" 
                                                               class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-edit"></i>
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>