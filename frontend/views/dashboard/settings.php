<?php
// Prevent session already started error
require_once __DIR__ . '/../../config/base_path.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
    exit;
}

// Get user info from session
$userName = $_SESSION["user_name"] ?? "User";
$userEmail = $_SESSION["user_email"] ?? "user@example.com";
?>
<?php include __DIR__ . "/../components/header.php"; ?>

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
.settings-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-radius: 12px;
    margin-bottom: 20px;
}
.settings-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
}
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
}
.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    padding: 12px 24px;
    font-weight: 500;
    transition: all 0.3s;
}
.nav-tabs .nav-link:hover {
    color: #495057;
    background-color: #f8f9fa;
}
.nav-tabs .nav-link.active {
    color: #667eea;
    background-color: transparent;
    border-bottom: 3px solid #667eea;
}
.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}
.form-control, .form-select {
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 10px 15px;
    transition: all 0.3s;
}
.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.1);
}
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-weight: 500;
    transition: all 0.3s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
.btn-secondary {
    background-color: #6c757d;
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-weight: 500;
}
.btn-info {
    background-color: #17a2b8;
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-weight: 500;
}
.smtp-info-box {
    background: #f0f8ff;
    border-left: 4px solid #17a2b8;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.config-example {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.code-block {
    background: #2d3748;
    color: #fff;
    padding: 15px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    margin: 10px 0;
    font-size: 14px;
}
.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}
.small-text {
    font-size: 0.875rem;
    color: #6c757d;
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

<?php include __DIR__ . "/../components/sidebar.php"; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Settings Header -->
        <div class="settings-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Settings</h1>
                    <p class="mb-0 opacity-75">Manage your account and email configuration</p>
                </div>
                <div>
                    <i class="bi bi-gear" style="font-size: 3rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="settings-card card">
            <div class="card-body p-0">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs px-4 pt-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                            <i class="bi bi-person me-2"></i>General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab">
                            <i class="bi bi-envelope me-2"></i>Email SMTP
                        </button>
                    </li>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content p-4">
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <h5 class="mb-4">Account Settings</h5>
                                <form id="generalSettingsForm">
                                    <div class="mb-4">
                                        <label class="form-label">Notification Email</label>
                                        <input type="email" class="form-control" name="notification_email" value="<?php echo $_SESSION['user_email'] ?? ''; ?>">
                                        <small class="small-text">We'll use this email for important notifications</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-4">Change Password</h5>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" placeholder="Enter new password">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password">
                                        <small class="small-text">Leave blank to keep current password</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Save Changes
                                    </button>
                                </form>
                            </div>
                            <div class="col-lg-4">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Account Information</h6>
                                        <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($userName); ?></p>
                                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?></p>
                                        <p class="mb-0"><strong>Role:</strong> <?php echo ucfirst($_SESSION['user_role'] ?? 'user'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SMTP Settings Tab -->
                    <div class="tab-pane fade" id="smtp" role="tabpanel">
                        <div class="smtp-info-box">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-2">About SMTP Configuration</h6>
                                    <p class="mb-0">Configure your SMTP settings to send emails through your own email server. This gives you better control over email deliverability and sender reputation.</p>
                                </div>
                            </div>
                        </div>
                        
                        <form id="smtpSettingsForm">
                            <h5 class="mb-4">Server Configuration</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="smtp_host" placeholder="smtp.gmail.com" required>
                                        <small class="small-text">Your SMTP server address</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Port <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="smtp_port" placeholder="587" required>
                                        <small class="small-text">Common ports: 25, 465 (SSL), 587 (TLS)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-4">Authentication</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="email" class="form-control" name="smtp_username" placeholder="your-email@gmail.com">
                                        <small class="small-text">Usually your email address</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="smtp_password" placeholder="••••••••">
                                        <small class="small-text">Your email password or app-specific password</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Encryption</label>
                                        <select class="form-select" name="smtp_encryption">
                                            <option value="">None</option>
                                            <option value="ssl">SSL</option>
                                            <option value="tls" selected>TLS (Recommended)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" name="smtp_enabled" id="smtpEnabled" value="1">
                                            <label class="form-check-label" for="smtpEnabled">
                                                Enable SMTP for sending emails
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-4">Sender Information</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">From Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="smtp_from_email" placeholder="noreply@yourdomain.com" required>
                                        <small class="small-text">Email address that will appear as sender</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">From Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="smtp_from_name" placeholder="Your Company Name" required>
                                        <small class="small-text">Name that will appear as sender</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mb-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save SMTP Settings
                                </button>
                                <button type="button" class="btn btn-secondary" id="testConnectionBtn">
                                    <i class="bi bi-wifi me-2"></i>Test Connection
                                </button>
                                <button type="button" class="btn btn-info" id="sendTestEmailBtn">
                                    <i class="bi bi-envelope me-2"></i>Send Test Email
                                </button>
                            </div>
                        </form>
                        
                        <!-- Common Configurations -->
                        <div class="config-example">
                            <h6 class="mb-3">Common SMTP Configurations</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <strong>Gmail</strong>
                                    <div class="code-block">
                                        Host: smtp.gmail.com<br>
                                        Port: 587<br>
                                        Encryption: TLS<br>
                                        <small class="text-warning">Requires app password</small>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong>Outlook/Office 365</strong>
                                    <div class="code-block">
                                        Host: smtp-mail.outlook.com<br>
                                        Port: 587<br>
                                        Encryption: TLS
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong>Yahoo Mail</strong>
                                    <div class="code-block">
                                        Host: smtp.mail.yahoo.com<br>
                                        Port: 587<br>
                                        Encryption: TLS<br>
                                        <small class="text-warning">Requires app password</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Test Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" name="test_email" placeholder="test@example.com" required>
                        <small class="text-muted">Enter email address to receive test email</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmSendTestEmail">Send Test</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basePath = '<?php echo base_path(""); ?>';
    
    // Load SMTP settings on page load
    loadSmtpSettings();
    
    // General settings form handler
    document.getElementById('generalSettingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword && newPassword !== confirmPassword) {
            showAlert('danger', 'Passwords do not match');
            return;
        }
        
        // TODO: Implement general settings save
        showAlert('info', 'General settings update not yet implemented');
    });
    
    // SMTP settings form handler
    document.getElementById('smtpSettingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // Convert checkbox value
        data.smtp_enabled = formData.has('smtp_enabled') ? '1' : '0';
        
        try {
            const response = await fetch(basePath + '/api/settings/smtp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showAlert('success', 'SMTP settings saved successfully!');
            } else {
                showAlert('danger', result.message || result.error || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Network error. Please try again.');
        }
    });
    
    // Test connection button
    document.getElementById('testConnectionBtn').addEventListener('click', async function() {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';
        btn.disabled = true;
        
        try {
            const response = await fetch(basePath + '/api/settings/smtp-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showAlert('success', result.message || 'Connection successful!');
            } else {
                showAlert('danger', result.message || result.error || 'Connection failed');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Network error. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
    
    // Send test email button
    document.getElementById('sendTestEmailBtn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('testEmailModal'));
        modal.show();
    });
    
    // Confirm send test email
    document.getElementById('confirmSendTestEmail').addEventListener('click', async function() {
        const form = document.getElementById('testEmailForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const testEmail = form.querySelector('[name="test_email"]').value;
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
        btn.disabled = true;
        
        try {
            const response = await fetch(basePath + '/api/settings/test-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ test_email: testEmail })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showAlert('success', result.message || 'Test email sent successfully!');
                bootstrap.Modal.getInstance(document.getElementById('testEmailModal')).hide();
                form.reset();
            } else {
                showAlert('danger', result.message || result.error || 'Failed to send test email');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Network error. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
    
    // Load SMTP settings
    async function loadSmtpSettings() {
        try {
            const response = await fetch(basePath + '/api/settings/smtp');
            const result = await response.json();
            
            if (response.ok && result.success) {
                const form = document.getElementById('smtpSettingsForm');
                const data = result.data;
                
                // Populate form fields
                for (const [key, value] of Object.entries(data)) {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = value == '1';
                        } else if (key !== 'smtp_password' || !data.smtp_password_set) {
                            input.value = value || '';
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error loading SMTP settings:', error);
        }
    }
    
    // Show alert helper
    function showAlert(type, message) {
        // Remove any existing alerts
        const existingAlerts = document.querySelectorAll('.alert-dismissible');
        existingAlerts.forEach(alert => alert.remove());
        
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const alertContainer = document.createElement('div');
        alertContainer.innerHTML = alertHtml;
        
        // Insert at the top of the active tab pane
        const activeTabPane = document.querySelector('.tab-pane.active');
        activeTabPane.insertBefore(alertContainer.firstElementChild, activeTabPane.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert && alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>