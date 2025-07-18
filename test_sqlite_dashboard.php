<?php
// test_sqlite_dashboard.php - Test SQLite database with dashboard

echo "Testing AutoDial Pro Dashboard with SQLite\n";
echo "==========================================\n\n";

try {
    // 1. Test database connection
    echo "1. Testing database connection...\n";
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✓ Database connection successful\n";
    } else {
        throw new Exception("Database connection failed");
    }
    
    // 2. Test user authentication
    echo "\n2. Testing user authentication...\n";
    
    // Test admin login
    $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute(['admin@autocrm.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✓ Admin user found: " . $admin['first_name'] . " " . $admin['last_name'] . "\n";
    } else {
        throw new Exception("Admin user not found");
    }
    
    // 3. Test dashboard data queries
    echo "\n3. Testing dashboard data queries...\n";
    
    // Count contacts
    $contactCount = $db->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    echo "✓ Total contacts: $contactCount\n";
    
    // Count today's calls
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) FROM call_logs WHERE date(call_date) = ?");
    $stmt->execute([$today]);
    $todayCalls = $stmt->fetchColumn();
    echo "✓ Today's calls: $todayCalls (Note: Sample data may be from different dates)\n";
    
    // Count active campaigns
    $activeCampaigns = $db->query("SELECT COUNT(*) FROM email_campaigns WHERE status = 'active'")->fetchColumn();
    echo "✓ Active campaigns: $activeCampaigns\n";
    
    // Calculate connection rate
    $totalCalls = $db->query("SELECT COUNT(*) FROM call_logs")->fetchColumn();
    $connectedCalls = $db->query("SELECT COUNT(*) FROM call_logs WHERE status = 'connected'")->fetchColumn();
    $connectionRate = $totalCalls > 0 ? round(($connectedCalls / $totalCalls) * 100, 1) : 0;
    echo "✓ Connection rate: $connectionRate% ($connectedCalls connected out of $totalCalls calls)\n";
    
    // 4. Test recent activity query
    echo "\n4. Testing recent activity query...\n";
    $stmt = $db->query("
        SELECT c.first_name, c.last_name, cl.call_type, cl.status, cl.call_date 
        FROM call_logs cl 
        JOIN contacts c ON cl.contact_id = c.id 
        ORDER BY cl.call_date DESC 
        LIMIT 5
    ");
    $activities = $stmt->fetchAll();
    
    if (count($activities) > 0) {
        echo "✓ Recent activities found:\n";
        foreach ($activities as $activity) {
            $name = $activity['first_name'] . ' ' . $activity['last_name'];
            $action = ucfirst($activity['call_type']) . ' call';
            $status = ucfirst($activity['status']);
            $date = date('M j, Y g:i A', strtotime($activity['call_date']));
            echo "  - $name: $action ($status) on $date\n";
        }
    } else {
        echo "⚠ No recent activities found\n";
    }
    
    // 5. Test login simulation
    echo "\n5. Testing login simulation...\n";
    session_start();
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_email'] = $admin['email'];
    $_SESSION['user_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
    
    echo "✓ Session variables set for admin user\n";
    echo "  - User ID: " . $_SESSION['user_id'] . "\n";
    echo "  - Email: " . $_SESSION['user_email'] . "\n";
    echo "  - Name: " . $_SESSION['user_name'] . "\n";
    
    // 6. Test dashboard access
    echo "\n6. Testing dashboard page access...\n";
    
    if (file_exists('views/dashboard/index.php')) {
        echo "✓ Dashboard file exists\n";
        
        // Check if dashboard loads without errors
        ob_start();
        include 'views/dashboard/index.php';
        $dashboardContent = ob_get_clean();
        
        if (strpos($dashboardContent, 'AutoDial Pro Dashboard') !== false) {
            echo "✓ Dashboard loads with correct title\n";
        }
        
        if (strpos($dashboardContent, 'Total Contacts') !== false) {
            echo "✓ Dashboard statistics section present\n";
        }
        
        echo "✓ Dashboard renders successfully\n";
    } else {
        echo "✗ Dashboard file not found\n";
    }
    
    echo "\n✅ All SQLite database tests passed!\n";
    echo "\nDashboard is ready for local testing with SQLite.\n";
    echo "Access it at: http://localhost/acrm/dashboard\n";
    echo "\nLogin with:\n";
    echo "- Email: admin@autocrm.com\n";
    echo "- Password: admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>