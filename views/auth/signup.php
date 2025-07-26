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
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted">Must be at least 8 characters</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="signupBtn">
                            <span class="btn-text">Create Account</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Creating Account...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="#" id="loginLink">Sign in</a></p>
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
    
    // Set login link href
    document.getElementById('loginLink').href = basePath + '/login';
    
    const signupForm = document.getElementById("signupForm");
    const signupBtn = document.getElementById("signupBtn");
    const btnText = signupBtn.querySelector(".btn-text");
    const btnLoading = signupBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("signupMessages");
    
    signupForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        signupBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(signupForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(basePath + "/api/auth/register", {
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
                        Account created successfully! Redirecting to login...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = basePath + "/login";
                }, 2000);
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Failed to create account"}
                    </div>
                `;
            }
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
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