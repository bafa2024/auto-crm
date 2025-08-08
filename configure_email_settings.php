<?php
require_once 'config/database.php';

echo "<h2>Setup SMTP Settings for Instant Email</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Create smtp_settings table if it doesn't exist
    $createTable = "
    CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $db->exec($createTable);
    echo "✅ SMTP settings table created/verified<br>";
    
    // Default SMTP settings for common providers
    $defaultSettings = [
        'smtp_enabled' => '1',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',  // User needs to fill this
        'smtp_password' => '',  // User needs to fill this  
        'smtp_from_email' => 'noreply@yoursite.com',
        'smtp_from_name' => 'AutoCRM System'
    ];
    
    // Insert default settings if they don't exist
    foreach ($defaultSettings as $key => $value) {
        $stmt = $db->prepare("INSERT IGNORE INTO smtp_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    echo "✅ Default SMTP settings inserted<br>";
    
    echo "<h3>Current SMTP Settings:</h3>";
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM smtp_settings ORDER BY setting_key");
    $stmt->execute();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = $row['setting_value'];
        if (strpos($row['setting_key'], 'password') !== false && !empty($value)) {
            $value = '***hidden***';
        }
        echo "<tr><td>" . htmlspecialchars($row['setting_key']) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Instructions to Configure Email:</h3>";
    echo "<ol>";
    echo "<li><strong>For Gmail:</strong><br>";
    echo "   - Use your Gmail address as smtp_username<br>";
    echo "   - Generate an App Password (not your regular password)<br>";
    echo "   - Go to Google Account Settings > Security > 2-Step Verification > App passwords<br>";
    echo "   - Generate an app password and use that as smtp_password</li>";
    echo "<li><strong>For Other Providers:</strong><br>";
    echo "   - Update smtp_host, smtp_port, and smtp_encryption accordingly<br>";
    echo "   - Common settings:<br>";
    echo "     * Gmail: smtp.gmail.com:587 (TLS)<br>";
    echo "     * Outlook: smtp-mail.outlook.com:587 (STARTTLS)<br>";
    echo "     * Yahoo: smtp.mail.yahoo.com:587 (TLS)</li>";
    echo "<li><strong>Update Settings:</strong><br>";
    echo "   - Manually update the smtp_settings table in your database<br>";
    echo "   - Or use the admin panel if available</li>";
    echo "</ol>";
    
    echo "<h3>Quick Update SQL Commands:</h3>";
    echo "<pre>";
    echo "-- Update SMTP username (your email)\n";
    echo "UPDATE smtp_settings SET setting_value = 'your-email@gmail.com' WHERE setting_key = 'smtp_username';\n\n";
    echo "-- Update SMTP password (your app password)\n";
    echo "UPDATE smtp_settings SET setting_value = 'your-app-password' WHERE setting_key = 'smtp_password';\n\n";
    echo "-- Update from email\n";
    echo "UPDATE smtp_settings SET setting_value = 'your-email@gmail.com' WHERE setting_key = 'smtp_from_email';\n\n";
    echo "-- Update from name\n";
    echo "UPDATE smtp_settings SET setting_value = 'Your Company Name' WHERE setting_key = 'smtp_from_name';\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
