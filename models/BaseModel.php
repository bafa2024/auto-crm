<?php
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function findBy($field, $value) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function all($limit = null, $offset = 0) {
        $sql = "SELECT * FROM {$this->table}";
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        return array_map([$this, 'hideFields'], $results);
    }
    
    public function create($data) {
        $data = $this->filterFillable($data);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute(array_values($data))) {
            return $this->find($this->db->lastInsertId());
        }
        return false;
    }
    
    public function update($id, $data) {
        $data = $this->filterFillable($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        
        $values = array_values($data);
        $values[] = $id;
        
        if ($stmt->execute($values)) {
            return $this->find($id);
        }
        return false;
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
    
    public function count($where = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    protected function hideFields($data) {
        if (empty($this->hidden) || !is_array($data)) {
            return $data;
        }
        
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }
    
    public function paginate($page = 1, $perPage = 25, $conditions = []) {
        $offset = ($page - 1) * $perPage;
        
        $whereClause = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereConditions = [];
            foreach ($conditions as $field => $value) {
                $whereConditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $whereClause = " WHERE " . implode(' AND ', $whereConditions);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}{$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get data
        $dataSql = "SELECT * FROM {$this->table}{$whereClause} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $this->db->prepare($dataSql);
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll();
        
        return [
            'data' => array_map([$this, 'hideFields'], $data),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }
}
