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
            $dsn = "mysql:host=" . $this->config['host'] . ";dbname=" . $this->config['database'] . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch(PDOException $exception) {
            // Log error but don't expose to user
            error_log("Database connection failed: " . $exception->getMessage());
            
            // Return null instead of showing error
            return null;
        }
        
        return $this->conn;
    }
}