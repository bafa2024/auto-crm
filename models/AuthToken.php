<?php
require_once "BaseModel.php";

class AuthToken extends BaseModel {
    protected $table = "auth_tokens";
    protected $fillable = ["email", "token", "expires_at", "is_used"];
    
    /**
     * Generate a unique auth token for email
     */
    public function generateToken($email) {
        if (!$this->db) return false;
        
        // Generate unique token
        $token = bin2hex(random_bytes(32)); // 64 character token
        
        // Set expiry time (30 minutes from now)
        $expires_at = date('Y-m-d H:i:s', time() + 1800);
        
        // Invalidate any existing unused tokens for this email
        $this->invalidateOldTokens($email);
        
        // Create new token
        $data = [
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at,
            'is_used' => 0
        ];
        
        $result = $this->create($data);
        
        if ($result) {
            return $token;
        }
        
        return false;
    }
    
    /**
     * Verify auth token
     */
    public function verifyToken($token) {
        if (!$this->db) return false;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE token = ? 
                AND is_used = 0 
                AND expires_at > ?
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $tokenData = $stmt->fetch();
        
        if ($tokenData) {
            // Mark token as used
            $this->markAsUsed($tokenData['id']);
            return $tokenData['email'];
        }
        
        return false;
    }
    
    /**
     * Mark token as used
     */
    private function markAsUsed($id) {
        $sql = "UPDATE {$this->table} SET is_used = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Invalidate old tokens for an email
     */
    private function invalidateOldTokens($email) {
        $sql = "UPDATE {$this->table} SET is_used = 1 WHERE email = ? AND is_used = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$email]);
    }
    
    /**
     * Clean up expired tokens (maintenance function)
     */
    public function cleanupExpiredTokens() {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < ? OR is_used = 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([date('Y-m-d H:i:s')]);
    }
}