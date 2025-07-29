<?php
require_once "BaseModel.php";

class PasswordReset extends BaseModel {
    protected $table = "password_reset_tokens";
    protected $fillable = [
        "user_id", "token", "email", "expires_at", "used"
    ];
    
    /**
     * Generate a password reset token for a user
     */
    public function generateToken($email) {
        if (!$this->db) return false;
        
        // Check if user exists and is active
        $stmt = $this->db->prepare("SELECT id, email FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Delete any existing tokens for this user
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (user_id, token, email, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $user['id'],
            $token,
            $user['email'],
            $expiresAt
        ]);
        
        if ($result) {
            return [
                'token' => $token,
                'email' => $user['email'],
                'expires_at' => $expiresAt
            ];
        }
        
        return false;
    }
    
    /**
     * Validate a password reset token
     */
    public function validateToken($token) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            SELECT prt.*, u.email, u.first_name, u.last_name 
            FROM {$this->table} prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > ?
        ");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        
        return $stmt->fetch();
    }
    
    /**
     * Mark a token as used
     */
    public function markTokenAsUsed($token) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("UPDATE {$this->table} SET used = 1 WHERE token = ?");
        return $stmt->execute([$token]);
    }
    
    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens() {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < ?");
        return $stmt->execute([date('Y-m-d H:i:s')]);
    }
    
    /**
     * Get token by email for testing/debugging
     */
    public function getTokenByEmail($email) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE email = ? AND used = 0 AND expires_at > ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email, date('Y-m-d H:i:s')]);
        
        return $stmt->fetch();
    }
}
?> 