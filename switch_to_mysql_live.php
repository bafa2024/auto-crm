<?php
// switch_to_mysql_live.php - Switch to MySQL for live hosting

echo "🔄 Switching to MySQL for Live Hosting\n";
echo "=====================================\n\n";

// Create MySQL configuration
$mysqlConfig = '<?php
// Live Hosting MySQL Configuration
class Database {
    private $host = "localhost";
    private $db_name = "u946493694_autocrm"; // Update with your actual database name
    private $username = "u946493694_admin"; // Update with your actual username  
    private $password = "YourPassword123"; // Update with your actual password
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>';

// Backup current config
if (file_exists('config/database.php')) {
    $backup = 'config/database_backup_' . date('Y-m-d_H-i-s') . '.php';
    copy('config/database.php', $backup);
    echo "✅ Backed up current config to: $backup\n";
}

// Write new MySQL config
file_put_contents('config/database.php', $mysqlConfig);
echo "✅ Switched to MySQL configuration\n\n";

echo "🔧 Next Steps:\n";
echo "1. Update database credentials in config/database.php\n";
echo "2. Create MySQL database in your Hostinger panel\n";
echo "3. Import the database schema\n";
echo "4. Update the following in config/database.php:\n";
echo "   - \$db_name: Your actual database name\n";
echo "   - \$username: Your actual database username\n";
echo "   - \$password: Your actual database password\n\n";

echo "📋 Sample MySQL Schema:\n";
echo "You can find the schema in: database/schema.sql\n\n";

echo "✅ MySQL configuration applied!\n";
?>