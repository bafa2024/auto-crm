<div class="dashboard-section">
    <h4 class="mb-4">Settings</h4>
    
    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab">Email SMTP</button>
        </li>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content">
        <!-- General Settings Tab -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <form id="generalSettingsForm">
                <div class="mb-3">
                    <label class="form-label">Notification Email</label>
                    <input type="email" class="form-control" name="notification_email" value="<?php echo $_SESSION['user_email'] ?? ''; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Change Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="New Password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password">
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
        
        <!-- SMTP Settings Tab -->
        <div class="tab-pane fade" id="smtp" role="tabpanel">
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle me-2"></i>
                Configure your SMTP settings to send emails through your own email server.
            </div>
            
            <form id="smtpSettingsForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="smtp_host" placeholder="smtp.gmail.com" required>
                            <small class="text-muted">Your SMTP server address</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Port <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="smtp_port" placeholder="587" required>
                            <small class="text-muted">Common ports: 25, 465 (SSL), 587 (TLS)</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="email" class="form-control" name="smtp_username" placeholder="your-email@gmail.com">
                            <small class="text-muted">Usually your email address</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password" placeholder="••••••••">
                            <small class="text-muted">Your email password or app-specific password</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Encryption</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="">None</option>
                                <option value="ssl">SSL</option>
                                <option value="tls" selected>TLS</option>
                            </select>
                            <small class="text-muted">Recommended: TLS</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Enable SMTP</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="smtp_enabled" id="smtpEnabled" value="1">
                                <label class="form-check-label" for="smtpEnabled">
                                    Use SMTP for sending emails
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">From Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="smtp_from_email" placeholder="noreply@yourdomain.com" required>
                            <small class="text-muted">Email address that will appear as sender</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">From Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="smtp_from_name" placeholder="Your Company Name" required>
                            <small class="text-muted">Name that will appear as sender</small>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
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
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const alertContainer = document.createElement('div');
        alertContainer.innerHTML = alertHtml;
        
        const firstForm = document.querySelector('.dashboard-section form');
        firstForm.parentNode.insertBefore(alertContainer.firstElementChild, firstForm);
        
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