<?php
require_once "BaseModel.php";

class User extends BaseModel {
    protected $table = "users";
    protected $fillable = [
        "email", "password", "first_name", "last_name", "company_name", "role", "status"
    ];
    protected $hidden = ["password"];
    
    public function create($data) {
        if (isset($data["password"])) {
            $data["password"] = password_hash($data["password"], PASSWORD_DEFAULT);
        }
        return parent::create($data);
    }
    
    public function authenticate($email, $password) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? AND status = \"active\"");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["password"])) {
            return $this->hideFields($user);
        }
        
        return false;
    }
}