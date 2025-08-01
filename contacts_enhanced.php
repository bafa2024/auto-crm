<?php
// Enhanced Contacts Page with History and Data Management
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

$user = $_SESSION;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Management - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .history-item {
            border-left: 4px solid #007bff;
            padding: 10px;
            margin: 10px 0;
            background: #f8f9fa;
        }
        .upload-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            background: white;
        }
        .batch-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky">
                    <div class="d-flex align-items-center mb-3 p-3">
                        <i class="fas fa-phone me-2"></i>
                        <span class="fw-bold">AutoDial Pro</span>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="contacts.php">
                                <i class="fas fa-address-book me-2"></i>Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="campaigns.php">
                                <i class="fas fa-envelope me-2"></i>Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="instant_email.php">
                                <i class="fas fa-paper-plane me-2"></i>Instant Email
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Contacts Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportContacts()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-upload me-1"></i>Upload
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5><i class="fas fa-users me-2"></i>Total Contacts</h5>
                            <h3 id="totalContacts">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5><i class="fas fa-upload me-2"></i>Recent Uploads</h5>
                            <h3 id="recentUploads">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5><i class="fas fa-clock me-2"></i>Last 7 Days</h5>
                            <h3 id="last7Days">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5><i class="fas fa-calendar me-2"></i>Last 30 Days</h5>
                            <h3 id="last30Days">-</h3>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="contactsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab">
                            <i class="fas fa-address-book me-1"></i>All Contacts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                            <i class="fas fa-history me-1"></i>Contact History
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="uploads-tab" data-bs-toggle="tab" data-bs-target="#uploads" type="button" role="tab">
                            <i class="fas fa-upload me-1"></i>Upload History
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="management-tab" data-bs-toggle="tab" data-bs-target="#management" type="button" role="tab">
                            <i class="fas fa-cogs me-1"></i>Data Management
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="contactsTabsContent">
                    <!-- All Contacts Tab -->
                    <div class="tab-pane fade show active" id="contacts" role="tabpanel">
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>All Contacts</h4>
                                    <div class="action-buttons">
                                        <button class="btn btn-danger btn-sm" onclick="deleteAllContacts()">
                                            <i class="fas fa-trash me-1"></i>Delete All
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="archiveOldContacts()">
                                            <i class="fas fa-archive me-1"></i>Archive Old
                                        </button>
                                    </div>
                                </div>
                                <div id="contactsList" class="table-responsive">
                                    <!-- Contacts will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="row mt-3">
                            <div class="col-12">
                                <h4>Contact History</h4>
                                <div class="mb-3">
                                    <label for="contactId" class="form-label">Contact ID:</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="contactId" placeholder="Enter contact ID">
                                        <button class="btn btn-primary" onclick="loadContactHistory()">
                                            <i class="fas fa-search me-1"></i>Load History
                                        </button>
                                    </div>
                                </div>
                                <div id="contactHistoryList">
                                    <!-- Contact history will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload History Tab -->
                    <div class="tab-pane fade" id="uploads" role="tabpanel">
                        <div class="row mt-3">
                            <div class="col-12">
                                <h4>Upload History</h4>
                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label for="uploadDays" class="form-label">Days:</label>
                                            <select class="form-select" id="uploadDays">
                                                <option value="7">Last 7 days</option>
                                                <option value="30" selected>Last 30 days</option>
                                                <option value="90">Last 90 days</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button class="btn btn-primary d-block" onclick="loadUploadHistory()">
                                                <i class="fas fa-refresh me-1"></i>Refresh
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="uploadHistoryList">
                                    <!-- Upload history will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Management Tab -->
                    <div class="tab-pane fade" id="management" role="tabpanel">
                        <div class="row mt-3">
                            <div class="col-12">
                                <h4>Data Management</h4>
                                
                                <!-- Batch Management -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5><i class="fas fa-layer-group me-2"></i>Batch Management</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Recent Batches</h6>
                                                <div class="mb-3">
                                                    <select class="form-select" id="batchSelect">
                                                        <option value="">Select a batch...</option>
                                                    </select>
                                                </div>
                                                <div class="action-buttons">
                                                    <button class="btn btn-info btn-sm" onclick="viewBatchDetails()">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                    <button class="btn btn-warning btn-sm" onclick="archiveBatch()">
                                                        <i class="fas fa-archive me-1"></i>Archive
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteBatch()">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Time-based Management</h6>
                                                <div class="mb-3">
                                                    <label for="timeRange" class="form-label">Time Range:</label>
                                                    <select class="form-select" id="timeRange">
                                                        <option value="7">Last 7 days</option>
                                                        <option value="30" selected>Last 30 days</option>
                                                        <option value="90">Last 90 days</option>
                                                    </select>
                                                </div>
                                                <div class="action-buttons">
                                                    <button class="btn btn-warning btn-sm" onclick="archiveByTime()">
                                                        <i class="fas fa-archive me-1"></i>Archive
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteByTime()">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistics -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Management Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="managementStats">
                                            <!-- Statistics will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Contacts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm">
                        <div class="mb-3">
                            <label for="uploadFile" class="form-label">Select File (CSV/Excel)</label>
                            <input type="file" class="form-control" id="uploadFile" accept=".csv,.xlsx,.xls" required>
                        </div>
                        <div class="mb-3">
                            <label for="batchName" class="form-label">Batch Name</label>
                            <input type="text" class="form-control" id="batchName" placeholder="Enter batch name">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="uploadContacts()">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '<?php echo base_path(); ?>';
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadContacts();
            loadManagementStats();
            loadUploadHistory();
        });

        // Load contacts
        function loadContacts() {
            fetch(`${basePath}/api/contacts`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayContacts(data.data);
                        updateStats(data.data);
                    }
                })
                .catch(error => console.error('Error loading contacts:', error));
        }

        // Display contacts
        function displayContacts(contacts) {
            const container = document.getElementById('contactsList');
            if (!contacts || contacts.length === 0) {
                container.innerHTML = '<p class="text-muted">No contacts found.</p>';
                return;
            }

            let html = `
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            contacts.forEach(contact => {
                html += `
                    <tr>
                        <td>${contact.first_name} ${contact.last_name}</td>
                        <td>${contact.email || '-'}</td>
                        <td>${contact.phone || '-'}</td>
                        <td>${contact.company || '-'}</td>
                        <td><span class="badge bg-${contact.status === 'active' ? 'success' : 'secondary'}">${contact.status}</span></td>
                        <td>${new Date(contact.created_at).toLocaleDateString()}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="editContact(${contact.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteContact(${contact.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="viewHistory(${contact.id})">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Update statistics
        function updateStats(contacts) {
            document.getElementById('totalContacts').textContent = contacts.length;
            // Other stats will be loaded separately
        }

        // Load contact history
        function loadContactHistory() {
            const contactId = document.getElementById('contactId').value;
            if (!contactId) {
                alert('Please enter a contact ID');
                return;
            }

            fetch(`${basePath}/api/contact-history/${contactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayContactHistory(data.data);
                    } else {
                        document.getElementById('contactHistoryList').innerHTML = '<p class="text-muted">No history found.</p>';
                    }
                })
                .catch(error => console.error('Error loading contact history:', error));
        }

        // Display contact history
        function displayContactHistory(history) {
            const container = document.getElementById('contactHistoryList');
            if (!history || history.length === 0) {
                container.innerHTML = '<p class="text-muted">No history found.</p>';
                return;
            }

            let html = '';
            history.forEach(item => {
                html += `
                    <div class="history-item">
                        <div class="d-flex justify-content-between">
                            <strong>${item.action}</strong>
                            <small>${new Date(item.performed_at).toLocaleString()}</small>
                        </div>
                        <div>Performed by: ${item.performed_by_email || 'System'}</div>
                        ${item.notes ? `<div class="text-muted">${item.notes}</div>` : ''}
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Load upload history
        function loadUploadHistory() {
            const days = document.getElementById('uploadDays').value;
            fetch(`${basePath}/api/contact-history/recent-uploads?days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUploadHistory(data.data);
                    }
                })
                .catch(error => console.error('Error loading upload history:', error));
        }

        // Display upload history
        function displayUploadHistory(data) {
            const container = document.getElementById('uploadHistoryList');
            const uploads = data.uploads;
            const summary = data.summary;

            if (!uploads || uploads.length === 0) {
                container.innerHTML = '<p class="text-muted">No uploads found.</p>';
                return;
            }

            let html = `
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>${summary.total_uploads || 0}</h5>
                                <small>Total Uploads</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>${summary.total_records || 0}</h5>
                                <small>Total Records</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>${summary.total_successful || 0}</h5>
                                <small>Successful</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>${summary.total_failed || 0}</h5>
                                <small>Failed</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            uploads.forEach(upload => {
                html += `
                    <div class="upload-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>${upload.session_name}</h6>
                                <p class="mb-1"><strong>File:</strong> ${upload.filename}</p>
                                <p class="mb-1"><strong>Records:</strong> ${upload.total_records} | <strong>Successful:</strong> ${upload.successful_uploads} | <strong>Failed:</strong> ${upload.failed_uploads}</p>
                                <small class="text-muted">Uploaded by: ${upload.uploaded_by_email || 'Unknown'} on ${new Date(upload.uploaded_at).toLocaleString()}</small>
                            </div>
                            <span class="badge bg-${upload.status === 'completed' ? 'success' : upload.status === 'processing' ? 'warning' : 'danger'}">${upload.status}</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Load management statistics
        function loadManagementStats() {
            fetch(`${basePath}/api/contact-history/management-stats`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayManagementStats(data.data);
                    }
                })
                .catch(error => console.error('Error loading management stats:', error));
        }

        // Display management statistics
        function displayManagementStats(stats) {
            const container = document.getElementById('managementStats');
            
            let html = '<div class="row">';
            
            // Last 7 days
            const last7 = stats.last_7_days;
            html += `
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Last 7 Days</h6>
                            <p class="mb-1">Total: ${last7.total_contacts || 0}</p>
                            <p class="mb-1">Active: ${last7.active_contacts || 0}</p>
                            <p class="mb-1">Batches: ${last7.unique_batches || 0}</p>
                        </div>
                    </div>
                </div>
            `;

            // Last 30 days
            const last30 = stats.last_30_days;
            html += `
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Last 30 Days</h6>
                            <p class="mb-1">Total: ${last30.total_contacts || 0}</p>
                            <p class="mb-1">Active: ${last30.active_contacts || 0}</p>
                            <p class="mb-1">Batches: ${last30.unique_batches || 0}</p>
                        </div>
                    </div>
                </div>
            `;

            // Upload stats
            const uploadStats = stats.upload_stats;
            html += `
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Upload Statistics</h6>
                            <p class="mb-1">Uploads: ${uploadStats.total_uploads || 0}</p>
                            <p class="mb-1">Records: ${uploadStats.total_records || 0}</p>
                            <p class="mb-1">Success Rate: ${uploadStats.total_records > 0 ? Math.round((uploadStats.successful_uploads / uploadStats.total_records) * 100) : 0}%</p>
                        </div>
                    </div>
                </div>
            `;

            html += '</div>';
            container.innerHTML = html;
        }

        // Contact management functions
        function deleteContact(id) {
            if (confirm('Are you sure you want to delete this contact?')) {
                fetch(`${basePath}/api/contacts/${id}`, { method: 'DELETE' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadContacts();
                            alert('Contact deleted successfully');
                        } else {
                            alert('Error deleting contact');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function deleteAllContacts() {
            if (confirm('Are you sure you want to delete ALL contacts? This action cannot be undone!')) {
                fetch(`${basePath}/api/contacts/delete-all`, { method: 'DELETE' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadContacts();
                            alert(`Successfully deleted ${data.data.deleted_count} contacts`);
                        } else {
                            alert('Error deleting contacts');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function archiveOldContacts() {
            if (confirm('Archive contacts older than 30 days?')) {
                fetch(`${basePath}/api/contact-history/archive-batch`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ days: 30 })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadContacts();
                            alert(`Successfully archived ${data.data.archived_count} contacts`);
                        } else {
                            alert('Error archiving contacts');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        // Batch management functions
        function deleteBatch() {
            const batchId = document.getElementById('batchSelect').value;
            if (!batchId) {
                alert('Please select a batch');
                return;
            }

            if (confirm('Are you sure you want to delete this batch?')) {
                fetch(`${basePath}/api/contact-history/delete-batch`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: batchId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Successfully deleted ${data.data.deleted_count} contacts`);
                            loadContacts();
                        } else {
                            alert('Error deleting batch');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function archiveBatch() {
            const batchId = document.getElementById('batchSelect').value;
            if (!batchId) {
                alert('Please select a batch');
                return;
            }

            if (confirm('Archive this batch?')) {
                fetch(`${basePath}/api/contact-history/archive-batch`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: batchId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Successfully archived ${data.data.archived_count} contacts`);
                            loadContacts();
                        } else {
                            alert('Error archiving batch');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function deleteByTime() {
            const days = document.getElementById('timeRange').value;
            if (confirm(`Delete contacts from the last ${days} days?`)) {
                fetch(`${basePath}/api/contact-history/delete-batch`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ days: parseInt(days) })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Successfully deleted ${data.data.deleted_count} contacts`);
                            loadContacts();
                        } else {
                            alert('Error deleting contacts');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function archiveByTime() {
            const days = document.getElementById('timeRange').value;
            if (confirm(`Archive contacts from the last ${days} days?`)) {
                fetch(`${basePath}/api/contact-history/archive-batch`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ days: parseInt(days) })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Successfully archived ${data.data.archived_count} contacts`);
                            loadContacts();
                        } else {
                            alert('Error archiving contacts');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        // Upload functions
        function uploadContacts() {
            const fileInput = document.getElementById('uploadFile');
            const batchName = document.getElementById('batchName').value;
            
            if (!fileInput.files[0]) {
                alert('Please select a file');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            if (batchName) {
                formData.append('batch_name', batchName);
            }

            fetch(`${basePath}/api/contacts/bulk-upload`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Upload successful!');
                        document.getElementById('uploadForm').reset();
                        bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                        loadContacts();
                        loadUploadHistory();
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function exportContacts() {
            window.open(`${basePath}/api/contacts/export`, '_blank');
        }

        function viewHistory(contactId) {
            document.getElementById('contactId').value = contactId;
            document.getElementById('history-tab').click();
            loadContactHistory();
        }

        function editContact(id) {
            // Implement edit functionality
            alert('Edit functionality to be implemented');
        }
    </script>
</body>
</html> 