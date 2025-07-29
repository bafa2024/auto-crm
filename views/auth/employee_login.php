<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION["user_id"]) && in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: /employee/email-dashboard");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    require_once __DIR__ . "/../../config/database.php";
    require_once __DIR__ . "/../../models/User.php";
    
    $database = new Database();
    $db = $database->getConnection();
    $userModel = new User($db);
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    // Authenticate user with email and password
    $user = $userModel->authenticate($email, $password);
    
    if ($user && in_array($user["role"], ['agent', 'manager'])) {
        // Create session
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["login_time"] = time();
        $_SESSION["login_method"] = "email_password";
        
        // Redirect to email dashboard
        header("Location: /employee/email-dashboard");
        exit();
    } else {
        $error = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 10px;
        }
        .login-header {
            background-color: #6c757d;
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle .form-control {
            padding-right: 40px;
        }
        .password-toggle .btn {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            border: none;
            background: transparent;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="login-header">
                    <i class="fas fa-user-tie fa-3x mb-3"></i>
                    <h4>Employee Login</h4>
                    <p class="mb-0">Access your email campaign dashboard</p>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Employee Email</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   placeholder="Enter your employee email" autofocus>
                            <div class="form-text">Enter your registered employee email address</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-toggle">
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Enter your password">
                                <button type="button" class="btn" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">Enter your employee password</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center text-muted">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            Only registered employees (agents/managers) can login
                        </small>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="/" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>