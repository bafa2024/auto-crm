<?php include __DIR__ . '/../components/header.php'; ?>
<div class="auth-container">
    <div class="auth-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold">
                <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
            </h3>
            <h4 class="mt-3">Create Your Account</h4>
            <p class="text-secondary">Start your 14-day free trial</p>
        </div>
        <form id="signupForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Company Name</label>
                <input type="text" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Work Email</label>
                <input type="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" required>
                <small class="text-secondary">Must be at least 8 characters</small>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Create Account</button>
            <div class="text-center">
                <p class="mb-0">Already have an account? <a href="/login">Sign in</a></p>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../components/footer.php'; ?> 