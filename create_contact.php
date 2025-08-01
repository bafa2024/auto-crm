<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    $dot = $_POST['dot'] ?? '';
    $companyName = $_POST['company_name'] ?? '';
    $customerName = $_POST['customer_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $campaignId = $_POST['campaign_id'] ?? null;
    
    try {
        $database = (new Database())->getConnection();
        
        // Validate required fields
        if (empty($email) || empty($customerName)) {
            throw new Exception('Email and Customer Name are required.');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Normalize email to lowercase for case-insensitive comparison
        $normalizedEmail = strtolower(trim($email));
        
        // Check if email already exists (case-insensitive)
        $stmt = $database->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
        $stmt->execute([$normalizedEmail]);
        if ($stmt->fetch()) {
            throw new Exception('A contact with this email address already exists.');
        }
        
        // Handle campaign_id - convert empty string to NULL
        if (empty($campaignId) || $campaignId === '' || $campaignId === '0') {
            $campaignId = null;
        }
        
        // Insert new contact with normalized email
        $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $database->prepare($sql);
        $stmt->execute([$normalizedEmail, $customerName, $companyName, $dot, $campaignId]);
        
        // Redirect with success message
        $_SESSION['message'] = 'Contact created successfully!';
        $_SESSION['messageType'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['messageType'] = 'danger';
    }
    
    header("Location: contacts.php");
    exit;
}

// If not POST, redirect to contacts
header("Location: contacts.php");
exit;