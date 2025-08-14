<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<?php include __DIR__ . "/../components/header-landing.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
                        </h3>
                        <h4 class="mt-3">Create Your Account</h4>
                        <p class="text-muted">Start your 14-day free trial</p>
                    </div>
                    
                    <div id="signupMessages"></div>
                    
                    <form id="signupForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Work Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Must be at least 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="signupBtn">
                            <span class="btn-text">Create Account</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Creating Account...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="<?php echo base_path('login'); ?>">Sign in</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Use PHP base path
    const basePath = '<?php echo base_path(""); ?>';
    
    const signupForm = document.getElementById("signupForm");
    const signupBtn = document.getElementById("signupBtn");
    const btnText = signupBtn.querySelector(".btn-text");
    const btnLoading = signupBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("signupMessages");
    
    signupForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        // Basic validation
        const password = signupForm.querySelector('input[name="password"]').value;
        const confirmPassword = signupForm.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Passwords do not match
                </div>
            `;
            return;
        }
        
        if (password.length < 6) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Password must be at least 6 characters long
                </div>
            `;
            return;
        }
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        signupBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(signupForm);
        const data = Object.fromEntries(formData.entries());
        
        // Remove confirm_password from data
        delete data.confirm_password;
        
        // Add default role
        data.role = 'admin';
        
        // Debug: Log the data being sent
        console.log("Sending signup data:", data);
        console.log("Base path:", basePath);
        console.log("API URL:", basePath + "/api/auth/register");
        
        try {
            const response = await fetch(basePath + "/api/auth/register", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            console.log("Response status:", response.status);
            
            const result = await response.json();
            console.log("Response data:", result);
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Account created successfully! Redirecting to login...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = basePath + "/login";
                }, 2000);
            } else {
                let errorMessage = "Failed to create account";
                
                if (result.message) {
                    errorMessage = result.message;
                } else if (result.error) {
                    errorMessage = result.error;
                }
                
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${errorMessage}
                    </div>
                `;
            }
        } catch (error) {
            console.error("Signup error:", error);
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            signupBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>