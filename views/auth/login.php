<?php
require_once __DIR__ . '/../../config/base_path.php';
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
                        <h4 class="mt-3">Welcome Back</h4>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <div id="loginMessages"></div>
                    
                    <form id="loginForm">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <a href="<?php echo base_path('forgot-password'); ?>">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Signing In...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Employee login? <a href="<?php echo base_path('employee/login'); ?>">Click here</a></p>
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
    
    const loginForm = document.getElementById("loginForm");
    const loginBtn = document.getElementById("loginBtn");
    const btnText = loginBtn.querySelector(".btn-text");
    const btnLoading = loginBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("loginMessages");
    
    loginForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        loginBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(loginForm);
        const data = Object.fromEntries(formData.entries());
        
        // Debug: Log the data being sent
        console.log("Sending login data:", data);
        console.log("API URL:", basePath + "/api/auth/login");
        
        try {
            const apiUrl = basePath + "/api/auth/login";
            
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            console.log("Response status:", response.status);
            console.log("Response headers:", response.headers);
            
            const result = await response.json();
            console.log("Response data:", result);
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Login successful! Redirecting...
                    </div>
                `;
                setTimeout(() => {
                    const dashboardUrl = basePath + "/dashboard";
                    window.location.href = dashboardUrl;
                }, 1000);
            } else {
                let errorMessage = "Invalid email or password";
                
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
            console.error("Login error:", error);
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            loginBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>