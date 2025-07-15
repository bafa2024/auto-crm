<?php
// config/database.php
require_once __DIR__ . '/cloud.php';

class Database {
    private $conn;
    private $config;

    public function __construct() {
        $this->config = CloudConfig::getDatabaseConfig();
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = $this->buildDSN();
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Use persistent connections for better performance
            ];
            
            // Add SSL options for cloud databases
            $platform = CloudConfig::detectPlatform();
            if (in_array($platform, ['aws', 'azure', 'gcp']) && isset($_ENV['DB_SSL_CA'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DB_SSL_CA'];
                if (isset($_ENV['DB_SSL_VERIFY'])) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $_ENV['DB_SSL_VERIFY'] === 'true';
                }
            }
            
            $this->conn = new PDO(
                $dsn, 
                $this->config['username'], 
                $this->config['password'], 
                $options
            );
            
            // Set timezone to UTC for consistency across platforms
            $this->conn->exec("SET time_zone = '+00:00'");
            
        } catch(PDOException $exception) {
            // Log error appropriately based on environment
            $this->logError($exception);
            
            // In production, don't expose database errors
            if ($_ENV['APP_ENV'] === 'production') {
                throw new Exception('Database connection failed');
            } else {
                throw $exception;
            }
        }
        
        return $this->conn;
    }
    
    private function buildDSN() {
        $dsn = "mysql:";
        
        // Use Unix socket for Google Cloud SQL
        if (isset($this->config['socket'])) {
            $dsn .= "unix_socket=" . $this->config['socket'] . ";";
        } else {
            $dsn .= "host=" . $this->config['host'] . ";";
            if (isset($this->config['port'])) {
                $dsn .= "port=" . $this->config['port'] . ";";
            }
        }
        
        $dsn .= "dbname=" . $this->config['database'] . ";";
        $dsn .= "charset=utf8mb4";
        
        return $dsn;
    }
    
    private function logError($exception) {
        $logConfig = CloudConfig::getLoggingConfig();
        
        $errorMessage = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'error',
            'message' => 'Database connection failed',
            'error' => $exception->getMessage(),
            'platform' => CloudConfig::detectPlatform(),
            'trace' => $exception->getTraceAsString()
        ];
        
        // Log based on platform
        switch ($logConfig['driver']) {
            case 'file':
                error_log(json_encode($errorMessage) . PHP_EOL, 3, $logConfig['path']);
                break;
            
            case 'cloudwatch':
                // AWS CloudWatch logging
                // Implement CloudWatch logging here
                error_log(json_encode($errorMessage));
                break;
                
            case 'stackdriver':
                // Google Cloud Logging
                // Implement Stackdriver logging here
                error_log(json_encode($errorMessage));
                break;
                
            case 'azure':
                // Azure Monitor logging
                // Implement Azure logging here
                error_log(json_encode($errorMessage));
                break;
                
            default:
                error_log(json_encode($errorMessage));
        }
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database version and info
     */
    public function getDatabaseInfo() {
        try {
            $conn = $this->getConnection();
            $version = $conn->query("SELECT VERSION()")->fetchColumn();
            
            return [
                'connected' => true,
                'version' => $version,
                'platform' => CloudConfig::detectPlatform(),
                'host' => $this->config['host'] ?? 'socket',
                'database' => $this->config['database']
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize database schema
     */
    public function initializeSchema() {
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('Schema file not found');
        }
        
        $sql = file_get_contents($schemaFile);
        $conn = $this->getConnection();
        
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
        
        return true;
    }
}