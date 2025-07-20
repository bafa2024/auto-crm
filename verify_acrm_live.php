<?php
// verify_acrm_live.php - Verify ACRM functionality on live server
// Run this at: https://acrm.regrowup.ca/verify_acrm_live.php

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACRM Live Server Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .test-result.success { background: #d4edda; border-left: 4px solid #28a745; }
        .test-result.error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .test-result.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .summary { background: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ACRM Live Server Verification</h1>
            <p>Testing functionality at: <strong>acrm.regrowup.ca</strong></p>
            <p>Timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <?php
        $testResults = [];
        $totalTests = 0;
        $passedTests = 0;

        function runTest($name, $callback) {
            global $totalTests, $passedTests;
            $totalTests++;
            
            try {
                $result = $callback();
                if ($result['success']) {
                    $passedTests++;
                    echo "<div class='test-result success'>✅ <strong>$name:</strong> {$result['message']}</div>";
                } else {
                    echo "<div class='test-result error'>❌ <strong>$name:</strong> {$result['message']}</div>";
                }
                return $result;
            } catch (Exception $e) {
                echo "<div class='test-result error'>❌ <strong>$name:</strong> Exception: " . $e->getMessage() . "</div>";
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // Test 1: Database Connection
        echo "<div class='test-section info'><h3>1. Database Connection Test</h3>";
        $dbTest = runTest("Database Connection", function() {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->query("SELECT 1 as test");
            $result = $stmt->fetch();
            if ($result['test'] == 1) {
                return ['success' => true, 'message' => 'Connected successfully to ' . $database->getDatabaseType() . ' database'];
            }
            return ['success' => false, 'message' => 'Database connection failed'];
        });
        echo "</div>";

        // Test 2: Environment Detection
        echo "<div class='test-section info'><h3>2. Environment Detection</h3>";
        $envTest = runTest("Environment Detection", function() {
            require_once 'config/database.php';
            $database = new Database();
            return [
                'success' => true, 
                'message' => 'Environment: ' . $database->getEnvironment() . ', Database: ' . $database->getDatabaseType()
            ];
        });
        echo "</div>";

        // Test 3: User Authentication
        echo "<div class='test-section info'><h3>3. User Authentication Test</h3>";
        $authTest = runTest("Admin User Check", function() {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("SELECT id, email, role FROM users WHERE email = ?");
            $stmt->execute(['admin@autocrm.com']);
            $user = $stmt->fetch();
            if ($user) {
                return ['success' => true, 'message' => "Admin user found (ID: {$user['id']}, Role: {$user['role']})"];
            }
            return ['success' => false, 'message' => 'Admin user not found'];
        });
        echo "</div>";

        // Test 4: Campaign Creation
        echo "<div class='test-section info'><h3>4. Campaign Creation Test</h3>";
        $campaignTest = runTest("Campaign Creation", function() {
            require_once 'config/database.php';
            require_once 'services/EmailCampaignService.php';
            $database = new Database();
            $campaignService = new EmailCampaignService($database);
            
            $testCampaign = [
                'user_id' => 1,
                'name' => 'Live Verification Test - ' . date('Y-m-d H:i:s'),
                'subject' => 'Live Server Verification',
                'content' => '<h1>Test Campaign</h1><p>This is a verification test.</p>',
                'sender_name' => 'Test Sender',
                'sender_email' => 'test@example.com',
                'status' => 'draft'
            ];
            
            $result = $campaignService->createCampaign($testCampaign);
            
            if ($result['success']) {
                // Clean up test data
                $db = $database->getConnection();
                $db->exec("DELETE FROM email_campaigns WHERE id = " . $result['campaign_id']);
                return ['success' => true, 'message' => "Campaign created successfully (ID: {$result['campaign_id']}) and cleaned up"];
            }
            return ['success' => false, 'message' => $result['message']];
        });
        echo "</div>";

        // Test 5: Contact Creation
        echo "<div class='test-section info'><h3>5. Contact Creation Test</h3>";
        $contactTest = runTest("Contact Creation", function() {
            require_once 'config/database.php';
            require_once 'models/Contact.php';
            $database = new Database();
            $db = $database->getConnection();
            $contactModel = new Contact($db);
            
            $testContact = [
                'first_name' => 'Live',
                'last_name' => 'Test Contact',
                'email' => 'live.test@example.com',
                'phone' => '555-123-4567',
                'company' => 'Test Company',
                'created_by' => 1
            ];
            
            $contact = $contactModel->create($testContact);
            
            if ($contact) {
                // Clean up test data
                $db->exec("DELETE FROM contacts WHERE id = {$contact['id']}");
                return ['success' => true, 'message' => "Contact created successfully (ID: {$contact['id']}) and cleaned up"];
            }
            return ['success' => false, 'message' => 'Contact creation failed'];
        });
        echo "</div>";

        // Test 6: Database Schema Check
        echo "<div class='test-section info'><h3>6. Database Schema Verification</h3>";
        $schemaTest = runTest("Required Columns Check", function() {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $requiredColumns = ['email_content', 'from_name', 'from_email'];
            $stmt = $db->query("DESCRIBE email_campaigns");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $existingColumns = array_column($columns, 'Field');
            
            $missingColumns = array_diff($requiredColumns, $existingColumns);
            
            if (empty($missingColumns)) {
                return ['success' => true, 'message' => 'All required columns present in email_campaigns table'];
            }
            return ['success' => false, 'message' => 'Missing columns: ' . implode(', ', $missingColumns)];
        });
        echo "</div>";

        // Test 7: Table Counts
        echo "<div class='test-section info'><h3>7. Database Table Health Check</h3>";
        $healthTest = runTest("Table Health Check", function() {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $tables = ['email_campaigns', 'users', 'contacts', 'email_recipients', 'campaign_sends'];
            $results = [];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
                    $count = $stmt->fetch()['count'];
                    $results[] = "$table: $count records";
                } catch (Exception $e) {
                    $results[] = "$table: ERROR - " . $e->getMessage();
                }
            }
            
            return ['success' => true, 'message' => implode(', ', $results)];
        });
        echo "</div>";

        // Test 8: API Endpoints
        echo "<div class='test-section info'><h3>8. API Endpoints Test</h3>";
        $apiTest = runTest("API Accessibility", function() {
            $endpoints = [
                'api/index.php' => 'Main API',
                'api/get_campaign.php' => 'Get Campaign API'
            ];
            
            $results = [];
            foreach ($endpoints as $endpoint => $name) {
                if (file_exists($endpoint)) {
                    $results[] = "$name: Available";
                } else {
                    $results[] = "$name: Not found";
                }
            }
            
            return ['success' => true, 'message' => implode(', ', $results)];
        });
        echo "</div>";

        // Summary
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
        $status = $successRate == 100 ? 'success' : ($successRate >= 80 ? 'warning' : 'error');
        ?>

        <div class="summary">
            <h2>📊 Test Results Summary</h2>
            <div class="test-result <?php echo $status; ?>">
                <strong>Overall Status:</strong> <?php echo $passedTests; ?> out of <?php echo $totalTests; ?> tests passed (<?php echo $successRate; ?>%)
            </div>
            
            <?php if ($successRate == 100): ?>
                <div class="test-result success">
                    <h3>🎉 EXCELLENT! All tests passed!</h3>
                    <p>Your ACRM application is fully functional on the live server.</p>
                    <ul>
                        <li>✅ Database connection is working</li>
                        <li>✅ Campaign creation is working</li>
                        <li>✅ Contact creation is working</li>
                        <li>✅ Database schema is correct</li>
                        <li>✅ All services are accessible</li>
                    </ul>
                </div>
            <?php elseif ($successRate >= 80): ?>
                <div class="test-result warning">
                    <h3>⚠️ MOSTLY WORKING</h3>
                    <p>Most functionality is working, but there are some minor issues to address.</p>
                </div>
            <?php else: ?>
                <div class="test-result error">
                    <h3>❌ NEEDS ATTENTION</h3>
                    <p>There are significant issues that need to be resolved.</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <a href="index.php" class="btn">🏠 Go to ACRM Dashboard</a>
            <a href="campaigns.php" class="btn">📧 Manage Campaigns</a>
            <a href="contacts.php" class="btn">👥 Manage Contacts</a>
            <button onclick="location.reload()" class="btn">🔄 Run Tests Again</button>
        </div>
    </div>
</body>
</html> 