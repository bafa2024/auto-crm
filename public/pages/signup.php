<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold">
                <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
            </h3>
            <h4 class="mt-3">Create Account</h4>
            <p class="text-secondary">Join thousands of sales professionals</p>
        </div>
        <form id="signupForm">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
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
            <button type="submit" class="btn btn-primary w-100 mb-3">Create Account</button>
            <div class="text-center">
                <p class="mb-0">Already have an account? <a href="<?php echo base_path('login'); ?>">Sign in</a></p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const basePath = '<?php echo base_path(""); ?>';
    const signupForm = document.getElementById("signupForm");
    
    signupForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        const formData = new FormData(signupForm);
        const fullName = formData.get('full_name').split(' ');
        
        const data = {
            first_name: fullName[0] || '',
            last_name: fullName.slice(1).join(' ') || '',
            email: formData.get('email'),
            password: formData.get('password'),
            role: 'admin'
        };
        
        // Validate passwords match
        if (formData.get('password') !== formData.get('confirm_password')) {
            alert('Passwords do not match');
            return;
        }
        
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
                alert('Account created successfully! Redirecting to login...');
                setTimeout(() => {
                    window.location.href = basePath + "/login";
                }, 2000);
            } else {
                alert(result.message || result.error || 'Failed to create account');
            }
        } catch (error) {
            console.error("Signup error:", error);
            alert('Network error. Please try again.');
        }
    });
});
</script> 