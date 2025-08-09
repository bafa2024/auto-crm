<?php
require_once __DIR__ . '/config/base_path.php';
require_once __DIR__ . '/config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$messageType = '';

// Check if user is already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: " . base_path('dashboard'));
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'signup') {
            // Handle Signup
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                $message = 'All fields are required.';
                $messageType = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
                $messageType = 'danger';
            } elseif (strlen($password) < 6) {
                $message = 'Password must be at least 6 characters long.';
                $messageType = 'danger';
            } elseif ($password !== $confirmPassword) {
                $message = 'Passwords do not match.';
                $messageType = 'danger';
            } else {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $message = 'An account with this email already exists.';
                    $messageType = 'danger';
                } else {
                    // Create new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (first_name, last_name, email, password, role, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, 'admin', 'active', NOW(), NOW())
                    ");
                    
                    if ($stmt->execute([$firstName, $lastName, $email, $hashedPassword])) {
                        $message = 'Account created successfully! You can now login.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error creating account. Please try again.';
                        $messageType = 'danger';
                    }
                }
            }
            
        } elseif ($_POST['action'] === 'login') {
            // Handle Login
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $message = 'Email and password are required.';
                $messageType = 'danger';
            } else {
                // Authenticate user
                $stmt = $db->prepare("SELECT id, first_name, last_name, email, password, role, status FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
                    $_SESSION["user_role"] = $user["role"];
                    $_SESSION["login_time"] = time();
                    
                    header("Location: " . base_path('dashboard'));
                    exit;
                } else {
                    $message = 'Invalid email or password.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Determine which form to show
$showSignup = isset($_GET['signup']) || (isset($_POST['action']) && $_POST['action'] === 'signup' && $messageType === 'danger');
?>
<?php include __DIR__ . "/views/components/header-landing.php"; ?>
<?php include __DIR__ . "/views/components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
                        </h3>
                        <h4 class="mt-3"><?php echo $showSignup ? 'Create Admin Account' : 'Welcome Back'; ?></h4>
                        <p class="text-muted"><?php echo $showSignup ? 'Sign up for admin access' : 'Sign in to your account'; ?></p>
                    </div>
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> mb-4">
                            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($showSignup): ?>
                        <!-- Signup Form -->
                        <form method="POST">
                            <input type="hidden" name="action" value="signup">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-plus me-2"></i>
                                Create Admin Account
                            </button>
                            
                            <div class="text-center">
                                <p class="mb-0">Already have an account? 
                                    <a href="<?php echo base_path('simple-auth'); ?>">Sign in here</a>
                                </p>
                                <p class="mb-0">Employee login? 
                                    <a href="<?php echo base_path('employee/login'); ?>">Click here</a>
                                </p>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <!-- Login Form -->
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
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
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Sign In
                            </button>
                            
                            <div class="text-center">
                                <p class="mb-0">Need an admin account? 
                                    <a href="<?php echo base_path('simple-auth?signup=1'); ?>">Sign up here</a>
                                </p>
                                <p class="mb-0">Employee login? 
                                    <a href="<?php echo base_path('employee/login'); ?>">Click here</a>
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Demo Section -->
<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quick Demo</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>For testing purposes:</strong></p>
                    <ul class="mb-3">
                        <li>Create an admin account using any email and password</li>
                        <li>All new accounts are automatically set as admin role</li>
                        <li>You'll be redirected to the dashboard after successful login</li>
                    </ul>
                    
                    <?php if (!$showSignup): ?>
                        <div class="d-grid gap-2">
                            <a href="<?php echo base_path('simple-auth?signup=1'); ?>" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-person-plus me-2"></i>Create New Admin Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.card-body {
    padding: 2rem;
}

.form-control {
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn {
    border-radius: 10px;
    padding: 12px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.alert {
    border-radius: 10px;
    border: none;
}

.form-text {
    font-size: 0.85rem;
    color: #6c757d;
}

a {
    color: #007bff;
    text-decoration: none;
    transition: color 0.3s ease;
}

a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.card.border-info {
    border: 1px solid #b8daff !important;
}

.bg-info {
    background-color: #17a2b8 !important;
}
</style>

<?php include __DIR__ . "/views/components/footer.php"; ?>
