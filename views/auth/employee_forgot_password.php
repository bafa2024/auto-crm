<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/base_path.php";

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
    <title>Employee Forgot Password - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .forgot-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .forgot-card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 10px;
        }
        .forgot-header {
            background-color: #6c757d;
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
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
        <div class="forgot-container">
            <div class="card forgot-card">
                <div class="forgot-header">
                    <i class="fas fa-user-tie fa-3x mb-3"></i>
                    <h4>Employee Forgot Password</h4>
                    <p class="mb-0">Enter your email to receive a password reset link</p>
                </div>
                <div class="card-body p-4">
                    <div id="forgotPasswordMessages"></div>
                    
                    <form id="employeeForgotPasswordForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="btn-text">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </span>
                                <span class="btn-loading">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Sending...
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
            
            const forgotPasswordForm = document.getElementById("employeeForgotPasswordForm");
            const submitBtn = document.getElementById("submitBtn");
            const btnText = submitBtn.querySelector(".btn-text");
            const btnLoading = submitBtn.querySelector(".btn-loading");
            const messagesContainer = document.getElementById("forgotPasswordMessages");
            
            forgotPasswordForm.addEventListener("submit", async function(e) {
                e.preventDefault();
                
                btnText.classList.add("hide");
                btnLoading.classList.add("show");
                submitBtn.disabled = true;
                
                messagesContainer.innerHTML = "";
                
                const formData = new FormData(forgotPasswordForm);
                const data = Object.fromEntries(formData.entries());
                
                try {
                    const apiUrl = basePath + "/api/employee/forgot-password";
                    
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
                                ${result.message || "Password reset link sent to your email"}
                            </div>
                        `;
                        
                        // Clear form
                        forgotPasswordForm.reset();
                        
                        // Show additional info in development mode
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            messagesContainer.innerHTML += `
                                <div class="alert alert-info">
                                    <strong>Development Mode:</strong> Check the console or logs for the reset link.
                                </div>
                            `;
                        }
                    } else {
                        messagesContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${result.message || "Failed to send reset link"}
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
    </script>
</body>
</html> 