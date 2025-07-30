<?php
// config/database.php - Automatic Database Configuration
// Automatically switches between SQLite (local) and MySQL (live server)

class Database {
    private $connection;
    private $config;
    private $environment;
    
    public function __construct() {
        $this->detectEnvironment();
        $this->loadConfiguration();
    }
    
    /**
     * Detect if we're running locally or on live server
     */
    private function detectEnvironment() {
        // Check for common local development indicators
        $localIndicators = [
            'localhost',
            '127.0.0.1',
            '::1',
            'xampp',
            'wamp',
            'mamp',
            'dev',
            'local'
        ];
        
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Check if we're on localhost or local development environment
        $isLocal = false;
        
        // Check server variables if available
        if (!empty($serverName) || !empty($httpHost)) {
            foreach ($localIndicators as $indicator) {
                if (stripos($serverName, $indicator) !== false || 
                    stripos($httpHost, $indicator) !== false) {
                    $isLocal = true;
                    break;
                }
            }
        }
        
        // Check document root for local development paths
        if (!empty($documentRoot)) {
            if (stripos($documentRoot, 'xampp') !== false || 
                stripos($documentRoot, 'wamp') !== false ||
                stripos($documentRoot, 'htdocs') !== false ||
                stripos($documentRoot, 'www') !== false) {
                $isLocal = true;
            }
        }
        
        // Check script path for local development indicators
        if (!empty($scriptName)) {
            if (stripos($scriptName, 'xampp') !== false || 
                stripos($scriptName, 'htdocs') !== false ||
                stripos($scriptName, 'localhost') !== false) {
                $isLocal = true;
            }
        }
        
        // If no server variables are available (CLI), check current working directory
        if (empty($serverName) && empty($httpHost) && empty($documentRoot)) {
            $currentDir = getcwd();
            if (stripos($currentDir, 'xampp') !== false || 
                stripos($currentDir, 'htdocs') !== false ||
                stripos($currentDir, 'localhost') !== false ||
                stripos($currentDir, 'dev') !== false) {
                $isLocal = true;
            }
        }
        
        // Default to local if we can't determine (safer for development)
        if (empty($serverName) && empty($httpHost) && empty($documentRoot)) {
            $isLocal = true;
        }
        
        // Check for environment variable override
        if (isset($_ENV['DB_ENVIRONMENT'])) {
            $this->environment = $_ENV['DB_ENVIRONMENT'];
        } else {
            $this->environment = $isLocal ? 'local' : 'live';
        }
        
        // Log environment detection for debugging
        error_log("Database Environment Detected: " . $this->environment . 
                 " (Server: $serverName, Host: $httpHost, Root: $documentRoot, Local: " . ($isLocal ? 'true' : 'false') . ")");
    }
    
    /**
     * Load appropriate database configuration
     */
    private function loadConfiguration() {
        if ($this->environment === 'local') {
            // Local development - use MySQL with local settings
            $this->config = [
                'type' => 'mysql',
                'host' => 'localhost',
                'port' => '3306',
                'database' => 'autocrm',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4'
            ];
        } else {
            // Live server - use MySQL
            $this->config = [
                'type' => 'mysql',
                'host' => 'localhost',
                'port' => '3306',
                'database' => 'u946493694_autocrm',
                'username' => 'u946493694_autocrmu',
                'password' => 'CDExzsawq123@#$',
                'charset' => 'utf8mb4'
            ];
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        if ($this->connection === null) {
            try {
                if ($this->config['type'] === 'sqlite') {
                    $this->connection = $this->createSQLiteConnection();
                } else {
                    $this->connection = $this->createMySQLConnection();
                }
                
                // Set common PDO attributes
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    /**
     * Create SQLite connection
     */
    private function createSQLiteConnection() {
        $dbPath = $this->config['path'];
        
        // Create database directory if it doesn't exist
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        // Create database file if it doesn't exist
        if (!file_exists($dbPath)) {
            touch($dbPath);
            chmod($dbPath, 0644);
        }
        
        $connection = new PDO("sqlite:" . $dbPath);
        
        // Enable foreign key constraints
        $connection->exec('PRAGMA foreign_keys = ON');
        
        // Set timeout for busy database
        $connection->exec('PRAGMA busy_timeout = 30000');
        
        return $connection;
    }
    
    /**
     * Create MySQL connection
     */
    private function createMySQLConnection() {
        $dsn = "mysql:host=" . $this->config['host'] . 
               ";port=" . $this->config['port'] . 
               ";dbname=" . $this->config['database'] . 
               ";charset=" . $this->config['charset'];
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->config['charset']
        ];
        
        return new PDO($dsn, $this->config['username'], $this->config['password'], $options);
    }
    
    /**
     * Get database information
     */
    public function getDatabaseInfo() {
        $conn = $this->getConnection();
        
        if ($this->config['type'] === 'sqlite') {
            return $this->getSQLiteInfo($conn);
        } else {
            return $this->getMySQLInfo($conn);
        }
    }
    
    /**
     * Get SQLite database information
     */
    private function getSQLiteInfo($conn) {
        $info = [
            'type' => 'SQLite',
            'environment' => $this->environment,
            'path' => $this->config['path'],
            'size' => file_exists($this->config['path']) ? filesize($this->config['path']) : 0,
            'tables' => []
        ];
        
        // Get table list
        $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get row counts for each table
        foreach ($tables as $table) {
            try {
                $countStmt = $conn->query("SELECT COUNT(*) FROM $table");
                $count = $countStmt->fetchColumn();
                $info['tables'][$table] = $count;
            } catch (Exception $e) {
                $info['tables'][$table] = 'Error: ' . $e->getMessage();
            }
        }
        
        return $info;
    }
    
    /**
     * Get MySQL database information
     */
    private function getMySQLInfo($conn) {
        $info = [
            'type' => 'MySQL',
            'environment' => $this->environment,
            'host' => $this->config['host'],
            'database' => $this->config['database'],
            'tables' => []
        ];
        
        // Get table list
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get row counts for each table
        foreach ($tables as $table) {
            try {
                $countStmt = $conn->query("SELECT COUNT(*) FROM `$table`");
                $count = $countStmt->fetchColumn();
                $info['tables'][$table] = $count;
            } catch (Exception $e) {
                $info['tables'][$table] = 'Error: ' . $e->getMessage();
            }
        }
        
        return $info;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            
            if ($this->config['type'] === 'sqlite') {
                $stmt = $conn->query("SELECT 1");
            } else {
                $stmt = $conn->query("SELECT 1");
            }
            
            $result = $stmt->fetchColumn();
            return $result === 1;
        } catch (Exception $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current environment
     */
    public function getEnvironment() {
        return $this->environment;
    }
    
    /**
     * Get database type
     */
    public function getDatabaseType() {
        return $this->config['type'];
    }
    
    /**
     * Close database connection
     */
    public function close() {
        $this->connection = null;
    }
}
?>