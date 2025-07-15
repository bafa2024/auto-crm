<?php
class EmailTemplate extends BaseModel {
    protected $table = 'email_templates';
    protected $fillable = [
        'name', 'subject', 'content', 'template_type', 'is_active', 'created_by'
    ];
    
    public function getByType($type) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE template_type = ? AND is_active = 1");
        $stmt->execute([$type]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function getActive() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1");
        $stmt->execute();
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
}
