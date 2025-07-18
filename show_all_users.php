<?php
// show_all_users.php - Display all user accounts

echo "All User Accounts in AutoDial Pro\n";
echo "=================================\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("SELECT id, first_name, last_name, email, role, company_name, created_at, status FROM users ORDER BY created_at");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "Total Users: " . count($users) . "\n\n";
        
        foreach ($users as $user) {
            echo "User ID: " . $user['id'] . "\n";
            echo "Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Role: " . ($user['role'] ?? 'not set') . "\n";
            echo "Company: " . ($user['company_name'] ?? 'not set') . "\n";
            echo "Status: " . $user['status'] . "\n";
            echo "Created: " . $user['created_at'] . "\n";
            echo str_repeat("-", 40) . "\n";
        }
        
        echo "\nYou can login with any of these accounts:\n";
        echo "1. Admin account: admin@autocrm.com / admin123\n";
        echo "2. Test account: test@autocrm.com / test123\n";
        echo "3. Or create your own account at: http://localhost/acrm/signup\n";
        
    } else {
        echo "No users found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>