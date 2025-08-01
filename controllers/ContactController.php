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
        $search = '';
        $dateFrom = '';
        $dateTo = '';
        
        // Get search parameters from query string
        if (isset($_GET['search'])) {
            $search = trim($_GET['search']);
        }
        
        if (isset($_GET['date_from'])) {
            $dateFrom = trim($_GET['date_from']);
        }
        
        if (isset($_GET['date_to'])) {
            $dateTo = trim($_GET['date_to']);
        }
        
        // Build query with search and date filters
        $sql = "SELECT 
                    er.id,
                    er.email,
                    er.name,
                    er.company,
                    er.dot,
                    er.created_at,
                    ec.name as campaign_name
                FROM email_recipients er
                LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
                WHERE 1=1";
        
        $params = [];
        
        // Add search condition
        if (!empty($search)) {
            $sql .= " AND (er.name LIKE ? OR er.email LIKE ? OR er.company LIKE ? OR er.dot LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add date range conditions
        if (!empty($dateFrom)) {
            $sql .= " AND DATE(er.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND DATE(er.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY er.created_at DESC";
        
        // Get total count for pagination
        $countSql = str_replace("SELECT er.id, er.email, er.name, er.company, er.dot, er.created_at, ec.name as campaign_name", "SELECT COUNT(*) as total", $sql);
        $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
        
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Add pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [
            'data' => $contacts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $perPage)
            ],
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ];
        
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
        
        // Note: created_by field doesn't exist in contacts table
        
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
        unset($data['id'], $data['created_at']);
        
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
    
    public function deleteAllContacts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            // Get total count before deletion
            $totalCount = $this->contactModel->getTotalCount();
            
            if ($totalCount === 0) {
                $this->sendError('No contacts to delete', 400);
            }
            
            // Delete all contacts
            $deletedCount = $this->contactModel->deleteAll();
            
            if ($deletedCount > 0) {
                $this->sendSuccess([
                    'deleted_count' => $deletedCount,
                    'total_count' => $totalCount
                ], "Successfully deleted all {$deletedCount} contacts");
            } else {
                $this->sendError('Failed to delete contacts', 500);
            }
        } catch (Exception $e) {
            $this->sendError('Error deleting all contacts: ' . $e->getMessage(), 500);
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

    public function exportContacts() {
        try {
            // Get search parameters
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            
            // Build query
            $sql = "SELECT first_name, last_name, email, phone, company, status, created_at FROM contacts WHERE 1=1";
            $params = [];
            
            if ($search) {
                $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)";
                $searchLike = "%$search%";
                $params = array_merge($params, [$searchLike, $searchLike, $searchLike, $searchLike]);
            }
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $contacts = $stmt->fetchAll();
            
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="contacts_' . date('Y-m-d_H-i-s') . '.csv"');
            
            // Create CSV output
            $output = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($output, ['First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Status', 'Created Date']);
            
            // Add data
            foreach ($contacts as $contact) {
                fputcsv($output, [
                    $contact['first_name'],
                    $contact['last_name'],
                    $contact['email'],
                    $contact['phone'],
                    $contact['company'],
                    $contact['status'],
                    $contact['created_at']
                ]);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            $this->sendError('Failed to export contacts: ' . $e->getMessage());
        }
    }
} 