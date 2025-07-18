<?php
// config/database_sqlite.php - SQLite Database Configuration for Local Testing

class SQLiteDatabase {
    private $dbPath;
    private $connection;
    
    public function __construct() {
        // Set database path
        $this->dbPath = __DIR__ . '/../database/autocrm_local.db';
        
        // Check if database file exists
        if (!file_exists($this->dbPath)) {
            throw new Exception("SQLite database not found. Run: php database/create_sqlite.php");
        }
    }
    
    public function getConnection() {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO("sqlite:" . $this->dbPath);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Enable foreign key constraints
                $this->connection->exec('PRAGMA foreign_keys = ON');
                
                // Set timeout for busy database
                $this->connection->exec('PRAGMA busy_timeout = 30000');
                
            } catch (PDOException $e) {
                throw new Exception("SQLite Connection failed: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    public function getDatabaseInfo() {
        $conn = $this->getConnection();
        
        $info = [
            'type' => 'SQLite',
            'path' => $this->dbPath,
            'size' => file_exists($this->dbPath) ? filesize($this->dbPath) : 0,
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
    
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            $result = $stmt->fetchColumn();
            return $result === 1;
        } catch (Exception $e) {
            error_log("SQLite connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function close() {
        $this->connection = null;
    }
}

// For compatibility with existing Database class
class Database extends SQLiteDatabase {
    // This extends SQLiteDatabase so existing code works
}
?>