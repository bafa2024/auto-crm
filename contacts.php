<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'version.php';

// Start session
    session_start();

// Simple session check without database access
// Temporarily disabled for testing
// if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
//     header('Location: views/auth/login.php');
//     exit;
// }

// Set session timeout (optional - 2 hours)
$session_timeout = 7200; // 2 hours in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_destroy();
    header('Location: views/auth/login.php?error=session_expired');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get real statistics from MySQL database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Total contacts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM email_recipients");
    $totalContacts = $stmt->fetch()['total'];
    
    // Active contacts (all contacts are considered active since we don't have status column)
    $activeContacts = $totalContacts;
    
    // New contacts this month - MySQL date functions
    $stmt = $pdo->query("SELECT COUNT(*) as new_this_month FROM email_recipients WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newThisMonth = $stmt->fetch()['new_this_month'];
    
    // Deleted contacts (since there's no deleted_at column, we'll show 0 for now)
    $deletedContacts = 0;
    
} catch (PDOException $e) {
    // Fallback to default values if database error
    $totalContacts = 0;
    $activeContacts = 0;
    $newThisMonth = 0;
    $deletedContacts = 0;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Management - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <style>
        /* Loading overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .contact-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .filter-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
        }
        .search-box {
            position: relative;
        }
        .search-box .form-control {
            padding-left: 2.5rem;
        }
        .search-box .bi {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .action-buttons .btn {
            margin-right: 0.25rem;
        }
        .contact-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Alert styling for success/error messages */
        .alert.position-fixed {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            border-radius: 8px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .pagination-info {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .filter-active {
            background-color: #e7f3ff !important;
            border-color: #0066cc !important;
        }
        .active-filters-badge {
            background-color: #0066cc;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
    </style>
</head>
<body>

<!-- Include Sidebar -->
    <?php include 'views/components/sidebar.php'; ?>

<!-- Include Header -->
<?php include 'views/components/header.php'; ?>

<!-- Main Content Area -->
<div class="main-content" style="margin-left: 260px; padding: 20px;">
        <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                <div>
                        <h1 class="h3 mb-0">
                            <i class="bi bi-people me-2"></i>
                            Contacts Management
                        </h1>
                        <p class="text-muted mb-0">Manage your contact database with ease</p>
                </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add Contact
                        </button>
                        <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bi bi-upload me-2"></i>
                            Import
                    </button>
                    </div>
                </div>
                </div>
            </div>
                
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                                            <div>
                                <h4 class="mb-0" id="totalContacts"><?php echo number_format($totalContacts); ?></h4>
                                <small>Total Contacts</small>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo number_format($activeContacts); ?></h4>
                                <small>Active Contacts</small>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo number_format($newThisMonth); ?></h4>
                                <small>New This Month</small>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-plus-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo number_format($deletedContacts); ?></h4>
                                <small>Deleted Contacts</small>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-trash fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                    </div>
                    
        <!-- Search and Filter Section -->
        <div class="row mb-4">
                <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                            <div class="row">
                            <!-- Search Box -->
                            <div class="col-md-4 mb-3">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search contacts...">
                                    <div class="form-text" id="searchHelp" style="display: none;">
                                        <small>Press Enter to search or wait for auto-search</small>
                                    </div>
                                </div>
                    </div>
                    
                            <!-- Filter Options -->
                                <div class="col-md-8">
                            <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <select class="form-select" id="statusFilter">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="pending">Pending</option>
                                    </select>
                                </div>
                                    <div class="col-md-3 mb-3">
                                        <select class="form-select" id="sortBy">
                                            <option value="created_at">Sort by Date (Newest)</option>
                                            <option value="name">Sort by Name (A-Z)</option>
                                            <option value="email">Sort by Email (A-Z)</option>
                                            <option value="company">Sort by Company (A-Z)</option>
                                            <option value="dot">Sort by DOT Number</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                            <i class="bi bi-x-circle me-2"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                                    </div>
                                </div>
                            </div>
                            
        <!-- Contacts Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contact List</h5>
                        <div class="d-flex align-items-center">
                            <span class="pagination-info me-3">Showing 1-10 of 1,234 contacts</span>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportContacts()">
                                    <i class="bi bi-download me-1"></i>Export
                            </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="bulkDelete()">
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                    </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th>Contact</th>
                                    <th>Email</th>
                                        <th>Company</th>
                                        <th>DOT</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th width="150">Actions</th>
                                </tr>
                            </thead>
                                <tbody id="contactsTableBody">
                                    <!-- Loading row -->
                                    <tr id="loadingRow" style="display: none;">
                                        <td colspan="8" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                    </div>
                                            <div class="mt-2">Loading contacts...</div>
                                        </td>
                                    </tr>
                                    <!-- Dynamic content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <select class="form-select form-select-sm" id="perPageSelect" style="width: auto;" onchange="changePerPage(this.value)">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50" selected>50 per page</option>
                                    <option value="100">100 per page</option>
                                </select>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="pagination">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Contact
                </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <form id="addContactForm">
                    <div class="modal-body">
                        <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company" name="company">
                        </div>
                        <div class="col-md-6 mb-3">
                                    <label for="dot" class="form-label">DOT Number</label>
                            <input type="text" class="form-control" id="dot" name="dot">
                                </div>
                            </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Only name and email are required fields. Company and DOT number are optional.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>
                        Save Contact
                    </button>
                </div>
            </form>
                                </div>
                            </div>
                        </div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Contact
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editContactForm">
                <input type="hidden" id="editContactId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEmail" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editCompany" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="editCompany" name="company">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editDot" class="form-label">DOT Number</label>
                            <input type="text" class="form-control" id="editDot" name="dot">
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Only name and email are required fields. Company and DOT number are optional.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>
                        Update Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
                        
<!-- View Contact Modal -->
<div class="modal fade" id="viewContactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>
                    View Contact Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewName">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewEmail">-</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Company Name</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewCompany">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">DOT Number</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewDot">-</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Contact ID</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewContactId">-</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date Added</label>
                        <div class="form-control-plaintext border bg-light rounded p-2" id="viewDateAdded">-</div>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Contact Information:</strong> This is a read-only view of the contact details.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upload me-2"></i>
                    Import Contacts
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Step 1: File Upload -->
                    <div id="step1" class="import-step">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Supported Formats:</strong> CSV, XLSX, XLS files with headers: Name, Email, Company, DOT
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                        <div class="mb-3">
                                    <label for="importFile" class="form-label">Select File</label>
                                    <input type="file" class="form-control" id="importFile" name="importFile" accept=".csv,.xlsx,.xls" required>
                                    <div class="form-text">Maximum file size: 10MB</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Download Template</label>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="downloadTemplate('csv')">
                                            <i class="bi bi-download me-2"></i>CSV Template
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="downloadTemplate('xlsx')">
                                            <i class="bi bi-download me-2"></i>Excel Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skipHeader" checked>
                                <label class="form-check-label" for="skipHeader">
                                    Skip first row (header row)
                                </label>
                            </div>
                        </div>
                        </div>
                        
                    <!-- Step 2: Preview Data -->
                    <div id="step2" class="import-step" style="display: none;">
                        <h6 class="mb-3">Preview Import Data</h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="previewTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Company</th>
                                        <th>DOT</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="previewTableBody">
                                    <!-- Preview data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Total Records:</strong> <span id="totalRecords">0</span> | 
                                <strong>Valid Records:</strong> <span id="validRecords">0</span> | 
                                <strong>Invalid Records:</strong> <span id="invalidRecords">0</span>
                            </div>
                        </div>
                        </div>
                        
                    <!-- Step 3: Import Progress -->
                    <div id="step3" class="import-step" style="display: none;">
                        <h6 class="mb-3">Import Progress</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar" id="importProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="importStatus" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Importing...</span>
                            </div>
                            <div class="mt-2">Processing contacts...</div>
                        </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" id="prevStep" style="display: none;">
                        <i class="bi bi-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="nextStep">
                        <i class="bi bi-arrow-right me-2"></i>Next
                    </button>
                    <button type="submit" class="btn btn-success" id="importBtn" style="display: none;">
                        <i class="bi bi-upload me-2"></i>Import Contacts
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<!-- Contact History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Contact History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6>Contact Created</h6>
                            <p class="text-muted mb-1">2024-01-15 10:30 AM</p>
                            <p>Contact was added to the system</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6>Email Sent</h6>
                            <p class="text-muted mb-1">2024-01-16 02:15 PM</p>
                            <p>Welcome email sent successfully</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>Contact Updated</h6>
                            <p class="text-muted mb-1">2024-01-17 09:45 AM</p>
                            <p>Phone number updated</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
// Search functionality with improved debouncing and filtering
let searchDebounceTimer;
const searchInput = document.getElementById('searchInput');

searchInput.addEventListener('input', function(e) {
    const searchTerm = e.target.value.trim();
    
    // Show/hide search help
    document.getElementById('searchHelp').style.display = searchTerm.length > 0 ? 'block' : 'none';
    
    // Clear existing timer
    clearTimeout(searchDebounceTimer);
    
    // Set new timer for debounced search
    searchDebounceTimer = setTimeout(() => {
        performSearch();
    }, 300); // 300ms debounce
});

// Add Enter key support for immediate search
searchInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchDebounceTimer);
        performSearch();
    }
});

// Perform search with current filters
function performSearch() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const statusFilter = getCurrentStatusFilter();
    const sortBy = document.getElementById('sortBy').value;
    
    // Update filter visual states
    updateFilterVisuals();
    
    // Always use loadContacts which handles both search and filtering
    loadContacts(1, 50, searchTerm, statusFilter, '', sortBy);
}

// Update visual indicators for active filters
function updateFilterVisuals() {
    // Check search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput.value.trim()) {
        searchInput.classList.add('filter-active');
    } else {
        searchInput.classList.remove('filter-active');
    }
    
    // Check status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter.value) {
        statusFilter.classList.add('filter-active');
    } else {
        statusFilter.classList.remove('filter-active');
    }
    
    // Check sort filter (highlight if not default)
    const sortBy = document.getElementById('sortBy');
    if (sortBy.value !== 'created_at') {
        sortBy.classList.add('filter-active');
    } else {
        sortBy.classList.remove('filter-active');
    }
    
    // Update filter count badge
    updateActiveFilterCount();
}

// Show count of active filters
function updateActiveFilterCount() {
    let activeCount = 0;
    if (document.getElementById('searchInput').value.trim()) activeCount++;
    if (document.getElementById('statusFilter').value) activeCount++;
    if (document.getElementById('sortBy').value !== 'created_at') activeCount++;
    
    // Find or create the badge element
    let badge = document.getElementById('activeFiltersBadge');
    const clearButton = document.querySelector('button[onclick="clearFilters()"]');
    
    if (activeCount > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'activeFiltersBadge';
            badge.className = 'active-filters-badge';
            clearButton.parentNode.insertBefore(badge, clearButton);
        }
        badge.textContent = `${activeCount} active`;
        badge.style.display = 'inline-block';
    } else if (badge) {
        badge.style.display = 'none';
    }
}

// Filter functionality - trigger unified search
document.getElementById('statusFilter').addEventListener('change', performSearch);
document.getElementById('sortBy').addEventListener('change', performSearch);

function getCurrentSearchTerm() {
    return document.getElementById('searchInput').value;
}

function getCurrentStatusFilter() {
    return document.getElementById('statusFilter').value;
}

function clearFilters() {
    // Clear all filter inputs
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('sortBy').value = 'created_at';
    
    // Clear visual indicators
    document.getElementById('searchInput').classList.remove('filter-active');
    document.getElementById('statusFilter').classList.remove('filter-active');
    document.getElementById('sortBy').classList.remove('filter-active');
    
    // Hide search help
    document.getElementById('searchHelp').style.display = 'none';
    
    // Clear any existing search timer
    clearTimeout(searchDebounceTimer);
    
    // Update filter count
    updateActiveFilterCount();
    
    // Reload contacts without filters
    loadContacts(1, 50, '', '', '', 'created_at');
}

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Removed searchContacts function - now using unified loadContacts for all operations

// Load contacts from API with improved error handling and search support
let currentRequest = null;
function loadContacts(page = 1, perPage = 50, search = '', status = '', company = '', sortBy = 'created_at') {
    const loadingRow = document.getElementById('loadingRow');
    const tableBody = document.getElementById('contactsTableBody');
    const paginationInfo = document.querySelector('.pagination-info');
    
    // Cancel any pending request
    if (currentRequest) {
        currentRequest.abort();
    }
    
    // Show loading state
    loadingRow.style.display = 'table-row';
    
    // Clear the table body except for the loading row
    const rows = tableBody.querySelectorAll('tr:not(#loadingRow)');
    rows.forEach(row => row.remove());
    
    // Build API URL with all parameters
    const params = new URLSearchParams({
        action: 'list_all',
        page: page,
        per_page: perPage
    });
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (company) params.append('company', company);
    if (sortBy) {
        // Default sort direction is DESC for created_at, ASC for everything else
        const sortDirection = sortBy === 'created_at' ? 'DESC' : 'ASC';
        params.append('sort_by', sortBy);
        params.append('sort_direction', sortDirection);
    }
    
    const apiUrl = `api/contacts_api.php?${params.toString()}`;
    console.log('Loading contacts:', apiUrl);
    console.log('Search term:', search);
    console.log('Filters:', { status, company, sortBy });
    
    // Create abort controller for this request
    const controller = new AbortController();
    currentRequest = controller;
    
    fetch(apiUrl, { signal: controller.signal })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data);
            loadingRow.style.display = 'none';
            currentRequest = null;
            
            if (data.success) {
                // Display the contacts
                displayContacts(data.data);
                
                // Update pagination
                updatePagination(data.pagination);
                
                // Update stats
                updateStats(data.pagination.total);
                
                // Update pagination info text
                if (paginationInfo && data.pagination) {
                    const start = data.pagination.total > 0 ? (data.pagination.current_page - 1) * data.pagination.per_page + 1 : 0;
                    const end = Math.min(start + data.data.length - 1, data.pagination.total);
                    paginationInfo.textContent = data.pagination.total > 0 
                        ? `Showing ${start}-${end} of ${data.pagination.total} contacts`
                        : 'No contacts found';
                }
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="alert alert-danger mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${data.error || 'Failed to load contacts'}
                            </div>
                        </td>
                    </tr>`;
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') {
                console.log('Request was cancelled');
                return;
            }
            
            loadingRow.style.display = 'none';
            currentRequest = null;
            console.error('Fetch error:', error);
            
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to load contacts. Please check your connection and try again.
                        </div>
                    </td>
                </tr>`;
        });
}

// Display contacts in table
function displayContacts(contacts) {
    const tableBody = document.getElementById('contactsTableBody');
    
    // Clear all rows except loading row
    const existingRows = tableBody.querySelectorAll('tr:not(#loadingRow)');
    existingRows.forEach(row => row.remove());
    
    // Hide loading row
    const loadingRow = document.getElementById('loadingRow');
    if (loadingRow) {
        loadingRow.style.display = 'none';
    }
    
    if (!contacts || contacts.length === 0) {
        const noDataRow = document.createElement('tr');
        noDataRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4">No contacts found</td>';
        tableBody.appendChild(noDataRow);
        return;
    }
    
    contacts.forEach(contact => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="form-check">
                    <input class="form-check-input contact-checkbox" type="checkbox" value="${contact.id}">
                </div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="contact-avatar me-2">
                        ${getInitials(contact.name)}
                    </div>
                    <div>
                        <div class="fw-bold">${contact.name}</div>
                    </div>
                </div>
            </td>
            <td>${contact.email || 'N/A'}</td>
            <td>${contact.company || 'N/A'}</td>
            <td>${contact.dot || 'N/A'}</td>
            <td>
                <span class="badge bg-${getStatusColor(contact.status)} status-badge">
                    ${contact.status || 'active'}
                </span>
            </td>
            <td>${formatDate(contact.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewContact(${contact.id})">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editContact(${contact.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory(${contact.id})">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteContact(${contact.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

// Update pagination with current filters preserved
function updatePagination(pagination) {
    const paginationElement = document.getElementById('pagination');
    if (!paginationElement || !pagination) return;
    
    // Show/hide pagination based on results
    paginationElement.style.display = pagination.total_pages > 1 ? '' : 'none';
    
    let paginationHtml = '';
    
    // Previous button
    paginationHtml += `
        <li class="page-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Calculate page range to show
    const maxPagesToShow = 5;
    let startPage = Math.max(1, pagination.current_page - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(pagination.total_pages, startPage + maxPagesToShow - 1);
    
    // Adjust start if we're near the end
    if (endPage - startPage < maxPagesToShow - 1) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    // First page + ellipsis
    if (startPage > 1) {
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
            </li>`;
        if (startPage > 2) {
            paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        paginationHtml += `
            <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>`;
    }
    
    // Last page + ellipsis
    if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
            paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(${pagination.total_pages}); return false;">${pagination.total_pages}</a>
            </li>`;
    }
    
    // Next button
    paginationHtml += `
        <li class="page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    paginationElement.innerHTML = paginationHtml;
}

// Change page while preserving current filters
function changePage(page) {
    const searchTerm = getCurrentSearchTerm();
    const statusFilter = getCurrentStatusFilter();
    const sortBy = document.getElementById('sortBy').value;
    const perPage = parseInt(document.getElementById('perPageSelect')?.value || 50);
    
    loadContacts(page, perPage, searchTerm, statusFilter, '', sortBy);
}

// Update stats
function updateStats(total) {
    const totalContactsElement = document.getElementById('totalContacts');
    if (totalContactsElement) {
        totalContactsElement.textContent = total;
    }
}

// Helper functions
function getStatusColor(status) {
    switch(status) {
        case 'active': return 'success';
        case 'inactive': return 'secondary';
        case 'pending': return 'warning';
        default: return 'primary';
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function getInitials(name) {
    if (!name) return 'NA';
    return name.split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
}



// Contact actions
function viewContact(id) {
    console.log('View contact:', id);
    
    // Show loading state
    const viewModal = new bootstrap.Modal(document.getElementById('viewContactModal'));
    
    // Clear previous data
    document.getElementById('viewName').textContent = 'Loading...';
    document.getElementById('viewEmail').textContent = 'Loading...';
    document.getElementById('viewCompany').textContent = 'Loading...';
    document.getElementById('viewDot').textContent = 'Loading...';
    document.getElementById('viewContactId').textContent = 'Loading...';
    document.getElementById('viewDateAdded').textContent = 'Loading...';
    
    // Show the modal
    viewModal.show();
    
    // Fetch contact data
    fetch(`api/contacts_api.php?action=view&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const contact = data.contact;
                document.getElementById('viewName').textContent = contact.name || '-';
                document.getElementById('viewEmail').textContent = contact.email || '-';
                document.getElementById('viewCompany').textContent = contact.company || '-';
                document.getElementById('viewDot').textContent = contact.dot || '-';
                document.getElementById('viewContactId').textContent = contact.id || '-';
                
                // Format date if available
                let dateAdded = '-';
                if (contact.created_at) {
                    const date = new Date(contact.created_at);
                    dateAdded = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                } else if (contact.date_added) {
                    const date = new Date(contact.date_added);
                    dateAdded = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                }
                document.getElementById('viewDateAdded').textContent = dateAdded;
            } else {
                // Handle error
                document.getElementById('viewName').textContent = 'Error loading data';
                document.getElementById('viewEmail').textContent = 'Error loading data';
                document.getElementById('viewCompany').textContent = 'Error loading data';
                document.getElementById('viewDot').textContent = 'Error loading data';
                document.getElementById('viewContactId').textContent = 'Error loading data';
                document.getElementById('viewDateAdded').textContent = 'Error loading data';
                
                showAlert('Error loading contact details: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error fetching contact:', error);
            // Handle network error
            document.getElementById('viewName').textContent = 'Network error';
            document.getElementById('viewEmail').textContent = 'Network error';
            document.getElementById('viewCompany').textContent = 'Network error';
            document.getElementById('viewDot').textContent = 'Network error';
            document.getElementById('viewContactId').textContent = 'Network error';
            document.getElementById('viewDateAdded').textContent = 'Network error';
            
            showAlert('Network error while loading contact details', 'danger');
        });
}

function editContact(id) {
    console.log('Edit contact:', id);
    
    // Show loading state
    const editBtn = document.querySelector(`button[onclick="editContact(${id})"]`);
    const originalText = editBtn.innerHTML;
    editBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    editBtn.disabled = true;
    
    // Fetch contact data
    fetch(`api/contacts_api.php?action=view&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit modal with contact data
                const contact = data.data;
                document.getElementById('editContactId').value = contact.id;
                document.getElementById('editName').value = contact.name || '';
                document.getElementById('editEmail').value = contact.email || '';
                document.getElementById('editCompany').value = contact.company || '';
                document.getElementById('editDot').value = contact.dot || '';
                
                // Show the modal
                const editModal = new bootstrap.Modal(document.getElementById('editContactModal'));
                editModal.show();
            } else {
                alert('Error: ' + (data.error || 'Failed to load contact data'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: Failed to load contact data. Please try again.');
        })
        .finally(() => {
            // Reset button state
            editBtn.innerHTML = originalText;
            editBtn.disabled = false;
        });
}

function viewHistory(id) {
    console.log('View history:', id);
    const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
    historyModal.show();
}

function deleteContact(id) {
    if (confirm('Are you sure you want to delete this contact?')) {
        // Show loading state on the specific delete button
        const deleteBtn = document.querySelector(`button[onclick="deleteContact(${id})"]`);
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        deleteBtn.disabled = true;
        
        // Call the delete API
        fetch(`api/contacts_api.php?action=delete&id=${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('Contact deleted successfully!');
                
                // Remove the row from the table
                const row = deleteBtn.closest('tr');
                if (row) {
                    row.remove();
                }
                
                // Reload contacts to update pagination and stats
                loadContacts();
                
                // Reload the page to refresh all data
                window.location.reload();
            } else {
                // Show error message
                alert('Error: ' + (data.error || data.message || 'Failed to delete contact'));
                
                // Reset button state on error
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: Failed to delete contact. Please try again.');
            
            // Reset button state on error
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    }
}

function bulkDelete() {
    const selectedContacts = document.querySelectorAll('.contact-checkbox:checked');
    if (selectedContacts.length === 0) {
        alert('Please select contacts to delete');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedContacts.length} contact(s)?`)) {
        // Get selected contact IDs
        const contactIds = Array.from(selectedContacts).map(checkbox => checkbox.value);
        
        // Show loading state
        const deleteBtn = document.querySelector('button[onclick="bulkDelete()"]');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Deleting...';
        deleteBtn.disabled = true;
        
        // Call the bulk delete API
        fetch('api/contacts_api.php?action=bulk_delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: contactIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert(`Successfully deleted ${data.deleted_count} contact(s)!`);
                 //reload the page
                 window.location.reload();
                // Reload contacts list
                loadContacts();
                
                // Uncheck all checkboxes
                document.querySelectorAll('.contact-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                //reload the page
               // window.location.reload();
                
            } else {
                // Show error message
                alert('Error: ' + (data.error || data.message || 'Failed to delete contacts'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            //alert('Error: Failed to delete contacts. Please try again.');
        })
        .finally(() => {
            // Reset button state
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    }
}

function exportContacts() {
    const selectedContacts = document.querySelectorAll('.contact-checkbox:checked');
    
    if (selectedContacts.length === 0) {
        // If no contacts selected, ask user if they want to export all
        if (confirm('No contacts selected. Do you want to export all contacts?')) {
            // Export all contacts
            exportAllContacts();
        } else {
            alert('Please select contacts to export or choose "Export All"');
        }
        return;
    }
    
    // Get selected contact IDs
    const contactIds = Array.from(selectedContacts).map(checkbox => checkbox.value);
    
    // Show loading state
    const exportBtn = document.querySelector('button[onclick="exportContacts()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Downloading...';
    exportBtn.disabled = true;
    
    // Use fetch to get the file and trigger download
    const formData = new FormData();
    contactIds.forEach(id => {
        formData.append('contact_ids[]', id);
    });
    
    fetch('api/contacts_api.php?action=export_contacts', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `contacts_export_${new Date().toISOString().slice(0,10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        // Show success message
        alert(`Successfully exported ${selectedContacts.length} contacts!`);
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Failed to export contacts. Please try again.');
    })
    .finally(() => {
        // Reset button state
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

function exportAllContacts() {
    // Show loading state
    const exportBtn = document.querySelector('button[onclick="exportContacts()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Downloading All...';
    exportBtn.disabled = true;
    
    // Use fetch to get all contacts and trigger download
    fetch('api/contacts_api.php?action=export_contacts', {
        method: 'POST',
        body: new FormData() // Empty form data means export all
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `all_contacts_export_${new Date().toISOString().slice(0,10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        // Show success message
        alert('Successfully exported all contacts!');
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Failed to export contacts. Please try again.');
    })
    .finally(() => {
        // Reset button state
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

// Form submission - Simple working version
document.getElementById('addContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const formData = {
        name: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim(),
        company: document.getElementById('company').value.trim(),
        dot: document.getElementById('dot').value.trim()
    };
    
    // Validate required fields
    if (!formData.name || !formData.email) {
        alert('Name and email are required fields.');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
        alert('Please enter a valid email address.');
        return;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('#addContactForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
    submitBtn.disabled = true;
    
    // Call the create_contact API
    fetch('api/contacts_api.php?action=create_contact', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Contact created successfully! Contact ID: ' + data.data.id);
            //refresh the page
            window.location.reload();
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addContactModal'));
            modal.hide();
            
            // Reset form
            document.getElementById('addContactForm').reset();
            
            // Reload contacts list
            loadContacts();
            
        } else {
            // Show error message
            alert('Error: ' + (data.error || data.message || 'Failed to create contact'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
       // alert('Error: Failed to create contact. Please try again.');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Edit Contact Form submission
document.getElementById('editContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const formData = {
        id: document.getElementById('editContactId').value,
        name: document.getElementById('editName').value.trim(),
        email: document.getElementById('editEmail').value.trim(),
        company: document.getElementById('editCompany').value.trim(),
        dot: document.getElementById('editDot').value.trim()
    };
    
    // Validate required fields
    if (!formData.name || !formData.email) {
        alert('Name and email are required fields.');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
        alert('Please enter a valid email address.');
        return;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('#editContactForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
    submitBtn.disabled = true;
    
    // Call the update API
    fetch('api/contacts_api.php?action=update', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Contact updated successfully!');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editContactModal'));
            modal.hide();
            
            // Reset form
            document.getElementById('editContactForm').reset();
            
            // Reload contacts list to reflect changes
            loadContacts();
            
            // Refresh the page to ensure all data is current
            window.location.reload();
            
        } else {
            // Show error message
            alert('Error: ' + (data.error || data.message || 'Failed to update contact'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: Failed to update contact. Please try again.');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Import Modal Functionality
let currentStep = 1;
let importData = [];

// Download template function
function downloadTemplate(type) {
    const url = `api/contacts_api.php?action=download_template&type=${type}`;
    const link = document.createElement('a');
    link.href = url;
    link.download = `contacts_template.${type}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Import modal step navigation
document.getElementById('nextStep').addEventListener('click', function() {
    if (currentStep === 1) {
        // Validate file selection
        const fileInput = document.getElementById('importFile');
        if (!fileInput.files[0]) {
            alert('Please select a file to import.');
            return;
        }
        
        // Preview the file
        previewImportFile(fileInput.files[0]);
        showStep(2);
    } else if (currentStep === 2) {
        // Start import process
        showStep(3);
        startImport();
    }
});

document.getElementById('prevStep').addEventListener('click', function() {
    if (currentStep === 2) {
        showStep(1);
    } else if (currentStep === 3) {
        showStep(2);
    }
});

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.import-step').forEach(el => el.style.display = 'none');
    
    // Show current step
    document.getElementById(`step${step}`).style.display = 'block';
    
    // Update buttons
    const prevBtn = document.getElementById('prevStep');
    const nextBtn = document.getElementById('nextStep');
    const importBtn = document.getElementById('importBtn');
    
    if (step === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'inline-block';
        importBtn.style.display = 'none';
    } else if (step === 2) {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'inline-block';
        importBtn.style.display = 'none';
    } else if (step === 3) {
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'none';
        importBtn.style.display = 'none';
    }
    
    currentStep = step;
}

function previewImportFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('action', 'preview_import');
    formData.append('skip_header', document.getElementById('skipHeader').checked ? '1' : '0');
    
    fetch('api/contacts_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            importData = data.data;
            displayPreview(data.data);
            updatePreviewStats(data.stats);
        } else {
            alert('Error: ' + (data.error || 'Failed to preview file'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error previewing file. Please try again.');
    });
}

function displayPreview(data) {
    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';
    
    data.slice(0, 10).forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.name || ''}</td>
            <td>${row.email || ''}</td>
            <td>${row.company || ''}</td>
            <td>${row.dot || ''}</td>
            <td>
                <span class="badge bg-${row.isValid ? 'success' : 'danger'}">
                    ${row.isValid ? 'Valid' : 'Invalid'}
                </span>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    if (data.length > 10) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="5" class="text-center text-muted">... and ${data.length - 10} more records</td>`;
        tbody.appendChild(tr);
    }
}

function updatePreviewStats(stats) {
    document.getElementById('totalRecords').textContent = stats.total || 0;
    document.getElementById('validRecords').textContent = stats.valid || 0;
    document.getElementById('invalidRecords').textContent = stats.invalid || 0;
}

function startImport() {
    const formData = new FormData();
    formData.append('action', 'import_contacts');
    formData.append('skip_header', document.getElementById('skipHeader').checked ? '1' : '0');
    
    // Add the file
    const fileInput = document.getElementById('importFile');
    formData.append('file', fileInput.files[0]);
    
    fetch('api/contacts_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            document.getElementById('importStatus').innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    Import completed successfully! ${data.imported} contacts imported.
                </div>
            `;
            //reload the page
            window.location.reload();
            
            // Reload contacts after a delay
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
                modal.hide();
                loadContacts();
            }, 2000);
        } else {
            document.getElementById('importStatus').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Import failed: ${data.error || 'Unknown error'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('importStatus').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Network error during import. Please try again.
            </div>
        `;
    });
}

// Import form submission
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    startImport();
});

// Change per page
function changePerPage(perPage) {
    loadContacts(1, parseInt(perPage), getCurrentSearchTerm(), getCurrentStatusFilter(), '', document.getElementById('sortBy').value);
}

// Initialize tooltips if Bootstrap tooltips are used
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Load filter options dynamically
function loadFilterOptions() {
    fetch('get_filter_options.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate company filter
                const companyFilter = document.getElementById('companyFilter');
                companyFilter.innerHTML = '<option value="">All Companies</option>';
                
                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company;
                    option.textContent = company;
                    companyFilter.appendChild(option);
                });
                
                // Update status filter with counts if needed
                if (data.statusCounts) {
                    const statusFilter = document.getElementById('statusFilter');
                    statusFilter.innerHTML = `
                        <option value="">All Status (${data.statusCounts.all})</option>
                        <option value="active">Active (${data.statusCounts.active})</option>
                        <option value="inactive">Inactive (${data.statusCounts.inactive})</option>
                        <option value="pending">Pending (${data.statusCounts.pending})</option>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

// Alert function for user notifications
function showAlert(message, type = 'info', duration = 5000) {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert.position-fixed');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
    alert.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(alert);
    
    // Auto remove after duration
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, duration);
}

// Load contacts on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadFilterOptions();
    loadContacts();
    initializeTooltips();
    
    // Set focus on search input for better UX
    document.getElementById('searchInput').focus();
});
    </script>

</body>
</html> 