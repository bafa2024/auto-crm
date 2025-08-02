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

try {
    require_once 'config/database.php';
    $database = (new Database())->getConnection();
    require_once 'services/EmailUploadService.php';
} catch (Exception $e) {
    die("<div class='alert alert-danger'>System Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$message = '';
$messageType = '';

// Handle contact creation (without campaign requirement)
if (isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    $dot = $_POST['dot'] ?? '';
    $companyName = $_POST['company_name'] ?? '';
    $customerName = $_POST['customer_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $campaignId = $_POST['campaign_id'] ?? null; // Optional campaign
    
    if (empty($email) || empty($customerName)) {
        $message = 'Email and Customer Name are required.';
        $messageType = 'danger';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            try {
                $normalizedEmail = strtolower(trim($email));
                
                $stmt = $database->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
                $stmt->execute([$normalizedEmail]);
                if ($stmt->fetch()) {
                    $message = 'A contact with this email address already exists.';
                    $messageType = 'warning';
                } else {
                    $currentTime = date('Y-m-d H:i:s');
                    
                    if (empty($campaignId) || $campaignId === '' || $campaignId === '0') {
                        $campaignId = null;
                    }
                    
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

// Handle file upload (without campaign requirement)
if (isset($_FILES['email_file'])) {
    try {
        $vendorPath = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($vendorPath)) {
            require_once 'services/SimpleEmailUploadService.php';
            $uploadService = new SimpleEmailUploadService($database);
        } else {
            $uploadService = new EmailUploadService($database);
        }
        
        $file = $_FILES['email_file'];
        $campaignId = $_POST['campaign_id'] ?? null; // Optional campaign
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = 'File upload failed.';
            $messageType = 'danger';
        } else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $message = 'Invalid file type. Please upload CSV or Excel file.';
                $messageType = 'danger';
            } else {
                $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
                
                if ($result['success']) {
                    $message = "Upload successful! Imported: {$result['imported']} contacts";
                    if ($result['skipped'] > 0) {
                        $message .= ", Skipped: {$result['skipped']} (already imported)";
                    }
                    $messageType = 'success';
                } else {
                    $message = 'Upload failed: ' . $result['message'];
                    $messageType = 'danger';
                }
            }
        }
    } catch (Exception $e) {
        $message = 'Upload error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

// Get existing campaigns for dropdown (optional)
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
    
    // Debug output
    echo "<!-- DEBUG: Query returned " . count($contacts) . " contacts -->";
    if (empty($contacts)) {
        echo "<!-- DEBUG: No contacts found in query -->";
    } else {
        echo "<!-- DEBUG: First contact: " . json_encode($contacts[0]) . " -->";
    }
    
} catch (Exception $e) {
    echo "<!-- DEBUG: Error in contacts query: " . $e->getMessage() . " -->";
    // Ignore error if table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Management - ACRM</title>
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
        .feature-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #5B5FDE;
            background: #f0f0ff;
        }
        .upload-area.dragover {
            border-color: #5B5FDE;
            background: #e8e8ff;
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
                    <p class="text-muted">Create and manage your email contacts without campaign restrictions</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createContactModal">
                        <i class="bi bi-person-plus"></i> Create Contact
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
                    <!-- Create Single Contact Card -->
                    <div class="feature-card">
                        <h5><i class="bi bi-person-plus"></i> Create Single Contact</h5>
                        <p class="text-muted">Add individual contacts to your database. Campaign assignment is optional.</p>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createContactModal">
                            <i class="bi bi-person-plus"></i> Create New Contact
                        </button>
                    </div>
                    
                    <!-- Bulk Upload Card -->
                    <div class="feature-card">
                        <h5><i class="bi bi-cloud-arrow-up"></i> Bulk Upload Contacts</h5>
                        <p class="text-muted">Upload CSV or Excel files with multiple contacts. Campaign assignment is optional.</p>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="campaign_id" class="form-label">Campaign (Optional)</label>
                                    <select class="form-select" id="campaign_id" name="campaign_id">
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
                                    <label for="email_file" class="form-label">Email List File</label>
                                    <div class="upload-area" id="uploadArea">
                                        <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                                        <p class="mt-3">Drag and drop your file here or click to browse</p>
                                        <input type="file" class="form-control d-none" id="email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                                        <button type="button" class="btn btn-outline-primary mt-2" onclick="document.getElementById('email_file').click()">
                                            <i class="bi bi-folder2-open"></i> Choose File
                                        </button>
                                        <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls). Max size: 10MB</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success mt-3">
                                <i class="bi bi-cloud-arrow-up"></i> Upload Contacts
                            </button>
                        </form>
                    </div>
                    
                    <!-- Template Format Card -->
                    <div class="feature-card">
                        <h5><i class="bi bi-file-earmark-text"></i> File Format Template</h5>
                        <p>Your file should have the following columns (extra columns will be ignored):</p>
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
                    
                    <!-- Recent Uploads Summary -->
                    <div class="feature-card">
                        <h5><i class="bi bi-clock-history"></i> Recent Uploads</h5>
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
                    
                    <!-- Contacts List -->
                    <div class="feature-card">
                        <h5><i class="bi bi-people"></i> All Contacts</h5>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($contacts)): ?>
                                        <?php foreach ($contacts as $contact): ?>
                                        <tr>
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
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted">No contacts found.</td></tr>
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
                                    <div class="form-text">Optional campaign assignment</div>
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
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('email_file');
        
        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay();
            }
        });
        
        fileInput.addEventListener('change', updateFileDisplay);
        
        function updateFileDisplay() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                uploadArea.innerHTML = `
                    <i class="bi bi-file-earmark-text display-4 text-success"></i>
                    <p class="mt-3"><strong>${file.name}</strong></p>
                    <p class="text-muted">Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="changeFile()">
                        <i class="bi bi-arrow-repeat"></i> Change File
                    </button>
                `;
            }
        }
        
        function changeFile() {
            fileInput.value = '';
            uploadArea.innerHTML = `
                <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                <p class="mt-3">Drag and drop your file here or click to browse</p>
                <input type="file" class="form-control d-none" id="email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                <button type="button" class="btn btn-outline-primary mt-2" onclick="document.getElementById('email_file').click()">
                    <i class="bi bi-folder2-open"></i> Choose File
                </button>
                <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls). Max size: 10MB</div>
            `;
        }
    </script>
</body>
</html> 