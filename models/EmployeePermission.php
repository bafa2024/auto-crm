<?php
require_once "BaseModel.php";

class EmployeePermission extends BaseModel {
    protected $table = "employee_permissions";
    protected $fillable = [
        "user_id", 
        "can_upload_contacts", 
        "can_create_campaigns", 
        "can_send_campaigns",
        "can_edit_campaigns",
        "can_delete_campaigns",
        "can_export_contacts",
        "can_view_all_campaigns",
        "can_send_instant_emails"
    ];
    
    /**
     * Get permissions for a user
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $permissions = $stmt->fetch();
        
        // If no permissions exist, create default ones
        if (!$permissions) {
            $this->create(['user_id' => $userId]);
            return $this->getUserPermissions($userId);
        }
        
        return $permissions;
    }
    
    /**
     * Update permissions for a user
     */
    public function updateUserPermissions($userId, $permissions) {
        // Check if permissions exist
        $existing = $this->getUserPermissions($userId);
        
        if ($existing) {
            // Update existing permissions
            $sql = "UPDATE {$this->table} SET ";
            $fields = [];
            $values = [];
            
            foreach ($permissions as $key => $value) {
                if (in_array($key, $this->fillable) && $key !== 'user_id') {
                    $fields[] = "$key = ?";
                    $values[] = $value ? 1 : 0;
                }
            }
            
            if (!empty($fields)) {
                $sql .= implode(", ", $fields);
                $sql .= ", updated_at = ? WHERE user_id = ?";
                $values[] = date('Y-m-d H:i:s');
                $values[] = $userId;
                
                $stmt = $this->db->prepare($sql);
                return $stmt->execute($values);
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($userId, $permission) {
        $permissions = $this->getUserPermissions($userId);
        
        if ($permissions && isset($permissions[$permission])) {
            return (bool) $permissions[$permission];
        }
        
        // Default to true for basic permissions
        $defaultTrue = ['can_upload_contacts', 'can_create_campaigns', 'can_send_campaigns', 'can_edit_campaigns', 'can_send_instant_emails'];
        return in_array($permission, $defaultTrue);
    }
    
    /**
     * Get all permissions with user info
     */
    public function getAllWithUsers() {
        $sql = "SELECT p.*, u.email, u.first_name, u.last_name, u.role 
                FROM {$this->table} p 
                JOIN users u ON p.user_id = u.id 
                WHERE u.role IN ('agent', 'manager')
                ORDER BY u.role, u.email";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}