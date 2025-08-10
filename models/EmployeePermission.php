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
     * Get existing columns from the table
     */
    private function getTableColumns() {
        try {
            $sql = "DESCRIBE {$this->table}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $columns;
        } catch (Exception $e) {
            error_log("Error getting table columns: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update permissions for a user
     */
    public function updateUserPermissions($userId, $permissions) {
        // Check if permissions exist
        $existing = $this->getUserPermissions($userId);
        
        if ($existing) {
            // Get existing columns to avoid column not found errors
            $existingColumns = $this->getTableColumns();
            
            // Update existing permissions
            $sql = "UPDATE {$this->table} SET ";
            $fields = [];
            $values = [];
            
            foreach ($permissions as $key => $value) {
                if (in_array($key, $this->fillable) && $key !== 'user_id' && in_array($key, $existingColumns)) {
                    $fields[] = "$key = ?";
                    $values[] = $value ? 1 : 0;
                }
            }
            
            if (!empty($fields)) {
                $sql .= implode(", ", $fields);
                if (in_array('updated_at', $existingColumns)) {
                    $sql .= ", updated_at = ?";
                    $values[] = date('Y-m-d H:i:s');
                }
                $sql .= " WHERE user_id = ?";
                $values[] = $userId;
                
                $stmt = $this->db->prepare($sql);
                return $stmt->execute($values);
            }
        }
        
        return false;
    }
    
    /**
     * Create permissions for a user
     */
    public function createUserPermissions($userId, $permissions) {
        try {
            // Get existing columns to avoid column not found errors
            $existingColumns = $this->getTableColumns();
            
            $fields = ['user_id'];
            $placeholders = ['?'];
            $values = [$userId];
            
            foreach ($permissions as $key => $value) {
                if (in_array($key, $this->fillable) && $key !== 'user_id' && in_array($key, $existingColumns)) {
                    $fields[] = $key;
                    $placeholders[] = '?';
                    $values[] = $value ? 1 : 0;
                }
            }
            
            if (in_array('created_at', $existingColumns)) {
                $fields[] = 'created_at';
                $placeholders[] = '?';
                $values[] = date('Y-m-d H:i:s');
            }
            
            if (in_array('updated_at', $existingColumns)) {
                $fields[] = 'updated_at';
                $placeholders[] = '?';
                $values[] = date('Y-m-d H:i:s');
            }
            
            $sql = "INSERT INTO {$this->table} (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($values);
            
        } catch (Exception $e) {
            error_log("Error creating user permissions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update or create permissions for a user
     */
    public function updateOrCreateUserPermissions($userId, $permissions) {
        $existing = $this->getUserPermissions($userId);
        
        if ($existing) {
            return $this->updateUserPermissions($userId, $permissions);
        } else {
            return $this->createUserPermissions($userId, $permissions);
        }
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