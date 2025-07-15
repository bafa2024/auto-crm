<?php
class Contact extends BaseModel {
    protected $table = 'contacts';
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'company', 'job_title',
        'lead_source', 'interest_level', 'product_interest', 'status', 'notes',
        'consent_given', 'consent_date', 'consent_type', 'dnc_status',
        'assigned_agent_id', 'created_by'
    ];
    
    public function getByEmail($email) {
        return $this->findBy('email', $email);
    }
    
    public function getByPhone($phone) {
        return $this->findBy('phone', $phone);
    }
    
    public function getByAgent($agentId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE assigned_agent_id = ?");
        $stmt->execute([$agentId]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function getByStatus($status) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE status = ?");
        $stmt->execute([$status]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function search($query) {
        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE first_name LIKE ? 
            OR last_name LIKE ? 
            OR email LIKE ? 
            OR phone LIKE ? 
            OR company LIKE ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function getLeadsBySource() {
        $stmt = $this->db->query("
            SELECT lead_source, COUNT(*) as count 
            FROM {$this->table} 
            GROUP BY lead_source
        ");
        return $stmt->fetchAll();
    }
    
    public function getLeadsByStatus() {
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count 
            FROM {$this->table} 
            GROUP BY status
        ");
        return $stmt->fetchAll();
    }
    
    public function bulkInsert($contacts) {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (first_name, last_name, email, phone, company, job_title, lead_source, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $successCount = 0;
            $errors = [];
            
            foreach ($contacts as $index => $contact) {
                try {
                    $stmt->execute([
                        $contact['first_name'] ?? '',
                        $contact['last_name'] ?? '',
                        $contact['email'] ?? null,
                        $contact['phone'] ?? '',
                        $contact['company'] ?? null,
                        $contact['job_title'] ?? null,
                        $contact['lead_source'] ?? 'website',
                        $contact['created_by'] ?? 1
                    ]);
                    $successCount++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'imported' => $successCount,
                'total' => count($contacts),
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
