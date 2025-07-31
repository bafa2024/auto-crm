<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Get user permissions
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

$database = new Database();
$db = $database->getConnection();

$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Helper function to check permissions
function hasPermission($permissions, $permission) {
    return isset($permissions[$permission]) && $permissions[$permission];
}

include __DIR__ . "/../components/header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Email Campaign Management</title>
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include __DIR__ . "/../components/employee-sidebar.php"; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-outline-primary" onclick="refreshProfile()">
                            <i class="fas fa-sync-alt me-2"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user me-2"></i> Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="profileLoading" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading profile...</p>
                                </div>
                                
                                <div id="profileContent" style="display: none;">
                                    <form id="profileForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">First Name</label>
                                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email" required readonly>
                                                    <small class="text-muted">Email cannot be changed</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Phone</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Company</label>
                                            <input type="text" class="form-control" id="company" name="company_name">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" id="role" readonly>
                                            <small class="text-muted">Role is managed by administrator</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <input type="text" class="form-control" id="status" readonly>
                                            <small class="text-muted">Status is managed by administrator</small>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-check-circle me-2"></i> Update Profile
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                                <i class="fas fa-undo me-2"></i> Reset
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Permission Summary -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shield-alt me-2"></i> Your Permissions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-muted">Campaign Permissions</h6>
                                    <div class="small">
                                        <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Create Campaigns
                                            </div>
                                        <?php endif; ?>
                                        <?php if (hasPermission($permissions, 'can_send_campaigns')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Send Campaigns
                                            </div>
                                        <?php endif; ?>
                                        <?php if (hasPermission($permissions, 'can_edit_campaigns')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Edit Campaigns
                                            </div>
                                        <?php endif; ?>
                                        <?php if (hasPermission($permissions, 'can_delete_campaigns')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Delete Campaigns
                                            </div>
                                        <?php endif; ?>
                                        <?php if (hasPermission($permissions, 'can_view_all_campaigns')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> View All Campaigns
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="text-muted">Contact Permissions</h6>
                                    <div class="small">
                                        <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Manage Contacts
                                            </div>
                                        <?php endif; ?>
                                        <?php if (hasPermission($permissions, 'can_export_contacts')): ?>
                                            <div class="text-success mb-1">
                                                <i class="fas fa-check"></i> Export Contacts
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        Contact your administrator if you need additional permissions.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-detect base path for live hosting compatibility
        const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
        
        // Load profile on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadProfile();
        });
        
        function loadProfile() {
            document.getElementById('profileLoading').style.display = 'block';
            document.getElementById('profileContent').style.display = 'none';
            
            fetch(`${basePath}/api/employee/profile`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('profileLoading').style.display = 'none';
                    
                    if (data.success) {
                        const profile = data.profile;
                        document.getElementById('firstName').value = profile.first_name || '';
                        document.getElementById('lastName').value = profile.last_name || '';
                        document.getElementById('email').value = profile.email || '';
                        document.getElementById('phone').value = profile.phone || '';
                        document.getElementById('company').value = profile.company_name || '';
                        document.getElementById('role').value = profile.role || '';
                        document.getElementById('status').value = profile.status || '';
                        
                        document.getElementById('profileContent').style.display = 'block';
                    } else {
                        alert('Failed to load profile: ' + data.message);
                    }
                })
                .catch(error => {
                    document.getElementById('profileLoading').style.display = 'none';
                    console.error('Error:', error);
                    alert('Failed to load profile');
                });
        }
        
        function refreshProfile() {
            loadProfile();
        }
        
        function resetForm() {
            loadProfile();
        }
        
        // Handle form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(`${basePath}/api/employee/profile`, {
                method: 'PUT',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
                    loadProfile();
                } else {
                    alert(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update profile');
            });
        });
    </script>
</body>
</html> 