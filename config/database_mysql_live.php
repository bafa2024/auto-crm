<?php
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
?>