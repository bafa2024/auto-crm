<?php
// Authentication page for magic link login
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: /employee/login");
    exit();
}

// Verify token
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/AuthToken.php";
require_once __DIR__ . "/../../models/User.php";

$database = new Database();
$db = $database->getConnection();

$authTokenModel = new AuthToken($db);
$email = $authTokenModel->verifyToken($token);

if (!$email) {
    // Invalid or expired token
    $_SESSION['auth_error'] = "Invalid or expired login link. Please request a new one.";
    header("Location: /employee/login");
    exit();
}

// Get user details
$userModel = new User($db);
$user = $userModel->findBy("email", $email);

if (!$user || !in_array($user["role"], ['agent', 'manager']) || $user["status"] !== "active") {
    $_SESSION['auth_error'] = "Account not found or inactive.";
    header("Location: /employee/login");
    exit();
}

// Create session
$_SESSION["user_id"] = $user["id"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
$_SESSION["user_role"] = $user["role"];
$_SESSION["login_time"] = time();
$_SESSION["login_method"] = "magic_link";

// Redirect to email dashboard for employees
header("Location: /employee/email-dashboard");
exit();