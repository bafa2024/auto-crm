<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'config/database.php';
require_once 'controllers/BaseController.php';
require_once 'controllers/EmailRecipientController.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $db = (new Database())->getConnection();
    $controller = new EmailRecipientController($db);
    
    // Override REQUEST_METHOD for the controller
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    
    // Call the delete method
    $controller->deleteRecipient($_POST['id']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}