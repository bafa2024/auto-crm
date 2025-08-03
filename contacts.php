<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base path configuration
require_once 'config/base_path.php';
require_once 'version.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
    exit;
}

try {
    require_once 'config/database.php';
    $database = (new Database())->getConnection();
    require_once 'services/EmailUploadService.php';
} catch (Exception $e) {
    die("<div class='alert alert-danger'>System Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Initialize variables
$message = '';
$messageType = '';

// Handle contact deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_contact' && isset($_POST['contact_id'])) {
    try {
        $contactId = $_POST['contact_id'];
        $stmt = $database->prepare("DELETE FROM email_recipients WHERE id = ?");
        $result = $stmt->execute([$contactId]);
        
        if ($result) {
            $message = 'Contact deleted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete contact.';
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = 'Error deleting contact: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle bulk delete
if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['contact_ids'])) {
    try {
        $contactIds = $_POST['contact_ids'];
        if (is_array($contactIds) && !empty($contactIds)) {
            $placeholders = str_repeat('?,', count($contactIds) - 1) . '?';
            $sql = "DELETE FROM email_recipients WHERE id IN ($placeholders)";
            $stmt = $database->prepare($sql);
            $result = $stmt->execute($contactIds);
            
            if ($result) {
                $message = 'Selected contacts deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete selected contacts.';
                $messageType = 'danger';
            }
        }
    } catch (Exception $e) {
        $message = 'Error deleting contacts: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle contact update
if (isset($_POST['action']) && $_POST['action'] === 'update_contact' && isset($_POST['contact_id'])) {
    try {
        $contactId = $_POST['contact_id'];
        $email = trim($_POST['email']);
        $name = trim($_POST['name']);
        $company = trim($_POST['company']);
        $dot = trim($_POST['dot']);
        $campaignId = !empty($_POST['campaign_id']) ? $_POST['campaign_id'] : null;
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            $sql = "UPDATE email_recipients SET email = ?, name = ?, company = ?, dot = ?, campaign_id = ? WHERE id = ?";
            $stmt = $database->prepare($sql);
            $result = $stmt->execute([$email, $name, $company, $dot, $campaignId, $contactId]);
            
            if ($result) {
                $message = 'Contact updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update contact.';
                $messageType = 'danger';
            }
        }
    } catch (Exception $e) {
        $message = 'Error updating contact: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle contact creation
if (isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    try {
        $email = trim($_POST['email']);
        $name = trim($_POST['name']);
        $company = trim($_POST['company']);
        $dot = trim($_POST['dot']);
        $campaignId = !empty($_POST['campaign_id']) ? $_POST['campaign_id'] : null;
        
        // Validate required fields
        if (empty($email) || empty($name)) {
            $message = 'Email and name are required.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            // Check if email already exists
            $stmt = $database->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
            $stmt->execute([strtolower($email)]);
            
            if ($stmt->fetch()) {
                $message = 'A contact with this email already exists.';
                $messageType = 'danger';
            } else {
                // Insert new contact
                $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $database->prepare($sql);
                $result = $stmt->execute([$email, $name, $company, $dot, $campaignId]);
                
                if ($result) {
                    $message = 'Contact created successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create contact.';
                    $messageType = 'danger';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Error creating contact: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle file upload (without campaign requirement)
if (isset($_FILES['email_file'])) {
    // Debug: Log the upload attempt
    error_log("=== UPLOAD DEBUG START ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    try {
        // Check which service to use
        $vendorPath = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($vendorPath)) {
            // Use simple service that doesn't require PhpSpreadsheet
            require_once 'services/SimpleEmailUploadService.php';
            $uploadService = new SimpleEmailUploadService($database);
            error_log("Using SimpleEmailUploadService");
        } else {
            require_once 'services/EmailUploadService.php';
            $uploadService = new EmailUploadService($database);
            error_log("Using EmailUploadService");
        }
        
        $file = $_FILES['email_file'];
        $campaignId = $_POST['campaign_id'] ?? null; // Optional campaign
        
        // Debug upload info
        error_log("Upload attempt - File: " . $file['name'] . ", Size: " . $file['size'] . ", Error: " . $file['error']);
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
            $message = 'File upload failed: ' . $errorMsg;
            $messageType = 'danger';
            error_log("Upload failed: " . $errorMsg);
        } else {
            // Check file extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $message = 'Invalid file type. Please upload CSV or Excel file.';
                $messageType = 'danger';
                error_log("Invalid file type: " . $extension);
            } else {
                // Check if file is actually uploaded and readable
                if (!is_uploaded_file($file['tmp_name'])) {
                    $message = 'File upload security check failed.';
                    $messageType = 'danger';
                    error_log("Security check failed for file: " . $file['tmp_name']);
                } else {
                    error_log("Processing file: " . $file['tmp_name']);
                    // Process the file
                    $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
                    error_log("Processing result: " . print_r($result, true));
                    
                    if ($result['success']) {
                        $message = "Upload successful! Imported: {$result['imported']} contacts";
                        if ($result['skipped'] > 0) {
                            $message .= ", Skipped: {$result['skipped']} (already imported)";
                        }
                        if (isset($result['failed']) && $result['failed'] > 0) {
                            $message .= ", Failed: {$result['failed']}";
                        }
                        $messageType = 'success';
                        error_log("Upload successful: " . $message);
                    } else {
                        $message = 'Upload failed: ' . $result['message'];
                        $messageType = 'danger';
                        error_log("Upload failed: " . $result['message']);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Upload exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $message = 'Upload error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
    
    error_log("=== UPLOAD DEBUG END ===");
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$filter_campaign = $_GET['filter_campaign'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get existing campaigns for dropdown (optional)
$campaigns = [];
try {
    $stmt = $database->query("SELECT id, name FROM email_campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}

// Build query with search and filter
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(email LIKE ? OR name LIKE ? OR company LIKE ? OR dot LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($filter_campaign)) {
    if ($filter_campaign === 'no_campaign') {
        $where_conditions[] = "campaign_id IS NULL";
    } else {
        $where_conditions[] = "campaign_id = ?";
        $params[] = $filter_campaign;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM email_recipients er LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id $where_clause";
$count_stmt = $database->prepare($count_sql);
$count_stmt->execute($params);
$total_contacts = $count_stmt->fetchColumn();
$total_pages = ceil($total_contacts / $per_page);

// Get contacts with search, filter, and pagination
$sql = "SELECT 
            er.id,
            er.email,
            er.name,
            er.company,
            er.dot,
            er.created_at,
            ec.name as campaign_name
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        $where_clause
        ORDER BY er.$sort_by $sort_order
        LIMIT $per_page OFFSET $offset";

$stmt = $database->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent uploads
$recentUploads = [];
try {
    $stmt = $database->query("
        SELECT 
            er.campaign_id,
            ec.name as campaign_name,
            COUNT(er.id) as recipient_count,
            MIN(er.created_at) as upload_date
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        GROUP BY er.campaign_id, ec.name
        ORDER BY upload_date DESC
        LIMIT 5
    ");
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/sidebar-fix.css">
    <style>
        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: #0d6efd;
            background: #e7f3ff;
        }
        
        .contact-actions {
            white-space: nowrap;
        }
        
        .search-filters {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .bulk-actions {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .bulk-actions.show {
            display: block;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Header fixes */
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Ensure content doesn't overlap with fixed header */
        .content-wrapper {
            margin-top: 70px;
        }
        
        /* Mobile responsive fixes */
        @media (max-width: 768px) {
            .navbar {
                margin-left: 0 !important;
                width: 100vw !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .content-wrapper {
                margin-top: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'views/components/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Full Width Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary" style="margin-left: 260px; width: calc(100vw - 260px); position: fixed; top: 0; z-index: 1030;">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <a class="navbar-brand fw-bold" href="/dashboard">
                        <i class="bi bi-telephone-fill"></i>
                    </a>
                    
                    <!-- Version Badge -->
                    <?php 
                    // Don't show version badge on landing page
                    $currentUri = $_SERVER["REQUEST_URI"] ?? "/";
                    if ($currentUri !== "/" && $currentUri !== "/index.php") {
                        echo VersionManager::getVersionBadge(); 
                    }
                    ?>
                </div>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> Account
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/profile">
                                    <i class="bi bi-person"></i> Profile
                                </a></li>
                                <li><a class="dropdown-item" href="/settings">
                                    <i class="bi bi-gear"></i> Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Content with top margin for header -->
        <div style="margin-top: 70px;">
            <div class="container-fluid py-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="mb-4">
                            <i class="bi bi-people"></i> Contact Management
                        </h1>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Search and Filters -->
                        <div class="search-filters">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search Contacts</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by email, name, company, or DOT...">
                                </div>
                                <div class="col-md-3">
                                    <label for="filter_campaign" class="form-label">Filter by Campaign</label>
                                    <select class="form-select" id="filter_campaign" name="filter_campaign">
                                        <option value="">All Campaigns</option>
                                        <option value="no_campaign" <?php echo $filter_campaign === 'no_campaign' ? 'selected' : ''; ?>>No Campaign</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo $campaign['id']; ?>" <?php echo $filter_campaign == $campaign['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($campaign['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sort_by" class="form-label">Sort By</label>
                                    <select class="form-select" id="sort_by" name="sort_by">
                                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                        <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                                        <option value="company" <?php echo $sort_by === 'company' ? 'selected' : ''; ?>>Company</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sort_order" class="form-label">Order</label>
                                    <select class="form-select" id="sort_order" name="sort_order">
                                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <div class="bulk-actions" id="bulkActions">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <span id="selectedCount">0 contacts selected</span>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                                        <i class="bi bi-x-circle"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Management Cards -->
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Contacts List -->
                                <div class="feature-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5><i class="bi bi-list-ul"></i> Contacts (<?php echo $total_contacts; ?>)</h5>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createContactModal">
                                            <i class="bi bi-plus-circle"></i> Add Contact
                                        </button>
                                    </div>
                                    
                                    <?php if (!empty($contacts)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>
                                                            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                                        </th>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Company</th>
                                                        <th>DOT</th>
                                                        <th>Campaign</th>
                                                        <th>Added</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($contacts as $contact): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" class="form-check-input contact-checkbox" 
                                                                   value="<?php echo $contact['id']; ?>" onchange="updateSelection()">
                                                        </td>
                                                        <td><strong><?php echo htmlspecialchars($contact['name']); ?></strong></td>
                                                        <td>
                                                            <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>">
                                                                <?php echo htmlspecialchars($contact['email']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($contact['company'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($contact['dot'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php if ($contact['campaign_name']): ?>
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($contact['campaign_name']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo date('M d, Y', strtotime($contact['created_at'])); ?>
                                                            </small>
                                                        </td>
                                                        <td class="contact-actions">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editContact(<?php echo htmlspecialchars(json_encode($contact)); ?>)">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteContact(<?php echo $contact['id']; ?>, '<?php echo htmlspecialchars($contact['name']); ?>')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                            <nav aria-label="Contacts pagination">
                                                <ul class="pagination justify-content-center">
                                                    <?php if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="bi bi-inbox display-1 text-muted"></i>
                                            <h4 class="mt-3 text-muted">No contacts found</h4>
                                            <p class="text-muted">Try adjusting your search criteria or create your first contact.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Quick Actions -->
                                <div class="feature-card">
                                    <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContactModal">
                                            <i class="bi bi-person-plus"></i> Add Single Contact
                                        </button>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                            <i class="bi bi-cloud-arrow-up"></i> Bulk Upload
                                        </button>
                                        <a href="sample_contacts.csv" download class="btn btn-outline-secondary">
                                            <i class="bi bi-download"></i> Download Template
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Recent Uploads -->
                                <div class="feature-card">
                                    <h5><i class="bi bi-clock-history"></i> Recent Uploads</h5>
                                    <?php if (!empty($recentUploads)): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recentUploads as $upload): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($upload['campaign_name'] ?? 'No Campaign'); ?></strong>
                                                    <br><small class="text-muted"><?php echo date('M d, Y', strtotime($upload['upload_date'])); ?></small>
                                                </div>
                                                <span class="badge bg-primary rounded-pill"><?php echo $upload['recipient_count']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No recent uploads found.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Contact Modal -->
    <div class="modal fade" id="createContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_contact">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dot" class="form-label">DOT Number</label>
                                    <input type="text" class="form-control" id="dot" name="dot" placeholder="e.g., 170481">
                                    <div class="form-text">Optional DOT number</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="campaign_id" class="form-label">Campaign (Optional)</label>
                                    <select class="form-select" id="campaign_id" name="campaign_id">
                                        <option value="">-- No Campaign --</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo $campaign['id']; ?>">
                                                <?php echo htmlspecialchars($campaign['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Optional campaign assignment</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="company" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company" name="company" placeholder="e.g., BELL TRUCKING CO INC">
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="e.g., JUDY BELL" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="e.g., judy@example.com" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Contact Modal -->
    <div class="modal fade" id="editContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_contact">
                        <input type="hidden" name="contact_id" id="edit_contact_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_dot" class="form-label">DOT Number</label>
                                    <input type="text" class="form-control" id="edit_dot" name="dot" placeholder="e.g., 170481">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_campaign_id" class="form-label">Campaign</label>
                                    <select class="form-select" id="edit_campaign_id" name="campaign_id">
                                        <option value="">-- No Campaign --</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo $campaign['id']; ?>">
                                                <?php echo htmlspecialchars($campaign['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_company" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="edit_company" name="company" placeholder="e.g., BELL TRUCKING CO INC">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" placeholder="e.g., JUDY BELL" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" placeholder="e.g., judy@example.com" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Upload Contacts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="modal_campaign_id" class="form-label">Campaign (Optional)</label>
                                <select class="form-select" id="modal_campaign_id" name="campaign_id">
                                    <option value="">-- No Campaign --</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>">
                                            <?php echo htmlspecialchars($campaign['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select a campaign to associate these contacts with (optional)</div>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="modal_email_file" class="form-label">Email List File</label>
                                <div class="upload-area" id="modalUploadArea">
                                    <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                                    <p class="mt-3">Drag and drop your file here or click to browse</p>
                                    <input type="file" class="form-control d-none" id="modal_email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                                    <button type="button" class="btn btn-outline-primary mt-2" onclick="document.getElementById('modal_email_file').click()">
                                        <i class="bi bi-folder2-open"></i> Choose File
                                    </button>
                                    <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls). Max size: 10MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>File Format Requirements:</h6>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>DOT</th>
                                        <th>Company Name</th>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>170481</td>
                                        <td>BELL TRUCKING CO INC</td>
                                        <td>JUDY BELL</td>
                                        <td>judy@example.com</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-cloud-arrow-up"></i> Upload Contacts
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Bulk Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <span id="deleteCount">0</span> selected contacts?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="bulk_delete">
                        <input type="hidden" name="contact_ids" id="bulkDeleteIds">
                        <button type="submit" class="btn btn-danger">Delete Selected</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Contact management functions
        function editContact(contact) {
            document.getElementById('edit_contact_id').value = contact.id;
            document.getElementById('edit_name').value = contact.name;
            document.getElementById('edit_email').value = contact.email;
            document.getElementById('edit_company').value = contact.company || '';
            document.getElementById('edit_dot').value = contact.dot || '';
            document.getElementById('edit_campaign_id').value = contact.campaign_id || '';
            
            new bootstrap.Modal(document.getElementById('editContactModal')).show();
        }
        
        function deleteContact(contactId, contactName) {
            if (confirm(`Are you sure you want to delete "${contactName}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_contact">
                    <input type="hidden" name="contact_id" value="${contactId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Bulk selection functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.contact-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateSelection();
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
            const selectedCount = checkboxes.length;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            selectedCountSpan.textContent = `${selectedCount} contact${selectedCount !== 1 ? 's' : ''} selected`;
            
            if (selectedCount > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.contact-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelection();
        }
        
        function bulkDelete() {
            const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
            const contactIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (contactIds.length === 0) {
                alert('Please select contacts to delete.');
                return;
            }
            
            document.getElementById('deleteCount').textContent = contactIds.length;
            document.getElementById('bulkDeleteIds').value = JSON.stringify(contactIds);
            new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
        }
        
        // File upload handling for modal
        const modalUploadArea = document.getElementById('modalUploadArea');
        const modalFileInput = document.getElementById('modal_email_file');
        
        modalUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            modalUploadArea.classList.add('dragover');
        });
        
        modalUploadArea.addEventListener('dragleave', () => {
            modalUploadArea.classList.remove('dragover');
        });
        
        modalUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            modalUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                modalFileInput.files = files;
                updateModalFileDisplay();
            }
        });
        
        modalFileInput.addEventListener('change', updateModalFileDisplay);
        
        function updateModalFileDisplay() {
            if (modalFileInput.files.length > 0) {
                const file = modalFileInput.files[0];
                modalUploadArea.innerHTML = `
                    <i class="bi bi-file-earmark-text display-4 text-success"></i>
                    <p class="mt-3"><strong>${file.name}</strong></p>
                    <p class="text-muted">Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="changeModalFile()">
                        <i class="bi bi-arrow-repeat"></i> Change File
                    </button>
                `;
            }
        }
        
        function changeModalFile() {
            modalFileInput.value = '';
            modalUploadArea.innerHTML = `
                <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                <p class="mt-3">Drag and drop your file here or click to browse</p>
                <button type="button" class="btn btn-outline-primary mt-2" onclick="modalFileInput.click()">
                    <i class="bi bi-folder2-open"></i> Choose File
                </button>
                <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls). Max size: 10MB</div>
            `;
        }
    </script>
</body>
</html> 