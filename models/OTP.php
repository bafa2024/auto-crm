<?php
require_once "BaseModel.php";

class OTP extends BaseModel {
    protected $table = "otps";
    protected $fillable = ["email", "otp_code", "expires_at", "is_used"];
    
    /**
     * Generate a new OTP for email
     */
    public function generateOTP($email) {
        if (!$this->db) return false;
        
        // Generate 6-digit OTP
        $otp_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiry time (10 minutes from now)
        $expires_at = date('Y-m-d H:i:s', time() + 600);
        
        // Invalidate any existing unused OTPs for this email
        $this->invalidateOldOTPs($email);
        
        // Create new OTP
        $data = [
            'email' => $email,
            'otp_code' => $otp_code,
            'expires_at' => $expires_at,
            'is_used' => 0
        ];
        
        $result = $this->create($data);
        
        if ($result) {
            return $otp_code;
        }
        
        return false;
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($email, $otp_code) {
        if (!$this->db) return false;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE email = ? 
                AND otp_code = ? 
                AND is_used = 0 
                AND expires_at > ?
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $otp_code, date('Y-m-d H:i:s')]);
        $otp = $stmt->fetch();
        
        if ($otp) {
            // Mark OTP as used
            $this->markAsUsed($otp['id']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Mark OTP as used
     */
    private function markAsUsed($id) {
        $sql = "UPDATE {$this->table} SET is_used = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Invalidate old OTPs for an email
     */
    private function invalidateOldOTPs($email) {
        $sql = "UPDATE {$this->table} SET is_used = 1 WHERE email = ? AND is_used = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$email]);
    }
    
    /**
     * Clean up expired OTPs (maintenance function)
     */
    public function cleanupExpiredOTPs() {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < ? OR is_used = 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([date('Y-m-d H:i:s')]);
    }
    
    /**
     * Get latest OTP for email (for testing)
     */
    public function getLatestOTP($email) {
        if (!$this->db) return null;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE email = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
}