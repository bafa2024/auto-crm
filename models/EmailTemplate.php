<?php
require_once "BaseModel.php";

class EmailTemplate extends BaseModel {
    protected $table = "email_templates";
    protected $fillable = [
        "name", "category", "subject", "content", "thumbnail", 
        "variables", "created_by", "is_public"
    ];
    
    /**
     * Get all public templates or user's private templates
     */
    public function getAvailableTemplates($userId = null) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM {$this->table} WHERE is_public = 1";
        $params = [];
        
        if ($userId) {
            $sql .= " OR created_by = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY category, name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get templates by category
     */
    public function getByCategory($category, $userId = null) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM {$this->table} WHERE category = ? AND (is_public = 1";
        $params = [$category];
        
        if ($userId) {
            $sql .= " OR created_by = ?";
            $params[] = $userId;
        }
        
        $sql .= ") ORDER BY name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get template categories
     */
    public function getCategories() {
        if (!$this->db) return [];
        
        $stmt = $this->db->query("
            SELECT DISTINCT category 
            FROM {$this->table} 
            WHERE category IS NOT NULL 
            ORDER BY category
        ");
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}