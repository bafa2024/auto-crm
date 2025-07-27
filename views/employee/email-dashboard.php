<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . "/../../models/Contact.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: /employee/login");
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
                            <a class="nav-link active" href="/employee/email-dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/campaigns">
                                <i class="fas fa-envelope me-2"></i> My Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/campaigns/create">
                                <i class="fas fa-plus-circle me-2"></i> Create Campaign
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/contacts">
                                <i class="fas fa-address-book me-2"></i> Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/profile">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="/employee/logout">
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
                        <a href="/employee/campaigns/create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Campaign
                        </a>
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
                                            Available Contacts
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Campaigns</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentCampaigns)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No campaigns yet. Create your first campaign!</p>
                                <a href="/employee/campaigns/create" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Campaign
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Campaign Name</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Recipients</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCampaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $campaign['status'] === 'active' ? 'success' : ($campaign['status'] === 'paused' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM email_recipients WHERE campaign_id = ?");
                                                    $stmt->execute([$campaign['id']]);
                                                    echo $stmt->fetch()['count'];
                                                    ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></td>
                                                <td>
                                                    <a href="/employee/campaigns/view/<?php echo $campaign['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="/employee/campaigns/edit/<?php echo $campaign['id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="/employee/campaigns" class="btn btn-primary">View All Campaigns</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <?php if ($permissions['can_create_campaigns']): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                <h5>Create New Campaign</h5>
                                <p class="text-muted">Start a new email campaign to reach your contacts</p>
                                <a href="/employee/campaigns/create" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($permissions['can_upload_contacts']): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-file-upload fa-3x text-success mb-3"></i>
                                <h5>Upload Contacts</h5>
                                <p class="text-muted">Import new contacts from CSV or Excel files</p>
                                <a href="/employee/contacts/upload" class="btn btn-success">Upload Now</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$permissions['can_create_campaigns'] && !$permissions['can_upload_contacts']): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You currently don't have permissions for quick actions. Please contact your administrator.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>