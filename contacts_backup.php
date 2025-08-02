<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Increase memory limit and execution time for uploads
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base path configuration
require_once 'config/base_path.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
    exit;
}

try {
    require_once 'config/database.php';
    $database = (new Database())->getConnection();
    
    // Check if vendor autoload exists before requiring EmailUploadService
    $vendorPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        throw new Exception('Vendor autoload not found. Please run: composer install');
    }
    
    require_once 'services/EmailUploadService.php';
} catch (Exception $e) {
    die("<div class='alert alert-danger'>System Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$message = '';
$messageType = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Handle contact creation
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    $dot = $_POST['dot'] ?? '';
    $companyName = $_POST['company_name'] ?? '';
    $customerName = $_POST['customer_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $campaignId = $_POST['campaign_id'] ?? null;
    
    // Validate required fields
    if (empty($email) || empty($customerName)) {
        $message = 'Email and Customer Name are required.';
        $messageType = 'danger';
    } else {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            try {
                // Normalize email to lowercase for case-insensitive comparison
                $normalizedEmail = strtolower(trim($email));
                
                // Check if email already exists (case-insensitive)
                $stmt = $database->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
                $stmt->execute([$normalizedEmail]);
                if ($stmt->fetch()) {
                    $message = 'A contact with this email address already exists.';
                    $messageType = 'warning';
                } else {
                    // Use proper datetime handling for both SQLite and MySQL
                    $currentTime = date('Y-m-d H:i:s');
                    
                    // Handle campaign_id - convert empty string to NULL
                    if (empty($campaignId) || $campaignId === '' || $campaignId === '0') {
                        $campaignId = null;
                    }
                    
                    // Insert new contact with normalized email
                    $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $database->prepare($sql);
                    $stmt->execute([$normalizedEmail, $customerName, $companyName, $dot, $campaignId, $currentTime]);
                    
                    $message = 'Contact created successfully!';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Failed to create contact: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Handle file upload
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_file'])) {
    try {
        // Check which service to use
        $vendorPath = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($vendorPath)) {
            // Use simple service that doesn't require PhpSpreadsheet
            require_once 'services/SimpleEmailUploadService.php';
            $uploadService = new SimpleEmailUploadService($database);
        } else {
            $uploadService = new EmailUploadService($database);
        }
        
        $file = $_FILES['email_file'];
        $campaignId = $_POST['campaign_id'] ?? null;
        
        // Validate file upload
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
        } else {
            // Check file extension only
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $message = 'Invalid file type. Please upload CSV or Excel file.';
                $messageType = 'danger';
            } else {
                // Log the upload attempt
                error_log("Processing upload: " . $file['name'] . " (" . $file['size'] . " bytes)");
                
                // Process the file
                $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
                
                if ($result['success']) {
                    $message = "Upload successful! Imported: {$result['imported']} contacts";
                    if ($result['skipped'] > 0) {
                        $message .= ", Skipped: {$result['skipped']} (already imported)";
                    }
                    if ($result['failed'] > 0) {
                        $message .= ", Failed: {$result['failed']}";
                    }
                    if (!empty($result['errors'])) {
                        $message .= "<br>Errors:<br>" . implode("<br>", array_slice($result['errors'], 0, 5));
                        if (count($result['errors']) > 5) {
                            $message .= "<br>... and " . (count($result['errors']) - 5) . " more errors";
                        }
                    }
                    $messageType = 'success';
                } else {
                    $message = 'Upload failed: ' . $result['message'];
                    $messageType = 'danger';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Upload exception: " . $e->getMessage());
        $message = 'Upload error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

// Get existing campaigns for dropdown
$campaigns = [];
try {
    $stmt = $database->query("SELECT id, name FROM email_campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}

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

// Get existing contacts for display
$contacts = [];
try {
    $stmt = $database->query("
        SELECT 
            er.id,
            er.email,
            er.name,
            er.company,
            er.dot,
            er.created_at,
            ec.name as campaign_name
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        ORDER BY er.created_at DESC
        LIMIT 50
    ");
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            overflow-y: auto;
            z-index: 100;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #ffffff;
        }
        .sidebar-link {
            color: #495057;
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            margin: 4px 10px;
            transition: all 0.3s;
        }
        .sidebar-link:hover {
            background-color: #e9ecef;
            color: #212529;
        }
        .sidebar-link.active {
            background-color: #5B5FDE;
            color: white;
        }
        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'views/components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Contacts Management</h1>
                    <p class="text-muted">Upload and manage your email contacts</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="showReports()">
                        <i class="bi bi-graph-up"></i> Reports
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createContactModal">
                        <i class="bi bi-person-plus"></i> Create Contact
                    </button>
                    <button class="btn btn-primary" onclick="startDialer()">
                        <i class="bi bi-telephone"></i> Start Dialer
                    </button>
                </div>
            </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                <?php endif; ?>
                
            <div class="row">
                <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upload Email Contacts</h5>
                        <p class="text-muted">Upload a CSV or Excel file with columns: DOT, Company Name, Customer Name, Email. Extra columns will be ignored.</p>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
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
                                <div class="form-text">Select a campaign to associate these contacts with</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_file" class="form-label">Email List File</label>
                                <input type="file" class="form-control" id="email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                                <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls). Max size: 10MB</div>
                            </div>
                            
                            <div class="progress mb-3" style="display: none;" id="uploadProgress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-cloud-arrow-up"></i> Direct Upload
                            </button>
                            
                            <noscript>
                                <div class="alert alert-warning mt-3">
                                    JavaScript is disabled. The form will submit normally without progress tracking.
                                </div>
                            </noscript>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Template Format</h5>
                        <p>Your file should have the following columns (extra columns are ignored):</p>
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
                                    <td>DOODLEBUGBELL@YAHOO.COM</td>
                                </tr>
                                <tr>
                                    <td>226308</td>
                                    <td>ROBERT L COSBY TRUCKING LLC</td>
                                    <td>ROBERT L COSBY</td>
                                    <td>robertlcosby@gmail.com</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Uploads Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Uploads</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Recipients</th>
                                        <th>Upload Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentUploads)): ?>
                                        <?php foreach ($recentUploads as $upload): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($upload['campaign_name'] ?? 'No Campaign'); ?></td>
                                            <td><span class="badge bg-info"><?php echo $upload['recipient_count']; ?></span></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($upload['upload_date'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted">No uploads found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Contacts List -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Contacts</h5>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-danger" id="bulkDeleteBtn" style="display: none;" onclick="bulkDeleteContacts()">
                                <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                            </button>
                            <?php
                            // Get total count of all contacts
                            $totalCountStmt = $database->query("SELECT COUNT(*) as total FROM email_recipients");
                            $totalContacts = $totalCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <?php if ($totalContacts > 0): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAllContacts()">
                                <i class="bi bi-trash-fill"></i> Delete All (<?php echo $totalContacts; ?>)
                            </button>
                            <?php endif; ?>
                            <span class="badge bg-primary" id="contactsCount"><?php echo count($contacts); ?> contacts shown</span>
                        </div>
                    </div>
                    
                    <!-- Search and Filter Controls -->
                    <div class="card-body border-bottom">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="searchContacts" placeholder="Search contacts...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="dateFrom" placeholder="From Date">
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="dateTo" placeholder="To Date">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" onclick="applyFilters()">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                    <i class="bi bi-x-circle"></i> Clear Filters
                                </button>
                                <span id="activeFilters" class="ms-2 text-muted"></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="contactsLoading" class="text-center py-4" style="display: none;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading contacts...</p>
                        </div>
                        
                        <div id="contactsTableContainer">
                            <div class="table-responsive">
                                <table class="table table-hover" id="contactsTable">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" class="form-check-input" id="selectAllContacts" onchange="toggleSelectAll()">
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
                                    <tbody id="contactsTableBody">
                                        <!-- Contacts will be loaded here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <small class="text-muted">Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalContacts">0</span> contacts</small>
                                </div>
                                <nav aria-label="Contacts pagination">
                                    <ul class="pagination pagination-sm mb-0" id="pagination">
                                        <!-- Pagination will be generated here -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        
                        <div id="noContactsMessage" class="text-center py-4" style="display: none;">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h5 class="mt-3">No contacts found</h5>
                            <p class="text-muted">Try adjusting your search criteria or create your first contact</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContactModal">
                                <i class="bi bi-person-plus"></i> Create Contact
                            </button>
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
                    <h5 class="modal-title">Create New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo base_path('create_contact.php'); ?>">
                    <input type="hidden" name="action" value="create_contact">
                    <div class="modal-body">
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
                                    <select class="form-select" id="modal_campaign_id" name="campaign_id">
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
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" placeholder="e.g., BELL TRUCKING CO INC">
                        </div>
                        
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="e.g., JUDY BELL" required>
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
                <form id="editContactForm">
                    <input type="hidden" id="edit_contact_id" name="contact_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_dot" class="form-label">DOT Number</label>
                                    <input type="text" class="form-control" id="edit_dot" name="dot" placeholder="e.g., 170481">
                                    <div class="form-text">Optional DOT number</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_campaign_id" class="form-label">Campaign (Optional)</label>
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
                            <label for="edit_company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="edit_company_name" name="company_name" placeholder="e.g., BELL TRUCKING CO INC">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_customer_name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="edit_customer_name" name="customer_name" placeholder="e.g., JUDY BELL" required>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get base path for API calls
        const basePath = '<?php echo rtrim(base_path(''), '/'); ?>';
        console.log('Base path:', basePath);
        
        // Global variables for contacts management
        let currentPage = 1;
        let currentFilters = {
            search: '',
            dateFrom: '',
            dateTo: ''
        };
        
        // Load contacts with search and filtering
        function loadContacts(page = 1) {
            const loadingEl = document.getElementById('contactsLoading');
            const tableContainer = document.getElementById('contactsTableContainer');
            const noContactsEl = document.getElementById('noContactsMessage');
            
            // Show loading
            loadingEl.style.display = 'block';
            tableContainer.style.display = 'none';
            noContactsEl.style.display = 'none';
            
            // Build query parameters
            const params = new URLSearchParams({
                page: page,
                per_page: 20
            });
            
            if (currentFilters.search) {
                params.append('search', currentFilters.search);
            }
            if (currentFilters.dateFrom) {
                params.append('date_from', currentFilters.dateFrom);
            }
            if (currentFilters.dateTo) {
                params.append('date_to', currentFilters.dateTo);
            }
            
            // Fetch contacts from API
            fetch(`${basePath}/api/contacts?${params.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingEl.style.display = 'none';
                    
                    if (data.success && data.data && data.data.length > 0) {
                        renderContacts(data.data);
                        renderPagination(data.pagination);
                        updateContactCount(data.pagination);
                        tableContainer.style.display = 'block';
                    } else {
                        noContactsEl.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading contacts:', error);
                    loadingEl.style.display = 'none';
                    noContactsEl.style.display = 'block';
                    alert('Error loading contacts: ' + error.message);
                });
        }
        
        // Render contacts in the table
        function renderContacts(contacts) {
            const tbody = document.getElementById('contactsTableBody');
            tbody.innerHTML = '';
            
            contacts.forEach(contact => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="checkbox" class="form-check-input contact-checkbox" value="${contact.id}" onchange="updateSelectedCount()">
                    </td>
                    <td>
                        <strong>${escapeHtml(contact.name)}</strong>
                    </td>
                    <td>
                        <a href="mailto:${escapeHtml(contact.email)}">
                            ${escapeHtml(contact.email)}
                        </a>
                    </td>
                    <td>${escapeHtml(contact.company || '-')}</td>
                    <td>${escapeHtml(contact.dot || '-')}</td>
                    <td>
                        ${contact.campaign_name ? 
                            `<span class="badge bg-info">${escapeHtml(contact.campaign_name)}</span>` : 
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                    <td>
                        <small class="text-muted">
                            ${formatDate(contact.created_at)}
                        </small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editContact(${contact.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteContact(${contact.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Render pagination
        function renderPagination(pagination) {
            const paginationEl = document.getElementById('pagination');
            paginationEl.innerHTML = '';
            
            if (pagination.total_pages <= 1) return;
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${pagination.current_page <= 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${pagination.current_page - 1})">Previous</a>`;
            paginationEl.appendChild(prevLi);
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === pagination.current_page ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${i})">${i}</a>`;
                paginationEl.appendChild(li);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${pagination.current_page + 1})">Next</a>`;
            paginationEl.appendChild(nextLi);
        }
        
        // Update contact count display
        function updateContactCount(pagination) {
            const start = (pagination.current_page - 1) * pagination.per_page + 1;
            const end = Math.min(start + pagination.per_page - 1, pagination.total);
            
            document.getElementById('showingStart').textContent = start;
            document.getElementById('showingEnd').textContent = end;
            document.getElementById('totalContacts').textContent = pagination.total;
            document.getElementById('contactsCount').textContent = `${pagination.total} total contacts`;
        }
        
        // Apply filters
        function applyFilters() {
            currentFilters.search = document.getElementById('searchContacts').value.trim();
            currentFilters.dateFrom = document.getElementById('dateFrom').value;
            currentFilters.dateTo = document.getElementById('dateTo').value;
            
            // Update active filters display
            updateActiveFiltersDisplay();
            
            // Reset to first page and load contacts
            currentPage = 1;
            loadContacts(1);
        }
        
        // Clear filters
        function clearFilters() {
            document.getElementById('searchContacts').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            
            currentFilters = {
                search: '',
                dateFrom: '',
                dateTo: ''
            };
            
            updateActiveFiltersDisplay();
            currentPage = 1;
            loadContacts(1);
        }
        
        // Update active filters display
        function updateActiveFiltersDisplay() {
            const activeFiltersEl = document.getElementById('activeFilters');
            const filters = [];
            
            if (currentFilters.search) {
                filters.push(`Search: "${currentFilters.search}"`);
            }
            if (currentFilters.dateFrom) {
                filters.push(`From: ${formatDate(currentFilters.dateFrom)}`);
            }
            if (currentFilters.dateTo) {
                filters.push(`To: ${formatDate(currentFilters.dateTo)}`);
            }
            
            if (filters.length > 0) {
                activeFiltersEl.textContent = `Active filters: ${filters.join(', ')}`;
            } else {
                activeFiltersEl.textContent = '';
            }
        }
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Search on Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchContacts');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        applyFilters();
                    }
                });
            }
            
            // Initial load
            loadContacts(1);
        });
        
        // Debug: Check if JavaScript is running
        console.log('JavaScript loaded successfully');
        
        // Check for any JavaScript errors
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.message, 'at', e.filename, ':', e.lineno);
        });
        
        // Test API connectivity
        window.addEventListener('load', function() {
            console.log('Testing API connectivity...');
            console.log('Base path:', basePath);
            
            // Test recipients API specifically
            fetch(`${basePath}/api/recipients/1`)
                .then(response => {
                    console.log('Recipients API test response:', response.status);
                    if (response.status === 404) {
                        console.log('Recipients API is accessible (404 is expected for non-existent ID)');
                    } else if (!response.ok) {
                        console.error('Recipients API test failed with status:', response.status);
                    } else {
                        console.log('Recipients API is accessible');
                    }
                })
                .catch(error => {
                    console.error('Recipients API connectivity test failed:', error);
                    console.error('This might indicate the API endpoint is not accessible');
                });
        });
        
        function showSection(section) {
            alert("Section: " + section + " - Coming soon!");
        }
        
        function showReports() {
            alert("Reports feature coming soon!");
        }
        
        function startDialer() {
            alert("Auto Dialer feature coming soon!");
        }
        
        function editContact(contactId) {
            // Fetch contact data and populate the edit modal
            fetch(`${basePath}/api/recipients/${contactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const contact = data.data;
                        
                        // Populate the edit modal fields
                        document.getElementById('edit_contact_id').value = contact.id;
                        document.getElementById('edit_dot').value = contact.dot || '';
                        document.getElementById('edit_company_name').value = contact.company || '';
                        document.getElementById('edit_customer_name').value = contact.name || '';
                        document.getElementById('edit_email').value = contact.email || '';
                        document.getElementById('edit_campaign_id').value = contact.campaign_id || '';
                        
                        // Show the modal
                        const editModal = new bootstrap.Modal(document.getElementById('editContactModal'));
                        editModal.show();
                    } else {
                        alert('Error loading contact data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading contact data');
                });
        }
        
        function deleteContact(contactId) {
            if (confirm("Are you sure you want to delete this contact? This action cannot be undone.")) {
                console.log('Deleting contact ID:', contactId);
                
                // Try multiple API endpoint variations
                const apiUrls = [
                    `${basePath}/api/recipients/${contactId}`,
                    `${basePath}/api/test_direct.php?id=${contactId}`,
                    `/acrm/api/recipients/${contactId}`,
                    `/api/recipients/${contactId}`,
                    `${basePath}/delete_contact.php` // Fallback POST endpoint
                ];
                
                console.log('Trying API URLs:', apiUrls);
                
                // Try the first URL that works
                tryDelete(apiUrls, 0, contactId);
            }
        }
        
        function tryDelete(urls, index, contactId) {
            if (index >= urls.length) {
                alert('Failed to delete contact. All API endpoints failed.');
                return;
            }
            
            const apiUrl = urls[index];
            console.log(`Trying API URL ${index + 1}/${urls.length}:`, apiUrl);
            
            // Use POST for the fallback endpoint
            const isPost = apiUrl.includes('delete_contact.php');
            const fetchOptions = {
                method: isPost ? 'POST' : 'DELETE',
                headers: isPost ? {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                } : {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            };
            
            if (isPost) {
                fetchOptions.body = `id=${contactId}`;
            }
            
            fetch(apiUrl, fetchOptions)
            .then(response => {
                console.log(`Response from ${apiUrl}:`, response.status);
                
                if (!response.ok && index < urls.length - 1) {
                    // Try next URL
                    console.log('Failed, trying next URL...');
                    tryDelete(urls, index + 1, contactId);
                    return null;
                }
                
                return response.text();
            })
            .then(text => {
                if (!text) return; // Skip if we're trying next URL
                
                console.log('Response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        alert('Contact deleted successfully!');
                        reloadContacts();
                    } else {
                        alert('Error: ' + (data.message || data.error || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                        if (index < urls.length - 1) {
                            tryDelete(urls, index + 1, contactId);
                        } else {
                            alert('API returned HTML instead of JSON. Check server configuration.');
                        }
                    } else {
                        alert('Invalid response from server');
                    }
                }
            })
            .catch(error => {
                console.error(`Error with ${apiUrl}:`, error);
                if (index < urls.length - 1) {
                    tryDelete(urls, index + 1, contactId);
                } else {
                    alert('Network error: ' + error.message);
                }
            });
        }
        
        // Handle edit contact form submission
        document.getElementById('editContactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const contactId = document.getElementById('edit_contact_id').value;
            const formData = new FormData(this);
            
            // Convert form data to JSON with correct field names
            const data = {
                dot: formData.get('dot'),
                company_name: formData.get('company_name'),
                customer_name: formData.get('customer_name'),
                email: formData.get('email'),
                campaign_id: formData.get('campaign_id')
            };
            
            fetch(`${basePath}/api/recipients/${contactId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contact updated successfully!');
                    // Close the modal
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editContactModal'));
                    editModal.hide();
                    // Reload contacts to refresh the list
                    reloadContacts();
                } else {
                    alert('Error updating contact: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating contact');
            });
        });
        
        // Select all functionality
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllContacts');
            const contactCheckboxes = document.querySelectorAll('.contact-checkbox');
            
            contactCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateSelectedCount();
        }
        
        // Update selected count and show/hide bulk delete button
        function updateSelectedCount() {
            const selectedCheckboxes = document.querySelectorAll('.contact-checkbox:checked');
            const count = selectedCheckboxes.length;
            
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('bulkDeleteBtn').style.display = count > 0 ? 'inline-block' : 'none';
            
            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('selectAllContacts');
            const allCheckboxes = document.querySelectorAll('.contact-checkbox');
            selectAllCheckbox.checked = count > 0 && count === allCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
        }
        
        // Bulk delete contacts
        function bulkDeleteContacts() {
            const selectedCheckboxes = document.querySelectorAll('.contact-checkbox:checked');
            const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                alert('No contacts selected');
                return;
            }
            
            const confirmMessage = selectedIds.length === 1 
                ? 'Are you sure you want to delete this contact?' 
                : `Are you sure you want to delete ${selectedIds.length} contacts?`;
            
            if (confirm(confirmMessage + ' This action cannot be undone.')) {
                // Show loading state
                const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Deleting...';
                
                // Delete all selected recipients using the fallback endpoint
                const deletePromises = selectedIds.map(id => 
                    fetch(`${basePath}/delete_contact.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `id=${id}`,
                        credentials: 'same-origin'
                    })
                );
                
                Promise.all(deletePromises)
                    .then(responses => {
                        console.log('Bulk delete responses:', responses);
                        return Promise.all(responses.map(r => {
                            if (!r.ok) {
                                return r.text().then(text => {
                                    console.error(`Response error for status ${r.status}:`, text);
                                    return { success: false, error: text };
                                });
                            }
                            return r.json();
                        }));
                    })
                    .then(results => {
                        console.log('Bulk delete results:', results);
                        const successCount = results.filter(r => r.success).length;
                        const failCount = results.length - successCount;
                        
                        if (failCount > 0) {
                            const errors = results.filter(r => !r.success).map(r => r.error || r.message || 'Unknown error');
                            console.error('Failed deletions:', errors);
                            alert(`${successCount} contacts deleted successfully. ${failCount} failed to delete.\n\nErrors: ${errors.join('\n')}`);
                        } else {
                            alert(`${successCount} contacts deleted successfully!`);
                        }
                        
                        // Reload contacts to refresh the list
                        reloadContacts();
                    })
                    .catch(error => {
                        console.error('Bulk delete error:', error);
                        console.error('Error details:', error.message);
                        alert('Error deleting contacts: ' + error.message);
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = `<i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">${selectedIds.length}</span>)`;
                    });
            }
        }
        
        // Delete all contacts
        function deleteAllContacts() {
            // Show a strong warning dialog
            const warningMessage = `⚠️ WARNING: This will delete ALL contacts in the database!\n\n` +
                                 `This action will permanently remove ALL email recipients and cannot be undone.\n\n` +
                                 `Are you absolutely sure you want to delete ALL contacts?`;
            
            if (confirm(warningMessage)) {
                // Second confirmation for safety
                const finalConfirm = prompt('To confirm deletion of ALL contacts, please type "DELETE ALL" (case sensitive):');
                
                if (finalConfirm === 'DELETE ALL') {
                    // Show loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.7);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                    `;
                    loadingOverlay.innerHTML = `
                        <div class="bg-white p-4 rounded">
                            <div class="spinner-border text-danger" role="status"></div>
                            <p class="mt-2 mb-0">Deleting all contacts...</p>
                        </div>
                    `;
                    document.body.appendChild(loadingOverlay);
                    
                    // Call the delete all API with fallback
                    fetch(`${basePath}/delete_all_contacts.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        console.log('Delete all response:', response);
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        document.body.removeChild(loadingOverlay);
                        if (data.success) {
                            alert(`✅ ${data.message}`);
                            // Reload contacts
                            reloadContacts();
                        } else {
                            alert('❌ Error: ' + (data.message || 'Failed to delete all contacts'));
                        }
                    })
                    .catch(error => {
                        document.body.removeChild(loadingOverlay);
                        console.error('Delete all error:', error);
                        alert('❌ Error deleting all contacts: ' + error.message);
                    });
                } else {
                    alert('Deletion cancelled. You must type "DELETE ALL" exactly to confirm.');
                }
            }
        }
        
        // Debug upload function
        function testDebugUpload() {
            const fileInput = document.getElementById('email_file');
            if (fileInput.files.length === 0) {
                alert('Please select a file first');
                return;
            }
            
            const formData = new FormData();
            formData.append('email_file', fileInput.files[0]);
            formData.append('campaign_id', document.getElementById('campaign_id').value);
            
            console.log('Testing debug upload...');
            
            fetch(`${basePath}/debug_upload.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Debug response:', response);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed debug data:', data);
                    alert('Debug info logged to console. Check browser console for details.');
                } catch (e) {
                    console.error('Failed to parse response:', e);
                    console.error('Raw text:', text);
                    alert('Error: Response is not valid JSON. Check console for raw response.');
                }
            })
            .catch(error => {
                console.error('Debug error:', error);
                alert('Debug error: ' + error.message);
            });
        }
        
        // Direct form submission - no AJAX
        console.log('Upload form configured for direct submission');
    </script>
</body>
</html>