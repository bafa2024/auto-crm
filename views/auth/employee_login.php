<?php include __DIR__ . "/../components/header-landing.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-person-badge text-primary"></i> Employee Portal
                        </h3>
                        <h4 class="mt-3">Employee Login</h4>
                        <p class="text-muted">Sign in to your employee account</p>
                    </div>
                    
                    <div id="employeeLoginMessages"></div>
                    
                    <!-- Step 1: Email Form -->
                    <form id="employeeEmailForm">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            <div class="form-text">We'll send a One-Time Password (OTP) to your email.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="sendOtpBtn">
                            <span class="btn-text">Send OTP</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Sending OTP...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Admin login? <a href="/login">Click here</a></p>
                        </div>
                    </form>
                    
                    <!-- Step 2: OTP Verification Form (Initially Hidden) -->
                    <form id="employeeOtpForm" class="d-none">
                        <div class="text-center mb-3">
                            <p class="text-muted">OTP sent to: <strong id="sentToEmail"></strong></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Enter OTP</label>
                            <input type="text" name="otp" class="form-control text-center" maxlength="6" placeholder="000000" required style="font-size: 24px; letter-spacing: 8px;">
                            <div class="form-text">Enter the 6-digit code sent to your email.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="verifyOtpBtn">
                            <span class="btn-text">Verify & Login</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Verifying...
                            </span>
                        </button>
                        <button type="button" class="btn btn-link w-100" id="backToEmailBtn">
                            <i class="bi bi-arrow-left"></i> Back to Email
                        </button>
                        <div class="text-center mt-3">
                            <p class="mb-0 text-muted">Didn't receive OTP? <a href="#" id="resendOtpBtn">Resend</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Auto-detect base path for live hosting compatibility
    const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
    
    // Form elements
    const emailForm = document.getElementById("employeeEmailForm");
    const otpForm = document.getElementById("employeeOtpForm");
    const sendOtpBtn = document.getElementById("sendOtpBtn");
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");
    const backToEmailBtn = document.getElementById("backToEmailBtn");
    const resendOtpBtn = document.getElementById("resendOtpBtn");
    const messagesContainer = document.getElementById("employeeLoginMessages");
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
            const apiUrl = (basePath ? basePath : '') + "/api/auth/employee-send-otp";
            console.log("Sending OTP request to:", apiUrl);
            console.log("Email:", currentEmail);
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ email: currentEmail })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        OTP sent successfully! Check your email.
                    </div>
                `;
                
                // Show OTP form
                sentToEmail.textContent = currentEmail;
                emailForm.classList.add("d-none");
                otpForm.classList.remove("d-none");
                
                // Focus on OTP input
                otpForm.querySelector('input[name="otp"]').focus();
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
            const apiUrl = (basePath ? basePath : '') + "/api/auth/employee-verify-otp";
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
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        Login successful! Redirecting to employee dashboard...
                    </div>
                `;
                setTimeout(() => {
                    const dashboardUrl = basePath + "/employee/dashboard";
                    window.location.href = dashboardUrl;
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
    
    // Handle Back to Email
    backToEmailBtn.addEventListener("click", function() {
        otpForm.classList.add("d-none");
        emailForm.classList.remove("d-none");
        messagesContainer.innerHTML = "";
    });
    
    // Handle Resend OTP
    resendOtpBtn.addEventListener("click", async function(e) {
        e.preventDefault();
        
        messagesContainer.innerHTML = `
            <div class="alert alert-info">
                Resending OTP...
            </div>
        `;
        
        try {
            const apiUrl = (basePath ? basePath : '') + "/api/auth/employee-send-otp";
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ email: currentEmail })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        New OTP sent successfully!
                    </div>
                `;
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Failed to resend OTP"}
                    </div>
                `;
            }
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    Network error. Please try again.
                </div>
            `;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 