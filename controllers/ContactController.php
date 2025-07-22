<?php
require_once 'BaseController.php';

class ContactController extends BaseController {
    private $contactModel;
    
    public function __construct($database) {
        parent::__construct($database);
        $this->contactModel = new Contact($database);
    }
    
    public function getContacts() {
        list($page, $perPage) = $this->getPaginationParams();
        $searchParams = $this->getSearchParams();
        
        $conditions = [];
        
        // Add search conditions
        if (!empty($searchParams['search'])) {
            // This would need to be implemented in the model
            $contacts = $this->contactModel->search($searchParams['search']);
            $this->sendSuccess($contacts);
            return;
        }
        
        // Add filters
        if (!empty($searchParams['filter']['status'])) {
            $conditions['status'] = $searchParams['filter']['status'];
        }
        
        if (!empty($searchParams['filter']['assigned_agent_id'])) {
            $conditions['assigned_agent_id'] = $searchParams['filter']['assigned_agent_id'];
        }
        
        $result = $this->contactModel->paginate($page, $perPage, $conditions);
        $this->sendSuccess($result);
    }
    
    public function getContact($id) {
        $contact = $this->contactModel->find($id);
        
        if ($contact) {
            $this->sendSuccess($contact);
        } else {
            $this->sendError('Contact not found', 404);
        }
    }
    
    public function createContact() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        $required = ['first_name', 'last_name', 'phone'];
        $errors = $this->validateRequired($data, $required);
        
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        
        // Add created_by from session
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
        $data['created_by'] = $_SESSION['user_id'] ?? 1;
        
        $contact = $this->contactModel->create($data);
        
        if ($contact) {
            $this->sendSuccess($contact, 'Contact created successfully');
        } else {
            $this->sendError('Failed to create contact', 500);
        }
    }
    
    public function updateContact($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        // Remove fields that shouldn't be updated
        unset($data['id'], $data['created_by'], $data['created_at']);
        
        $contact = $this->contactModel->update($id, $data);
        
        if ($contact) {
            $this->sendSuccess($contact, 'Contact updated successfully');
        } else {
            $this->sendError('Failed to update contact', 500);
        }
    }
    
    public function deleteContact($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        if ($this->contactModel->delete($id)) {
            $this->sendSuccess([], 'Contact deleted successfully');
        } else {
            $this->sendError('Failed to delete contact', 500);
        }
    }
    
    public function bulkUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        if (!isset($_FILES['file'])) {
            $this->sendError('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('File upload error');
        }
        
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $this->sendError('File too large');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, ALLOWED_UPLOAD_TYPES)) {
            $this->sendError('Invalid file type');
        }
        
        // Move file to upload directory
        $filename = uniqid() . '.' . $fileExtension;
        $filepath = UPLOAD_PATH . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->sendError('Failed to save file');
        }
        
        // Create bulk upload record
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
        $bulkUploadModel = new BulkUpload($this->db);
        $uploadRecord = $bulkUploadModel->create([
            'filename' => $filename,
            'original_filename' => $file['name'],
            'file_size' => $file['size'],
            'status' => 'processing',
            'uploaded_by' => $_SESSION['user_id'] ?? 1
        ]);
        
        // Process file
        $result = $this->processContactFile($filepath, $fileExtension, $uploadRecord['id']);
        
        if ($result['success']) {
            $this->sendSuccess($result, 'File processed successfully');
        } else {
            $this->sendError($result['message'], 500);
        }
    }
    
    private function processContactFile($filepath, $extension, $uploadId) {
        try {
            $contacts = [];
            
            if ($extension === 'csv') {
                $contacts = $this->parseCSV($filepath);
            } else {
                $contacts = $this->parseExcel($filepath);
            }
            
            $bulkUploadModel = new BulkUpload($this->db);
            $bulkUploadModel->update($uploadId, ['total_records' => count($contacts)]);
            
            $result = $this->contactModel->bulkInsert($contacts);
            
            $bulkUploadModel->updateProgress(
                $uploadId,
                count($contacts),
                $result['imported'],
                count($contacts) - $result['imported'],
                $result['errors'] ?? []
            );
            
            // Clean up file
            unlink($filepath);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function parseCSV($filepath) {
        $contacts = [];
        $headers = [];
        
        if (($handle = fopen($filepath, 'r')) !== FALSE) {
            $rowIndex = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if ($rowIndex === 0) {
                    $headers = array_map('trim', $data);
                } else {
                    $contact = [];
                    foreach ($headers as $index => $header) {
                        $contact[strtolower(str_replace(' ', '_', $header))] = $data[$index] ?? '';
                    }
                    
                    if (!empty($contact['first_name']) && !empty($contact['phone'])) {
                        $contacts[] = $contact;
                    }
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        return $contacts;
    }
    
    private function parseExcel($filepath) {
        // This would require PhpSpreadsheet library
        // For now, return empty array
        return [];
    }
    
    public function getStats() {
        $stats = [
            'total_contacts' => $this->contactModel->count(),
            'by_status' => $this->contactModel->getLeadsByStatus(),
            'by_source' => $this->contactModel->getLeadsBySource(),
            'recent_contacts' => $this->contactModel->all(10)
        ];
        
        $this->sendSuccess($stats);
    }
} 