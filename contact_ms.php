<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'version.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: views/auth/login.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submissions
$message = '';
$message_type = '';

// Handle single contact creation
if (isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $dot = trim($_POST['dot'] ?? '');
    
    if (empty($name) || empty($email)) {
        $message = 'Name and email are required fields.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO email_recipients (name, email, company, dot, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $company, $dot]);
            $message = 'Contact created successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error creating contact: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Handle bulk upload
if (isset($_POST['action']) && $_POST['action'] === 'upload_contacts' && isset($_FILES['email_file'])) {
    $file = $_FILES['email_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload error: ' . $file['error'];
        $message_type = 'danger';
    } else {
        $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $message = 'Invalid file type. Please upload CSV or Excel files only.';
            $message_type = 'danger';
        } else {
            try {
                // Load appropriate upload service
                if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    require_once 'services/EmailUploadService.php';
                    $uploadService = new EmailUploadService($pdo);
                } else {
                    require_once 'services/SimpleEmailUploadService.php';
                    $uploadService = new SimpleEmailUploadService($pdo);
                }
                
                $result = $uploadService->processUpload($file);
                
                if ($result['success']) {
                    $message = "Successfully uploaded {$result['imported']} contacts.";
                    if (!empty($result['errors'])) {
                        $message .= " Some rows had issues.";
                    }
                    $message_type = 'success';
                } else {
                    $message = $result['message'];
                    $message_type = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Error processing file: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Handle template download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_contacts.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DOT', 'Company Name', 'Customer Name', 'Email']);
    fputcsv($output, ['170481', 'BELL TRUCKING CO INC', 'JUDY BELL', 'judy@example.com']);
    fputcsv($output, ['170482', 'ABC TRANSPORT', 'JOHN DOE', 'john@example.com']);
    fputcsv($output, ['170483', 'XYZ LOGISTICS', 'JANE SMITH', 'jane@example.com']);
    fclose($output);
    exit;
}

// Get total contacts count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM email_recipients");
$total_contacts = $stmt->fetch()['total'];

// Get recent contacts
$stmt = $pdo->query("SELECT * FROM email_recipients ORDER BY created_at DESC LIMIT 5");
$recent_contacts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Management - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        /* Header */
        .top-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Content Area */
        .content-wrapper {
            padding: 2rem;
        }

        /* Cards */
        .feature-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            border-color: var(--success-color);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-warning {
            background: var(--warning-color);
            border-color: var(--warning-color);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-danger {
            background: var(--danger-color);
            border-color: var(--danger-color);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Forms */
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        /* Tables */
        .table {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content-wrapper {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-cube me-2"></i>
                ACRM
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="contact_ms.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Contacts
                </a>
            </div>
            <div class="nav-item">
                <a href="campaigns.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    Campaigns
                </a>
            </div>
            <div class="nav-item">
                <a href="views/dashboard/settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
            <div class="nav-item">
                <a href="views/auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-users me-2"></i>
                    Contacts Management
                </h1>
                <div class="user-menu">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?></span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content-wrapper">
            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-icon" style="background: var(--primary-color);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_contacts); ?></div>
                    <div class="stat-label">Total Contacts</div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-icon" style="background: var(--success-color);">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="stat-number">Quick Add</div>
                    <div class="stat-label">Single Contact</div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-icon" style="background: var(--warning-color);">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="stat-number">Bulk Upload</div>
                    <div class="stat-label">CSV/XLSX Files</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-plus-circle me-2"></i>
                                Create Single Contact
                            </h3>
                        </div>
                        <p class="text-muted mb-3">Add a new contact manually with all details.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContactModal">
                            <i class="fas fa-plus me-2"></i>
                            Add Contact
                        </button>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-upload me-2"></i>
                                Bulk Upload Contacts
                            </h3>
                        </div>
                        <p class="text-muted mb-3">Upload multiple contacts from CSV or Excel files.</p>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fas fa-upload me-2"></i>
                            Upload File
                        </button>
                        <a href="?action=download_template" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-download me-2"></i>
                            Template
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Contacts -->
            <div class="feature-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock me-2"></i>
                        Recent Contacts
                    </h3>
                    <a href="contacts.php" class="btn btn-outline-primary btn-sm">
                        View All
                    </a>
                </div>
                
                <?php if (!empty($recent_contacts)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Company</th>
                                    <th>DOT</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_contacts as $contact): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                                    <?php echo strtoupper(substr($contact['name'] ?? 'C', 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($contact['name'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($contact['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($contact['company'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($contact['dot'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editContact(<?php echo $contact['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteContact(<?php echo $contact['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No contacts yet</h5>
                        <p class="text-muted">Start by adding your first contact or uploading a file.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Contact Modal -->
    <div class="modal fade" id="createContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Create New Contact
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createContactForm">
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Create Contact
                        </button>
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
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>
                        Bulk Upload Contacts
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>File Format:</strong> CSV or Excel files with headers: DOT, Company Name, Customer Name, Email
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_file" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                        </div>
                        
                        <div class="upload-preview" id="uploadPreview" style="display: none;">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                File selected: <span id="fileName"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>
                            Upload Contacts
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload preview
        document.getElementById('email_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('uploadPreview');
            const fileName = document.getElementById('fileName');
            
            if (file) {
                fileName.textContent = file.name;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });

        // Create contact form submission
        document.getElementById('createContactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_contact');
            
            fetch('contact_ms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Contact created successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating contact. Please try again.');
            });
        });

        // Upload form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'upload_contacts');
            
            fetch('contact_ms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Contacts uploaded successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading contacts. Please try again.');
            });
        });

        // Contact actions
        function editContact(id) {
            // TODO: Implement edit functionality
            alert('Edit contact ' + id);
        }

        function deleteContact(id) {
            if (confirm('Are you sure you want to delete this contact?')) {
                // TODO: Implement delete functionality
                alert('Delete contact ' + id);
            }
        }
    </script>
</body>
</html> 