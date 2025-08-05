<?php
// Create email_recipients table in MySQL
require_once 'config/config.php';

echo "<h1>Creating email_recipients Table</h1>";

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ MySQL connection successful</p>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_recipients'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ email_recipients table already exists</p>";
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
        $count = $stmt->fetch()['count'];
        echo "<p>Table has $count records</p>";
        
    } else {
        echo "<p>Creating email_recipients table...</p>";
        
        // Create the table
        $sql = "CREATE TABLE email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            company VARCHAR(255),
            dot VARCHAR(100),
            custom_data TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            error_message TEXT,
            open_count INT DEFAULT 0,
            click_count INT DEFAULT 0,
            campaign_id INT,
            tracking_id VARCHAR(255),
            status VARCHAR(50) DEFAULT 'active',
            INDEX idx_email (email),
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ email_recipients table created successfully</p>";
        
        // Insert sample data
        echo "<p>Inserting sample data...</p>";
        
        $sampleData = [
            ['John Doe', 'john.doe@example.com', 'Example Corp', '123456'],
            ['Jane Smith', 'jane.smith@test.com', 'Test Solutions', '789012'],
            ['Bob Wilson', 'bob.wilson@demo.com', 'Demo Company', '345678'],
            ['Alice Johnson', 'alice.johnson@sample.com', 'Sample Inc', '901234'],
            ['Charlie Brown', 'charlie.brown@test.org', 'Test Organization', '567890']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO email_recipients (name, email, company, dot) VALUES (?, ?, ?, ?)");
        
        foreach ($sampleData as $data) {
            $stmt->execute($data);
        }
        
        echo "<p style='color: green;'>✓ Sample data inserted successfully</p>";
        
        // Verify the data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
        $count = $stmt->fetch()['count'];
        echo "<p>Table now has $count records</p>";
    }
    
    echo "<h2>Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE email_recipients");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Sample Data:</h2>";
    $stmt = $pdo->query("SELECT * FROM email_recipients LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Company</th><th>DOT</th><th>Created At</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['email'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['company'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['dot'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'>✓ Setup complete! The contacts page should now work with MySQL.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 