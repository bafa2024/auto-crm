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

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
    exit;
}

require_once 'config/database.php';
$database = (new Database())->getConnection();
require_once 'services/EmailUploadService.php';

$message = '';
$messageType = '';

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
    $uploadService = new EmailUploadService($database);
    
    $file = $_FILES['email_file'];
    $campaignId = $_POST['campaign_id'] ?? null;
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload failed: ' . $file['error'];
        $messageType = 'danger';
    } else {
        // Check file extension only
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $message = 'Invalid file type. Please upload CSV or Excel file.';
            $messageType = 'danger';
        } else {
            // Process the file
            $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
            
            if ($result['success']) {
                $message = "Upload successful! Imported: {$result['imported']} contacts";
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
                <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upload Email Contacts</h5>
                        <p class="text-muted">Upload a CSV or Excel file with columns: DOT, Company Name, Customer Name, Email. Extra columns will be ignored.</p>
                        
                        <form method="POST" enctype="multipart/form-data">
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
                                <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls)</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload"></i> Upload Contacts
                            </button>
                            
                                <a href="<?php echo base_path('download_template.php'); ?>" class="btn btn-secondary">
                                <i class="bi bi-download"></i> Download Template
                            </a>
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
                
                <!-- Contacts List -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Contacts</h5>
                        <span class="badge bg-primary"><?php echo count($contacts); ?> contacts</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($contacts)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
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
                                                <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                                            </td>
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
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editContact(<?php echo $contact['id']; ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteContact(<?php echo $contact['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <h5 class="mt-3">No contacts yet</h5>
                                <p class="text-muted">Create your first contact or upload a file to get started</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContactModal">
                                    <i class="bi bi-person-plus"></i> Create Contact
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Uploads</h5>
                        <table class="table">
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
                                        <td><?php echo $upload['recipient_count']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($upload['upload_date'])); ?></td>
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
                <form method="POST">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
            alert("Edit contact " + contactId + " - Coming soon!");
        }
        
        function deleteContact(contactId) {
            if (confirm("Are you sure you want to delete this contact?")) {
                // TODO: Implement delete functionality
                alert("Delete contact " + contactId + " - Coming soon!");
            }
        }
    </script>
</body>
</html>