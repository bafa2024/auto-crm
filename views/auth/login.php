<?php include __DIR__ . "/../components/header.php"; ?>
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
                            <a href="#">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Signing In...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="/signup">Sign up</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
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
        
        try {
            const response = await fetch("/api/auth/login", {
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
                        Login successful! Redirecting...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = "/dashboard";
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
            loginBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>