<?php
// Get token from URL
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header("Location: /login");
    exit();
}
?>
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
                        <h4 class="mt-3">Reset Your Password</h4>
                        <p class="text-muted">Enter your new password below</p>
                    </div>
                    
                    <div id="resetPasswordMessages"></div>
                    
                    <form id="resetPasswordForm">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                            <span class="btn-text">Reset Password</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Resetting...
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
    
    const resetPasswordForm = document.getElementById("resetPasswordForm");
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
            const response = await fetch(`${basePath}/api/auth/validate-reset-token?token=${token}`, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                }
            });
            
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Invalid or expired reset token. Please request a new password reset link.
                    </div>
                `;
                resetPasswordForm.style.display = 'none';
                return false;
            }
            
            // Show user info
            messagesContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Resetting password for: <strong>${result.data.email}</strong>
                </div>
            `;
            return true;
            
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
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
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Passwords do not match.
                </div>
            `;
            return;
        }
        
        if (password.length < 6) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Password must be at least 6 characters long.
                </div>
            `;
            return;
        }
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        submitBtn.disabled = true;
        
        const formData = new FormData(resetPasswordForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const apiUrl = basePath + "/api/auth/reset-password";
            
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
                        ${result.message || "Password has been reset successfully"}
                    </div>
                `;
                
                // Clear form
                resetPasswordForm.reset();
                
                // Redirect to login after 3 seconds
                setTimeout(() => {
                    window.location.href = basePath + "/login";
                }, 3000);
                
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${result.message || "Failed to reset password"}
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