<?php include __DIR__ . '/../components/header.php'; ?>
<div class="auth-container" style="margin-top: 80px; padding-top: 20px;">
    <div class="auth-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold">
                <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
            </h3>
            <h4 class="mt-3">Welcome Back</h4>
            <p class="text-secondary">Sign in to your account</p>
        </div>
        
        <!-- Error/Success Messages -->
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
                <a href="#">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
                <span class="btn-text">Sign In</span>
                <span class="btn-loading d-none">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Signing In...
                </span>
            </button>
            <div class="text-center">
                <p class="mb-0">Don't have an account? <a href="/signup">Sign up</a></p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const btnText = loginBtn.querySelector('.btn-text');
    const btnLoading = loginBtn.querySelector('.btn-loading');
    const messagesContainer = document.getElementById('loginMessages');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading state
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        loginBtn.disabled = true;
        
        // Clear previous messages
        messagesContainer.innerHTML = '';
        
        // Get form data
        const formData = new FormData(loginForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            // Try the main API endpoint first
            let response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            // If that fails, try the fallback endpoint
            if (!response.ok) {
                response = await fetch('/api.php/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
            }
            
            const result = await response.json();
            
            if (response.ok) {
                // Success - redirect to dashboard
                showMessage('Login successful! Redirecting to dashboard...', 'success');
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 1000);
            } else {
                // Error - show error message
                const errorMessage = result.error || result.message || 'Invalid email or password. Please try again.';
                showMessage(errorMessage, 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            showMessage('Network error. Please check your connection and try again.', 'error');
        } finally {
            // Reset button state
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            loginBtn.disabled = false;
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