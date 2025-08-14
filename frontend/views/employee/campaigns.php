<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . '/../../config/base_path.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: " . base_path() . "/employee/login");
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

// Helper function to check permissions
if (!function_exists('hasPermission')) {
    function hasPermission($permissions, $permission) {
        return isset($permissions[$permission]) && $permissions[$permission];
    }
}

// Check if user has any campaign-related permissions
if (!hasPermission($permissions, 'can_create_campaigns') && !hasPermission($permissions, 'can_view_all_campaigns')) {
    header("Location: " . base_path('employee/email-dashboard'));
    exit();
}

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
            <?php include __DIR__ . "/../components/employee-sidebar.php"; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" style="margin-left: 260px;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Email Campaigns</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
                        <a href="<?php echo base_path('employee/campaigns/create'); ?>" class="btn btn-primary">
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
                                <a href="<?php echo base_path('employee/campaigns'); ?>" class="btn btn-secondary">
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
                                <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
                                <a href="<?php echo base_path('employee/campaigns/create'); ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Campaign
                                </a>
                                <?php endif; ?>
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
                                                <td><?php echo number_format($campaign['sent_count'] ?? 0); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></td>
                                                <td class="campaign-actions">
                                                    <a href="<?php echo base_path('employee/campaigns/view/' . $campaign['id']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (hasPermission($permissions, 'can_edit_campaigns')): ?>
                                                    <a href="<?php echo base_path('employee/campaigns/edit/' . $campaign['id']); ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission($permissions, 'can_send_campaigns') && $campaign['status'] === 'draft'): ?>
                                                    <button onclick="sendCampaign(<?php echo $campaign['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-success" title="Send">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission($permissions, 'can_delete_campaigns')): ?>
                                                    <button onclick="deleteCampaign(<?php echo $campaign['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-danger" title="Delete">
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
                            <nav aria-label="Campaigns pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
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
        function sendCampaign(campaignId) {
            if (confirm('Are you sure you want to send this campaign?')) {
                fetch(`<?php echo base_path('api/campaigns'); ?>/${campaignId}/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Campaign sent successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to send campaign');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to send campaign');
                });
            }
        }

        function deleteCampaign(campaignId) {
            if (confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
                fetch(`<?php echo base_path('api/campaigns'); ?>/${campaignId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Campaign deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete campaign');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete campaign');
                });
            }
        }
    </script>
</body>
</html>