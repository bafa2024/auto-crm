<?php include __DIR__ . "/../components/header-landing.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-person-badge text-primary"></i> Employee Portal
                        </h3>
                        <h4 class="mt-3">Employee Login</h4>
                        <p class="text-muted">Sign in to your employee account</p>
                    </div>
                    
                    <div id="employeeLoginMessages"></div>
                    
                    <form id="employeeLoginForm">
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
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="employeeLoginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Signing In...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Admin login? <a href="/login">Click here</a></p>
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
    
    const employeeLoginForm = document.getElementById("employeeLoginForm");
    const employeeLoginBtn = document.getElementById("employeeLoginBtn");
    const btnText = employeeLoginBtn.querySelector(".btn-text");
    const btnLoading = employeeLoginBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("employeeLoginMessages");
    
    employeeLoginForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        employeeLoginBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(employeeLoginForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const apiUrl = basePath + "/api/auth/employee-login";
            
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
                        Login successful! Redirecting to employee dashboard...
                    </div>
                `;
                setTimeout(() => {
                    const dashboardUrl = basePath + "/employee/dashboard";
                    window.location.href = dashboardUrl;
                }, 1000);
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Invalid email or password"}
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
            employeeLoginBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 