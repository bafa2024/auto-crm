<?php include __DIR__ . "/../components/header-landing.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
                        </h3>
                        <h4 class="mt-3">Forgot Password?</h4>
                        <p class="text-muted">Enter your email to receive a password reset link</p>
                    </div>
                    
                    <div id="forgotPasswordMessages"></div>
                    
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                            <span class="btn-text">Send Reset Link</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Sending...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Remember your password? <a href="/login">Sign in</a></p>
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
    
    const forgotPasswordForm = document.getElementById("forgotPasswordForm");
    const submitBtn = document.getElementById("submitBtn");
    const btnText = submitBtn.querySelector(".btn-text");
    const btnLoading = submitBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("forgotPasswordMessages");
    
    forgotPasswordForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        submitBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(forgotPasswordForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const apiUrl = basePath + "/api/auth/forgot-password";
            
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
                        <i class="bi bi-check-circle me-2"></i>
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
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${result.message || "Failed to send reset link"}
                    </div>
                `;
            }
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            submitBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 