<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION["user_id"]) && in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/dashboard'));
    exit();
}
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
        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
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
                    <p class="mb-0">Access your employee dashboard</p>
                </div>
                <div class="card-body p-4">
                    <div id="messagesContainer"></div>
                    
                    <!-- Email Form -->
                    <form id="employeeEmailForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your email">
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="sendOtpBtn">
                            <span class="btn-text">Send OTP</span>
                            <span class="btn-loading">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Sending...
                            </span>
                        </button>
                    </form>
                    
                    <!-- OTP Form (hidden initially) -->
                    <form id="employeeOtpForm" style="display: none;">
                        <div class="mb-3">
                            <label for="otp" class="form-label">Enter OTP</label>
                            <input type="text" class="form-control otp-input" id="otp" name="otp" 
                                   maxlength="6" pattern="[0-9]{6}" required 
                                   placeholder="000000">
                            <div class="form-text">Enter the 6-digit code sent to <span id="sentToEmail"></span></div>
                        </div>
                        <button type="submit" class="btn btn-success w-100" id="verifyOtpBtn">
                            <span class="btn-text">Verify & Login</span>
                            <span class="btn-loading">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Verifying...
                            </span>
                        </button>
                        <button type="button" class="btn btn-link w-100" id="changeEmailBtn">
                            Use different email
                        </button>
                    </form>
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
    // Use direct endpoints
    const apiBasePath = window.location.protocol + '//' + window.location.host;
    
    // Form elements
    const emailForm = document.getElementById("employeeEmailForm");
    const otpForm = document.getElementById("employeeOtpForm");
    const sendOtpBtn = document.getElementById("sendOtpBtn");
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");
    const changeEmailBtn = document.getElementById("changeEmailBtn");
    const messagesContainer = document.getElementById("messagesContainer");
    const sentToEmail = document.getElementById("sentToEmail");
    
    let currentEmail = "";
    
    // Handle Send OTP
    emailForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const btnText = sendOtpBtn.querySelector(".btn-text");
        const btnLoading = sendOtpBtn.querySelector(".btn-loading");
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        sendOtpBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(emailForm);
        currentEmail = formData.get("email");
        
        try {
            // Use direct endpoint
            const apiUrl = apiBasePath + "/api-employee-send-otp.php";
            console.log("Sending OTP request to:", apiUrl);
            console.log("Email:", currentEmail);
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ email: currentEmail })
            });
            
            console.log("Response status:", response.status);
            const result = await response.json();
            console.log("Response data:", result);
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        ${result.message || "OTP sent successfully"}
                    </div>
                `;
                
                // Show OTP form
                emailForm.style.display = "none";
                otpForm.style.display = "block";
                sentToEmail.textContent = currentEmail;
                document.getElementById("otp").focus();
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Failed to send OTP"}
                    </div>
                `;
            }
        } catch (error) {
            console.error("OTP error:", error);
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            sendOtpBtn.disabled = false;
        }
    });
    
    // Handle OTP Verification
    otpForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const btnText = verifyOtpBtn.querySelector(".btn-text");
        const btnLoading = verifyOtpBtn.querySelector(".btn-loading");
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        verifyOtpBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(otpForm);
        const otp = formData.get("otp");
        
        try {
            // Use direct endpoint
            const apiUrl = apiBasePath + "/api-employee-verify-otp.php";
            console.log("Verifying OTP at:", apiUrl);
            console.log("Email:", currentEmail, "OTP:", otp);
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ 
                    email: currentEmail,
                    otp: otp 
                })
            });
            
            console.log("Response status:", response.status);
            const result = await response.json();
            console.log("Response data:", result);
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ${result.message || "Login successful"}
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = basePath + "/employee/dashboard";
                }, 1000);
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Invalid OTP"}
                    </div>
                `;
            }
        } catch (error) {
            console.error("OTP error:", error);
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            verifyOtpBtn.disabled = false;
        }
    });
    
    // Handle change email
    changeEmailBtn.addEventListener("click", function() {
        emailForm.style.display = "block";
        otpForm.style.display = "none";
        messagesContainer.innerHTML = "";
        document.getElementById("otp").value = "";
    });
});
</script>
</body>
</html>