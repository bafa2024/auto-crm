<?php
// setup_live_hosting.php - Setup database for live hosting

echo "🚀 AutoDial Pro Live Hosting Setup\n";
echo "==================================\n\n";

echo "1. Checking current database configuration...\n";

// Check which database config is being used
$currentConfig = file_get_contents('config/database.php');
if (strpos($currentConfig, 'SQLiteDatabase') !== false) {
    echo "   📋 Currently configured for: SQLite\n";
    $usingSQLite = true;
} else {
    echo "   📋 Currently configured for: MySQL\n";
    $usingSQLite = false;
}

echo "\n2. Checking environment...\n";
echo "   🌐 Domain: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
echo "   📂 Path: " . __DIR__ . "\n";

if ($usingSQLite) {
    echo "\n3. Setting up SQLite database...\n";
    
    // Create database directory if it doesn't exist
    if (!is_dir('database')) {
        mkdir('database', 0755, true);
        echo "   ✅ Created database directory\n";
    }
    
    $dbFile = 'database/autocrm_local.db';
    
    if (file_exists($dbFile)) {
        echo "   ✅ SQLite database already exists\n";
    } else {
        echo "   📦 Creating SQLite database...\n";
        
        // Include the SQLite creation script
        if (file_exists('database/create_sqlite.php')) {
            try {
                // Capture output from create_sqlite.php
                ob_start();
                include 'database/create_sqlite.php';
                $output = ob_get_clean();
                
                if (file_exists($dbFile)) {
                    echo "   ✅ SQLite database created successfully\n";
                } else {
                    echo "   ❌ Failed to create SQLite database\n";
                    echo "   Output: $output\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error creating SQLite database: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ❌ create_sqlite.php not found\n";
        }
    }
} else {
    echo "\n3. Checking MySQL configuration...\n";
    echo "   📋 MySQL is configured - no SQLite setup needed\n";
}

echo "\n4. Creating live hosting database switch...\n";

// Create a simple database switcher for live hosting
$switcherContent = '<?php
// live_db_switch.php - Switch between SQLite and MySQL for live hosting

if (isset($_GET["to"])) {
    $switchTo = $_GET["to"];
    
    if ($switchTo === "mysql") {
        // Switch to MySQL
        $mysqlConfig = \'<?php
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
?>\';
        
        file_put_contents("config/database.php", $mysqlConfig);
        echo "Switched to MySQL configuration. Please update the database credentials.";
        
    } elseif ($switchTo === "sqlite") {
        // Switch to SQLite
        $sqliteConfig = \'<?php
// SQLite Database Configuration
class SQLiteDatabase {
    private $db_path;
    
    public function __construct() {
        $this->db_path = __DIR__ . "/../database/autocrm_local.db";
        
        if (!file_exists($this->db_path)) {
            throw new Exception("SQLite database not found. Run: php database/create_sqlite.php");
        }
    }
    
    public function getConnection() {
        try {
            $conn = new PDO("sqlite:" . $this->db_path);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

// Alias for compatibility
class Database extends SQLiteDatabase {}
?>\';
        
        file_put_contents("config/database.php", $sqliteConfig);
        echo "Switched to SQLite configuration.";
    }
    
    echo "<br><a href=\'?\'>Back to setup</a>";
    
} else {
    echo "Live Hosting Database Setup<br><br>";
    echo "<a href=\'?to=mysql\'>Switch to MySQL</a><br>";
    echo "<a href=\'?to=sqlite\'>Switch to SQLite</a><br>";
}
?>';

file_put_contents('live_db_switch.php', $switcherContent);

echo "   ✅ Created live_db_switch.php\n";

echo "\n5. Testing database connection...\n";

try {
    require_once 'config/database.php';
    
    if ($usingSQLite) {
        $db = new SQLiteDatabase();
    } else {
        $db = new Database();
    }
    
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "   ✅ Database connection successful\n";
        
        // Test a simple query
        $stmt = $conn->query("SELECT 1 as test");
        if ($stmt->fetch()) {
            echo "   ✅ Database query test passed\n";
        }
        
    } else {
        echo "   ❌ Database connection failed\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Quick Fixes:\n";
echo "==============\n\n";

echo "Option 1 - Use MySQL (Recommended for live hosting):\n";
echo "1. Visit: https://yourdomain.com/acrm/live_db_switch.php\n";
echo "2. Click 'Switch to MySQL'\n";
echo "3. Update database credentials in config/database.php\n";
echo "4. Import your database schema\n\n";

echo "Option 2 - Create SQLite database:\n";
echo "1. Run: php database/create_sqlite.php\n";
echo "2. Or visit: https://yourdomain.com/acrm/database/create_sqlite.php\n\n";

echo "Option 3 - Upload SQLite database:\n";
echo "1. Download autocrm_local.db from your local development\n";
echo "2. Upload it to: /database/autocrm_local.db on your server\n\n";

echo "✅ Setup complete! Visit your application to test.\n";
?>