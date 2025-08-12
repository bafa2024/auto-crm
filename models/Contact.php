<?php
require_once "BaseModel.php";

class Contact extends BaseModel {
    protected $table = "contacts";
    protected $fillable = [
        "first_name", "last_name", "email", "phone", "company", "position", "status"
    ];
    
    public function search($searchTerm) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM {$this->table} WHERE 
                first_name LIKE ? OR 
                last_name LIKE ? OR 
                email LIKE ? OR 
                company LIKE ? OR 
                phone LIKE ?";
        
        $searchLike = "%{$searchTerm}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchLike, $searchLike, $searchLike, $searchLike, $searchLike]);
        
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function paginate($page = 1, $perPage = 10, $conditions = []) {
        if (!$this->db) return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereClause = '';
        $params = [];
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "{$field} = ?";
                $params[] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get paginated data
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = array_map([$this, 'hideFields'], $stmt->fetchAll());
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    public function count() {
        if (!$this->db) return 0;
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? (int)$result['count'] : 0;
    }
    
    public function all($limit = null) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function getLeadsByStatus() {
        if (!$this->db) return [];
        
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getLeadsBySource() {
        if (!$this->db) return [];
        
        $sql = "SELECT lead_source, COUNT(*) as count FROM {$this->table} GROUP BY lead_source";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function bulkInsert($contacts) {
        if (!$this->db || empty($contacts)) {
            return ['imported' => 0, 'errors' => []];
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($contacts as $index => $contact) {
            try {
                $data = $this->filterFillable($contact);
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                
                $fields = array_keys($data);
                $placeholders = str_repeat("?,", count($fields) - 1) . "?";
                
                $sql = "INSERT INTO {$this->table} (" . implode(",", $fields) . ") VALUES ({$placeholders})";
                $stmt = $this->db->prepare($sql);
                
                if ($stmt->execute(array_values($data))) {
                    $imported++;
                } else {
                    $errors[] = "Row " . ($index + 1) . ": Failed to insert";
                }
            } catch (Exception $e) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    /**
     * Search contacts for email composer autocomplete
     */
    public function searchForEmailComposer($query, $limit = 10) {
        if (!$this->db) return [];
        
        $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, company 
                FROM {$this->table} 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)
                AND email IS NOT NULL AND email != ''
                ORDER BY 
                    CASE 
                        WHEN email LIKE ? THEN 1
                        WHEN CONCAT(first_name, ' ', last_name) LIKE ? THEN 2
                        WHEN company LIKE ? THEN 3
                        ELSE 4
                    END,
                    first_name ASC
                LIMIT ?";
        
        $searchLike = "%{$query}%";
        $emailLike = "{$query}%";
        $nameLike = "{$query}%";
        $companyLike = "{$query}%";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $searchLike, $searchLike, $searchLike, $searchLike,
            $emailLike, $nameLike, $companyLike, $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all contacts for dropdown selection with optional search
     */
    public function getAllForDropdown($search = '', $page = 1, $limit = 100) {
        if (!$this->db) return ['data' => [], 'total' => 0, 'has_more' => false];
        
        $offset = ($page - 1) * $limit;
        $whereClause = "WHERE email IS NOT NULL AND email != ''";
        $params = [];
        
        if (!empty($search)) {
            $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $searchLike = "%{$search}%";
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get contacts
        $sql = "SELECT id, 
                       CONCAT(first_name, ' ', last_name) as name, 
                       email, 
                       company,
                       CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''), 
                              CASE WHEN company IS NOT NULL AND company != '' 
                                   THEN CONCAT(' (', company, ')') 
                                   ELSE '' 
                              END) as display_name
                FROM {$this->table} 
                {$whereClause}
                ORDER BY first_name ASC, last_name ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $contacts,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total,
            'page' => $page,
            'per_page' => $limit
        ];
    }
}