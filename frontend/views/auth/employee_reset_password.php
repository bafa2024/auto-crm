<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/base_path.php";

// Get token from URL
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header("Location: " . base_path('employee/login'));
    exit();
}

// Redirect if already logged in
if (isset($_SESSION["user_id"]) && in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: " . base_path('employee/email-dashboard'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Reset Password - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .reset-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .reset-card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 10px;
        }
        .reset-header {
            background-color: #6c757d;
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle .form-control {
            padding-right: 40px;
        }
        .password-toggle .btn {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            border: none;
            background: transparent;
            color: #6c757d;
        }
        .btn-loading {
            display: none;
        }
        .btn-loading.show {
            display: inline-block;
        }
        .btn-text.hide {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="card reset-card">
                <div class="reset-header">
                    <i class="fas fa-user-tie fa-3x mb-3"></i>
                    <h4>Employee Reset Password</h4>
                    <p class="mb-0">Enter your new password below</p>
                </div>
                <div class="card-body p-4">
                    <div id="resetPasswordMessages"></div>
                    
                    <form id="employeeResetPasswordForm">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group password-toggle">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <button type="button" class="btn" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 6 characters long</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group password-toggle">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                <button type="button" class="btn" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="btn-text">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </span>
                                <span class="btn-loading">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Resetting...
                                </span>
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="text-muted mb-2">Remember your password?</p>
                        <a href="<?php echo base_path('employee/login'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Login
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="<?php echo base_path(); ?>" class="text-decoration-none">
                            <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Auto-detect base path for live hosting compatibility
            const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
            
            const resetPasswordForm = document.getElementById("employeeResetPasswordForm");
            const submitBtn = document.getElementById("submitBtn");
            const btnText = submitBtn.querySelector(".btn-text");
            const btnLoading = submitBtn.querySelector(".btn-loading");
            const messagesContainer = document.getElementById("resetPasswordMessages");
            
            // Validate token on page load
            const token = new URLSearchParams(window.location.search).get('token');
            if (token) {
                validateToken(token);
            }
            
            async function validateToken(token) {
                try {
                    const response = await fetch(`${basePath}/api/employee/validate-reset-token?token=${token}`, {
                        method: "GET",
                        headers: {
                            "Content-Type": "application/json",
                        }
                    });
                    
                    const result = await response.json();
                    
                    if (!response.ok || !result.success) {
                        messagesContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Invalid or expired reset token. Please request a new password reset link.
                            </div>
                        `;
                        resetPasswordForm.style.display = 'none';
                        return false;
                    }
                    
                    // Show user info
                    messagesContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Resetting password for: <strong>${result.data.email}</strong>
                        </div>
                    `;
                    return true;
                    
                } catch (error) {
                    messagesContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error validating token. Please try again.
                        </div>
                    `;
                    return false;
                }
            }
            
            resetPasswordForm.addEventListener("submit", async function(e) {
                e.preventDefault();
                
                const password = resetPasswordForm.querySelector('input[name="password"]').value;
                const confirmPassword = resetPasswordForm.querySelector('input[name="confirm_password"]').value;
                
                if (password !== confirmPassword) {
                    messagesContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Passwords do not match.
                        </div>
                    `;
                    return;
                }
                
                if (password.length < 6) {
                    messagesContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Password must be at least 6 characters long.
                        </div>
                    `;
                    return;
                }
                
                btnText.classList.add("hide");
                btnLoading.classList.add("show");
                submitBtn.disabled = true;
                
                const formData = new FormData(resetPasswordForm);
                const data = Object.fromEntries(formData.entries());
                
                try {
                    const apiUrl = basePath + "/api/employee/reset-password";
                    
                    const response = await fetch(apiUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        messagesContainer.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                ${result.message || "Password has been reset successfully"}
                            </div>
                        `;
                        
                        // Clear form
                        resetPasswordForm.reset();
                        
                        // Redirect to login after 3 seconds
                        setTimeout(() => {
                            window.location.href = basePath + "/employee/login";
                        }, 3000);
                        
                    } else {
                        messagesContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${result.message || "Failed to reset password"}
                            </div>
                        `;
                    }
                } catch (error) {
                    messagesContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Network error. Please try again.
                        </div>
                    `;
                } finally {
                    btnText.classList.remove("hide");
                    btnLoading.classList.remove("show");
                    submitBtn.disabled = false;
                }
            });
        });
        
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(inputId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 