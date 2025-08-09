<?php
// create_admin.php - Create/Update Admin Account for Local Testing

echo "Creating Admin Account for AutoDial Pro\n";
echo "======================================\n\n";

try {
    // Load database connection
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "1. Checking existing admin accounts...\n";
    
    // Check for existing admin accounts
    $stmt = $db->prepare("SELECT id, email, first_name, last_name FROM users WHERE email LIKE '%admin%' OR email = ?");
    $stmt->execute(['admin@autocrm.com']);
    $existingAdmins = $stmt->fetchAll();
    
    if (count($existingAdmins) > 0) {
        echo "Found existing admin accounts:\n";
        foreach ($existingAdmins as $admin) {
            echo "- ID: {$admin['id']}, Email: {$admin['email']}, Name: {$admin['first_name']} {$admin['last_name']}\n";
        }
    }
    
    echo "\n2. Creating/updating admin account...\n";
    
    // Admin account details
    $adminEmail = 'admin@autocrm.com';
    $adminPassword = 'admin123';
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    // Check if admin already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        // Update existing admin
        $stmt = $db->prepare("
            UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                password = ?, 
                company_name = ?, 
                phone = ?,
                role = ?,
                updated_at = CURRENT_TIMESTAMP,
                status = 'active'
            WHERE email = ?
        ");
        $stmt->execute([
            'Admin',
            'User',
            $hashedPassword,
            'AutoDial Pro',
            '+1-555-0100',
            'admin',
            $adminEmail
        ]);
        echo "✓ Updated existing admin account\n";
        $adminId = $existingAdmin['id'];
    } else {
        // Create new admin
        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, password, company_name, phone, role, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            'Admin',
            'User',
            $adminEmail,
            $hashedPassword,
            'AutoDial Pro',
            '+1-555-0100',
            'admin'
        ]);
        echo "✓ Created new admin account\n";
        $adminId = $db->lastInsertId();
    }
    
    echo "\n3. Testing admin login...\n";
    
    // Test login with the credentials
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, company_name FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Verify password
        $stmt = $db->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        $storedPassword = $stmt->fetchColumn();
        
        if (password_verify($adminPassword, $storedPassword)) {
            echo "✓ Admin login credentials verified\n";
            echo "✓ Admin account details:\n";
            echo "  - ID: {$admin['id']}\n";
            echo "  - Name: {$admin['first_name']} {$admin['last_name']}\n";
            echo "  - Email: {$admin['email']}\n";
            echo "  - Company: {$admin['company_name']}\n";
        } else {
            throw new Exception("Password verification failed");
        }
    } else {
        throw new Exception("Admin account not found after creation");
    }
    
    echo "\n4. Creating additional test user...\n";
    
    // Create a second test user
    $testEmail = 'test@autocrm.com';
    $testPassword = 'test123';
    $testHashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $existingTest = $stmt->fetch();
    
    if (!$existingTest) {
        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, password, company_name, phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            'Test',
            'User',
            $testEmail,
            $testHashedPassword,
            'Test Company',
            '+1-555-0200'
        ]);
        echo "✓ Created test user account\n";
    } else {
        echo "✓ Test user already exists\n";
    }
    
    echo "\n5. Setting up admin permissions (future enhancement)...\n";
    // Note: In a full system, you'd add role-based permissions here
    echo "✓ Admin has full system access\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ ADMIN ACCOUNT READY FOR TESTING\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🔐 LOGIN CREDENTIALS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👤 Admin Account:\n";
    echo "   Email:    admin@autocrm.com\n";
    echo "   Password: admin123\n\n";
    echo "👤 Test Account:\n";
    echo "   Email:    test@autocrm.com\n";
    echo "   Password: test123\n\n";
    
    echo "🌐 ACCESS URLS:\n";
    echo "━━━━━━━━━━━━━━━\n";
    echo "Dashboard:  http://localhost/acrm/dashboard\n";
    echo "Login:      http://localhost/acrm/login\n";
    echo "Landing:    http://localhost/acrm/\n\n";
    
    echo "📋 TESTING STEPS:\n";
    echo "━━━━━━━━━━━━━━━━━\n";
    echo "1. Open: http://localhost/acrm/login\n";
    echo "2. Enter: admin@autocrm.com / admin123\n";
    echo "3. Click: Sign In\n";
    echo "4. You'll be redirected to the dashboard\n\n";
    
    echo "💡 TROUBLESHOOTING:\n";
    echo "━━━━━━━━━━━━━━━━━━━\n";
    echo "- Make sure XAMPP is running\n";
    echo "- Verify the URL path matches your setup\n";
    echo "- Check browser console for any errors\n";
    echo "- Database is using SQLite (no MySQL needed)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>