<?php
/**
 * Fix Email Case Sensitivity
 * 
 * This script normalizes all email addresses in the database to lowercase
 * to ensure case-insensitive email handling for campaigns.
 */

require_once 'config/database.php';
require_once 'autoload.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Starting email case sensitivity fix...\n";
    
    // Get all email recipients
    $stmt = $db->prepare("SELECT id, email FROM email_recipients");
    $stmt->execute();
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    $errors = [];
    
    foreach ($recipients as $recipient) {
        $originalEmail = $recipient['email'];
        $normalizedEmail = strtolower(trim($originalEmail));
        
        // Only update if the email needs normalization
        if ($originalEmail !== $normalizedEmail) {
            try {
                $updateStmt = $db->prepare("UPDATE email_recipients SET email = ? WHERE id = ?");
                $result = $updateStmt->execute([$normalizedEmail, $recipient['id']]);
                
                if ($result) {
                    $updated++;
                    echo "Updated: {$originalEmail} -> {$normalizedEmail}\n";
                } else {
                    $errors[] = "Failed to update email {$originalEmail}";
                }
            } catch (Exception $e) {
                $errors[] = "Error updating email {$originalEmail}: " . $e->getMessage();
            }
        }
    }
    
    // Also normalize user emails
    $stmt = $db->prepare("SELECT id, email FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $originalEmail = $user['email'];
        $normalizedEmail = strtolower(trim($originalEmail));
        
        if ($originalEmail !== $normalizedEmail) {
            try {
                $updateStmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                $result = $updateStmt->execute([$normalizedEmail, $user['id']]);
                
                if ($result) {
                    $updated++;
                    echo "Updated user email: {$originalEmail} -> {$normalizedEmail}\n";
                } else {
                    $errors[] = "Failed to update user email {$originalEmail}";
                }
            } catch (Exception $e) {
                $errors[] = "Error updating user email {$originalEmail}: " . $e->getMessage();
            }
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total emails updated: {$updated}\n";
    
    if (!empty($errors)) {
        echo "Errors encountered:\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    } else {
        echo "All emails normalized successfully!\n";
    }
    
    echo "\nEmail case sensitivity fix completed.\n";
    echo "All future email uploads and contacts will be automatically normalized to lowercase.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 