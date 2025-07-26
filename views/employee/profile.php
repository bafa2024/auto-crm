<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: /employee/login");
    exit;
}

include __DIR__ . "/../components/header.php";
include __DIR__ . "/../components/employee-sidebar.php";
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">My Profile</h1>
                <p class="text-muted">Manage your account information</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshProfile()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person"></i> Profile Information
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
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-check"></i> Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="accountInfo">
                            <p><strong>User ID:</strong> <span id="userId">-</span></p>
                            <p><strong>Member Since:</strong> <span id="memberSince">-</span></p>
                            <p><strong>Last Login:</strong> <span id="lastLogin">-</span></p>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-key"></i> Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-detect base path for live hosting compatibility
const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';

// Load profile on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProfile();
    
    // Form submissions
    document.getElementById('profileForm').addEventListener('submit', updateProfile);
    document.getElementById('passwordForm').addEventListener('submit', changePassword);
});

async function loadProfile() {
    const loadingEl = document.getElementById('profileLoading');
    const contentEl = document.getElementById('profileContent');

    try {
        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';

        const response = await fetch(basePath + '/api/employee/profile');
        const data = await response.json();

        if (response.ok && data.success) {
            const profile = data.profile;
            
            // Fill form fields
            document.getElementById('firstName').value = profile.first_name || '';
            document.getElementById('lastName').value = profile.last_name || '';
            document.getElementById('email').value = profile.email || '';
            document.getElementById('phone').value = profile.phone || '';
            document.getElementById('company').value = profile.company_name || '';
            document.getElementById('role').value = profile.role || '';
            document.getElementById('status').value = profile.status || '';
            
            // Fill account info
            document.getElementById('userId').textContent = profile.id || '-';
            document.getElementById('memberSince').textContent = profile.created_at ? new Date(profile.created_at).toLocaleDateString() : '-';
            document.getElementById('lastLogin').textContent = profile.last_login ? new Date(profile.last_login).toLocaleString() : '-';
            
            contentEl.style.display = 'block';
        } else {
            throw new Error(data.message || 'Failed to load profile');
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        alert('Error loading profile: ' + error.message);
    } finally {
        loadingEl.style.display = 'none';
    }
}

async function updateProfile(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch(basePath + '/api/employee/profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            alert('Profile updated successfully!');
            loadProfile(); // Reload to get updated data
        } else {
            alert('Failed to update profile: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        alert('Error updating profile');
    }
}

async function changePassword(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Validate passwords match
    if (data.new_password !== data.confirm_password) {
        alert('New passwords do not match');
        return;
    }
    
    // Validate password length
    if (data.new_password.length < 6) {
        alert('New password must be at least 6 characters long');
        return;
    }
    
    try {
        const response = await fetch(basePath + '/api/employee/change-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            alert('Password changed successfully!');
            form.reset();
        } else {
            alert('Failed to change password: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error changing password:', error);
        alert('Error changing password');
    }
}

function refreshProfile() {
    loadProfile();
}

function resetForm() {
    loadProfile();
}
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 