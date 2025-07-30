<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold">
                <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
            </h3>
            <h4 class="mt-3">Welcome Back</h4>
            <p class="text-secondary">Sign in to your account</p>
        </div>
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
                <a href="#">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
            <div class="text-center">
                <p class="mb-0">Don't have an account? <a href="<?php echo base_path('signup'); ?>">Sign up</a></p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const basePath = '<?php echo base_path(""); ?>';
    const loginForm = document.getElementById("loginForm");
    
    loginForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const formData = new FormData(loginForm);
        const data = {
            email: formData.get('email'),
            password: formData.get('password')
        };
        
        try {
            const response = await fetch(basePath + "/api/auth/login", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert('Login successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = basePath + "/dashboard";
                }, 1000);
            } else {
                alert(result.message || result.error || 'Invalid email or password');
            }
        } catch (error) {
            console.error("Login error:", error);
            alert('Network error. Please try again.');
        }
    });
});
</script> 