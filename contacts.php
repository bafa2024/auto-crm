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
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-danger" id="bulkDeleteBtn" style="display: none;" onclick="bulkDeleteContacts()">
                                <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                            </button>
                            <span class="badge bg-primary"><?php echo count($contacts); ?> contacts</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($contacts)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
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
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input contact-checkbox" value="<?php echo $contact['id']; ?>" onchange="updateSelectedCount()">
                                            </td>
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
        
        // Test API connectivity
        window.addEventListener('load', function() {
            console.log('Testing API connectivity...');
            fetch(`${basePath}/api/campaigns`)
                .then(response => {
                    console.log('API test response:', response.status);
                    if (!response.ok) {
                        console.error('API test failed with status:', response.status);
                    }
                })
                .catch(error => {
                    console.error('API connectivity test failed:', error);
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
                
                // Try different API endpoints
                const apiUrl = `${basePath}/api/recipients/${contactId}`;
                console.log('Trying API URL:', apiUrl);
                
                // First test if API is accessible
                fetch(`${basePath}/api/test.php`)
                    .then(response => response.json())
                    .then(testData => {
                        console.log('API test successful:', testData);
                        
                        // Now try the actual delete
                        return fetch(apiUrl, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin' // Include cookies for session
                        });
                    })
                    .then(response => {
                        console.log('Delete response status:', response.status);
                        console.log('Delete response headers:', response.headers);
                        
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Response text:', text);
                                // Check if it's HTML (likely 404 or error page)
                                if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                                    throw new Error('API endpoint not found - received HTML instead of JSON');
                                }
                                throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            alert('Contact deleted successfully!');
                            // Reload the page to refresh the contact list
                            location.reload();
                        } else {
                            alert('Error deleting contact: ' + (data.message || data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        console.error('Error details:', error.message);
                        
                        // Provide more helpful error messages
                        if (error.message.includes('Failed to fetch')) {
                            alert('Network error: Could not connect to the API. Please check if the server is running and the API path is correct.');
                            console.error('API URL was:', apiUrl);
                        } else if (error.message.includes('API endpoint not found')) {
                            alert('API endpoint not found. The server might be misconfigured.');
                        } else {
                            alert('Error deleting contact: ' + error.message);
                        }
                    });
            }
        }
        
        // Handle edit contact form submission
        document.getElementById('editContactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const contactId = document.getElementById('edit_contact_id').value;
            const formData = new FormData(this);
            
            // Convert form data to JSON
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
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
                    // Reload the page to refresh the contact list
                    location.reload();
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
                
                // Delete all selected contacts
                const deletePromises = selectedIds.map(id => 
                    fetch(`${basePath}/api/recipients/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        }
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
                        
                        // Reload the page to refresh the contact list
                        location.reload();
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
    </script>
</body>
</html>