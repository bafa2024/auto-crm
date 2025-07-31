<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION["user_id"]) && in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/dashboard'));
    exit();
}

// Check for auth error from magic link
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 10px;
        }
        .login-header {
            background-color: #6c757d;
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .btn-loading {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="login-header">
                    <i class="fas fa-user-tie fa-3x mb-3"></i>
                    <h4>Employee Login</h4>
                    <p class="mb-0">Get instant access to your dashboard</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($authError): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($authError); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <div id="messagesContainer"></div>
                    
                    <!-- Email Form -->
                    <form id="employeeLoginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your employee email">
                            <div class="form-text">We'll send a secure login link to your email</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="sendLinkBtn">
                            <span class="btn-text">
                                <i class="fas fa-paper-plane me-2"></i>Send Login Link
                            </span>
                            <span class="btn-loading">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Sending...
                            </span>
                        </button>
                    </form>
                    
                    <!-- Success Message (hidden initially) -->
                    <div id="successMessage" style="display: none;">
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h5>Login Link Sent!</h5>
                            <p class="text-muted">
                                Check your email <strong id="sentEmail"></strong> for the login link.
                                <br>The link will expire in 30 minutes.
                            </p>
                            <button type="button" class="btn btn-link" id="sendAnotherBtn">
                                Send to different email
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="/" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById("employeeLoginForm");
    const sendLinkBtn = document.getElementById("sendLinkBtn");
    const sendAnotherBtn = document.getElementById("sendAnotherBtn");
    const messagesContainer = document.getElementById("messagesContainer");
    const successMessage = document.getElementById("successMessage");
    const sentEmail = document.getElementById("sentEmail");
    
    // Handle form submission
    loginForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const btnText = sendLinkBtn.querySelector(".btn-text");
        const btnLoading = sendLinkBtn.querySelector(".btn-loading");
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        sendLinkBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(loginForm);
        const email = formData.get("email");
        
        try {
            // Use direct endpoint for now
            const apiUrl = "/api-employee-send-link.php";
            console.log("Sending login link request to:", apiUrl);
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ email: email })
            });
            
            const result = await response.json();
            console.log("Response:", result);
            
            if (response.ok && result.success) {
                // Show success message
                loginForm.style.display = "none";
                successMessage.style.display = "block";
                sentEmail.textContent = email;
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${result.message || "Failed to send login link"}
                    </div>
                `;
            }
        } catch (error) {
            console.error("Error:", error);
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            sendLinkBtn.disabled = false;
        }
    });
    
    // Handle send another
    sendAnotherBtn.addEventListener("click", function() {
        loginForm.style.display = "block";
        successMessage.style.display = "none";
        messagesContainer.innerHTML = "";
        document.getElementById("email").value = "";
        document.getElementById("email").focus();
    });
});
</script>
</body>
</html>