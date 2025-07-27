<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: /employee/login");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$campaignModel = new EmailCampaign($db);
$userId = $_SESSION["user_id"];

// Get user permissions
require_once __DIR__ . "/../../models/EmployeePermission.php";
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($userId);

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT * FROM email_campaigns WHERE created_by = ?";
$params = [$userId];

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (name LIKE ? OR subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count
$countQuery = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalCampaigns = $stmt->fetch()['total'];
$totalPages = ceil($totalCampaigns / $limit);

// Get campaigns with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$campaigns = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Campaigns - Email Campaign Management</title>
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
        .campaign-actions .btn {
            margin: 0 2px;
        }
        .status-badge {
            font-size: 0.875rem;
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
                            <a class="nav-link" href="/employee/email-dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/employee/campaigns">
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
                    <h1 class="h2">My Email Campaigns</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($permissions['can_create_campaigns']): ?>
                        <a href="/employee/campaigns/create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Campaign
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search campaigns...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="/employee/campaigns" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Campaigns Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Campaigns (<?php echo $totalCampaigns; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($campaigns)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No campaigns found</h5>
                                <p class="text-muted">Create your first campaign to get started!</p>
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
                                            <th>Type</th>
                                            <th>Recipients</th>
                                            <th>Sent</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                                <td>
                                                    <span class="badge status-badge bg-<?php 
                                                        echo $campaign['status'] === 'active' ? 'success' : 
                                                            ($campaign['status'] === 'paused' ? 'warning' : 
                                                            ($campaign['status'] === 'completed' ? 'info' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $campaign['send_type'] === 'immediate' ? 'danger' : 'primary'; ?>">
                                                        <?php echo ucfirst($campaign['send_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stmt = $db->prepare("SELECT COUNT(*) as total, 
                                                                         SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent 
                                                                         FROM email_recipients WHERE campaign_id = ?");
                                                    $stmt->execute([$campaign['id']]);
                                                    $stats = $stmt->fetch();
                                                    echo $stats['total'];
                                                    ?>
                                                </td>
                                                <td><?php echo $stats['sent'] ?? 0; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></td>
                                                <td class="campaign-actions">
                                                    <a href="/employee/campaigns/view/<?php echo $campaign['id']; ?>" 
                                                       class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($campaign['status'] === 'draft'): ?>
                                                        <a href="/employee/campaigns/edit/<?php echo $campaign['id']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="activateCampaign(<?php echo $campaign['id']; ?>)" 
                                                                title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php elseif ($campaign['status'] === 'active'): ?>
                                                        <button class="btn btn-sm btn-warning" 
                                                                onclick="pauseCampaign(<?php echo $campaign['id']; ?>)" 
                                                                title="Pause">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    <?php elseif ($campaign['status'] === 'paused'): ?>
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="resumeCampaign(<?php echo $campaign['id']; ?>)" 
                                                                title="Resume">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-secondary" 
                                                            onclick="duplicateCampaign(<?php echo $campaign['id']; ?>)" 
                                                            title="Duplicate">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <?php if (in_array($campaign['status'], ['draft', 'paused'])): ?>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="deleteCampaign(<?php echo $campaign['id']; ?>)" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-detect base path
        const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
        
        async function updateCampaignStatus(campaignId, status) {
            try {
                const response = await fetch(`${basePath}/api/campaigns/${campaignId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: status })
                });
                
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to update campaign status');
                }
            } catch (error) {
                alert('Network error. Please try again.');
            }
        }
        
        function activateCampaign(id) {
            if (confirm('Are you sure you want to activate this campaign?')) {
                updateCampaignStatus(id, 'active');
            }
        }
        
        function pauseCampaign(id) {
            if (confirm('Are you sure you want to pause this campaign?')) {
                updateCampaignStatus(id, 'paused');
            }
        }
        
        function resumeCampaign(id) {
            if (confirm('Are you sure you want to resume this campaign?')) {
                updateCampaignStatus(id, 'active');
            }
        }
        
        async function duplicateCampaign(id) {
            if (confirm('Are you sure you want to duplicate this campaign?')) {
                try {
                    const response = await fetch(`${basePath}/api/campaigns/${id}/duplicate`, {
                        method: 'POST'
                    });
                    
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Failed to duplicate campaign');
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                }
            }
        }
        
        async function deleteCampaign(id) {
            if (confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
                try {
                    const response = await fetch(`${basePath}/api/campaigns/${id}`, {
                        method: 'DELETE'
                    });
                    
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Failed to delete campaign');
                    }
                } catch (error) {
                    alert('Network error. Please try again.');
                }
            }
        }
    </script>
</body>
</html>