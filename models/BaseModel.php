<?php
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = "id";
    protected $fillable = [];
    protected $hidden = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function find($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function findBy($field, $value) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function create($data) {
        if (!$this->db) return false;
        
        $data = $this->filterFillable($data);
        $data["created_at"] = date("Y-m-d H:i:s");
        $data["updated_at"] = date("Y-m-d H:i:s");
        
        $fields = array_keys($data);
        $placeholders = str_repeat("?,", count($fields) - 1) . "?";
        
        $sql = "INSERT INTO {$this->table} (" . implode(",", $fields) . ") VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute(array_values($data))) {
            return $this->find($this->db->lastInsertId());
        }
        return false;
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

    public function findByFields($fields) {
        if (!$this->db) return null;
        $where = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $where[] = "$k = ?";
            $params[] = $v;
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? $this->hideFields($result) : null;
    }
    public function findAllBy($field, $value) {
        if (!$this->db) return [];
        $sql = "SELECT * FROM {$this->table} WHERE $field = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    public function findAllByFields($fields) {
        if (!$this->db) return [];
        $where = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $where[] = "$k = ?";
            $params[] = $v;
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    public function deleteWhere($fields) {
        if (!$this->db) return false;
        $where = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $where[] = "$k = ?";
            $params[] = $v;
        }
        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}