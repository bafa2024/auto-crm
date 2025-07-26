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
                // Plain text comparison for employees
                if ($password === $user["password"]) {
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
}