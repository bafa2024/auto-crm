<?php
require_once "BaseModel.php";

class User extends BaseModel {
    protected $table = "users";
    protected $fillable = [
        "email", "password", "first_name", "last_name", "company_name", "phone", "role", "status"
    ];
    protected $hidden = ["password"];
    
    public function create($data) {
        if (isset($data["password"])) {
            // Check if user is employee (agent or manager) - no hashing for employees
            if (isset($data["role"]) && in_array($data["role"], ['agent', 'manager'])) {
                // Store plain text password for employees
                $data["password"] = $data["password"];
            } else {
                // Keep hashing for admin users
                $data["password"] = password_hash($data["password"], PASSWORD_DEFAULT);
            }
        }
        return parent::create($data);
    }
    
    public function authenticate($email, $password) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? AND status = ?");
        $stmt->execute([$email, "active"]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if user is employee (loose login - plain text password)
            if (in_array($user["role"], ['agent', 'manager'])) {
                // Try plain text comparison first for employees
                if ($password === $user["password"]) {
                    return $this->hideFields($user);
                }
                // If plain text doesn't work, try hashed password (for backward compatibility)
                if (password_verify($password, $user["password"])) {
                    return $this->hideFields($user);
                }
            } else {
                // Keep secure password verification for admin users
                if (password_verify($password, $user["password"])) {
                    return $this->hideFields($user);
                }
            }
        }
        
        return false;
    }
    
    public function findByEmail($email) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        return $user ? $this->hideFields($user) : false;
    }
    
    public function updatePassword($userId, $newPassword) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("SELECT role FROM {$this->table} WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if user is employee
            if (in_array($user["role"], ['agent', 'manager'])) {
                // Store plain text password for employees
                $hashedPassword = $newPassword;
            } else {
                // Hash password for admin users
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            $stmt = $this->db->prepare("UPDATE {$this->table} SET password = ? WHERE id = ?");
            return $stmt->execute([$hashedPassword, $userId]);
        }
        
        return false;
    }
}