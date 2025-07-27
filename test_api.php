<?php
// Test API endpoint directly
session_start();

// Set admin session for testing
$_SESSION["user_id"] = 1;
$_SESSION["user_role"] = "admin";

// Include required files
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/controllers/UserController.php";

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Create controller instance
$controller = new UserController($db);

// Mock the request
$_GET["q"] = "";

echo "<h2>Direct API Test</h2>";
echo "<pre>";

// Call the method directly
ob_start();
$controller->listEmployees();
$output = ob_get_clean();

echo "API Response:\n";
echo $output;

// Also fetch data directly
echo "\n\nDirect Database Query:\n";
$stmt = $db->query("SELECT id, first_name, last_name, email, role, status FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();
echo json_encode(["success" => true, "data" => $users], JSON_PRETTY_PRINT);

echo "</pre>";
?>
