<?php include __DIR__ . '/../components/header.php'; ?>
<div class="auth-container" style="margin-top: 80px; padding-top: 20px;">
    <div class="auth-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold">
                <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
            </h3>
            <h4 class="mt-3">Create Your Account</h4>
            <p class="text-secondary">Start your 14-day free trial</p>
        </div>
        
        <!-- Error/Success Messages -->
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
                <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Work Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required minlength="8">
                <small class="text-secondary">Must be at least 8 characters</small>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3" id="signupBtn">
                <span class="btn-text">Create Account</span>
                <span class="btn-loading d-none">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Creating Account...
                </span>
            </button>
            <div class="text-center">
                <p class="mb-0">Already have an account? <a href="/login">Sign in</a></p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const signupForm = document.getElementById('signupForm');
    const signupBtn = document.getElementById('signupBtn');
    const btnText = signupBtn.querySelector('.btn-text');
    const btnLoading = signupBtn.querySelector('.btn-loading');
    const messagesContainer = document.getElementById('signupMessages');
    
    signupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading state
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        signupBtn.disabled = true;
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Get form data
        const formData = new FormData(signupForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            // Try the main API endpoint first
            let response = await fetch('/api/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            // If that fails, try the fallback endpoint
            if (!response.ok && response.status !== 409) {
                response = await fetch('/api.php/api/auth/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
            }
            
            const result = await response.json();
            
            if (response.ok) {
                // Success - show success message and redirect
                showMessage('Account created successfully! Redirecting to login...', 'success');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
            } else {
                // Error - show error message
                const errorMessage = result.error || result.message || 'Failed to create account. Please try again.';
                showMessage(errorMessage, 'error');
            }
        } catch (error) {
            console.error('Signup error:', error);
            showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            // Reset button state
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            signupBtn.disabled = false;
        }
    });
    
    function showMessage(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
        
        messagesContainer.innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?> 