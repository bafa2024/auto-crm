<?php
class BulkUpload extends BaseModel {
    protected $table = 'bulk_uploads';
    protected $fillable = [
        'filename', 'original_filename', 'file_size', 'total_records',
        'processed_records', 'successful_records', 'failed_records',
        'status', 'error_log', 'uploaded_by'
    ];
    
    public function getByUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE uploaded_by = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }
    
    public function updateProgress($id, $processed, $successful, $failed, $errors = null) {
        $data = [
            'processed_records' => $processed,
            'successful_records' => $successful,
            'failed_records' => $failed
        ];
        
        if ($errors) {
            $data['error_log'] = json_encode($errors);
        }
        
        if ($processed > 0 && $processed >= ($successful + $failed)) {
            $data['status'] = 'completed';
        }
        
        return $this->update($id, $data);
    }
}
