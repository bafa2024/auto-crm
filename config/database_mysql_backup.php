<?php
// config/database.php

class Database {
    private $conn;
    private $config;

    public function __construct() {
        // Load config from environment or defaults
        $this->config = [
            "host" => $_ENV["DB_HOST"] ?? "localhost",
            "port" => $_ENV["DB_PORT"] ?? "3306",
            "database" => $_ENV["DB_NAME"] ?? "u946493694_autocrm",
            "username" => $_ENV["DB_USER"] ?? "u946493694_autocrmu",
            "password" => $_ENV["DB_PASS"] ?? "CDExzsawq123@#$"
        ];
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->config["host"] . 
                   ";port=" . $this->config["port"] . 
                   ";dbname=" . $this->config["database"] . 
                   ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->config["username"], $this->config["password"], $options);
        } catch(PDOException $exception) {
            // Log error but don't expose to user
            error_log("Database connection failed: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}