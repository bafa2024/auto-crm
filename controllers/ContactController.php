<?php
require_once 'BaseController.php';

class ContactController extends BaseController {
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    public function list_all($page = 1, $per_page = 50, $search = '', $status = '', $company = '') {
        try {
            $offset = ($page - 1) * $per_page;
            
            // Build WHERE clause for filters
            $where_conditions = [];
            $params = [];
            
            // Search filter
            if (!empty($search)) {
                $where_conditions[] = "(name LIKE ? OR email LIKE ? OR company LIKE ? OR dot LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
            }
            
            // Status filter - since email_recipients doesn't have status column, we'll skip this filter
            // if (!empty($status)) {
            //     $where_conditions[] = "status = ?";
            //     $params[] = $status;
            // }
            
            // Company filter
            if (!empty($company)) {
                $where_conditions[] = "company = ?";
                $params[] = $company;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM email_recipients $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];
            
            // Get all contacts with pagination
            $sql = "SELECT * FROM email_recipients $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format contacts for frontend
            $formatted_contacts = [];
            foreach ($contacts as $contact) {
                $formatted_contacts[] = [
                    'id' => $contact['id'],
                    'name' => $contact['name'] ?? 'N/A',
                    'email' => $contact['email'] ?? 'N/A',
                    'company' => $contact['company'] ?? 'N/A',
                    'dot' => $contact['dot'] ?? 'N/A',
                    'status' => 'active', // Default status since table doesn't have status column
                    'created_at' => $contact['created_at'],
                    'avatar' => strtoupper(substr($contact['name'] ?? 'C', 0, 2))
                ];
            }
            
            return [
                'success' => true,
                'data' => $formatted_contacts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => ceil($total / $per_page)
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'company' => $company
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch all contacts: ' . $e->getMessage()
            ];
        }
    }

    
    





    

    // API Methods for contacts_api.php
    
    public function getContactsList($page = 1, $per_page = 10, $search = '', $status = '', $company = '', $sort_by = 'created_at', $sort_direction = 'DESC') {
        try {
            $offset = ($page - 1) * $per_page;
            
            // Build WHERE clause for filters
            $where_conditions = [];
            $params = [];
            
            // Search filter
            if (!empty($search)) {
                $where_conditions[] = "(name LIKE ? OR email LIKE ? OR company LIKE ? OR dot LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
            }
            
            // Status filter
            if (!empty($status)) {
                $where_conditions[] = "status = ?";
                $params[] = $status;
            }
            
            // Company filter
            if (!empty($company)) {
                $where_conditions[] = "company = ?";
                $params[] = $company;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM email_recipients $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];
            
            // Get contacts with pagination
            $sql = "SELECT * FROM email_recipients $where_clause ORDER BY $sort_by $sort_direction LIMIT ? OFFSET ?";
            $params[] = $per_page;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format contacts for frontend
            $formatted_contacts = [];
            foreach ($contacts as $contact) {
                $formatted_contacts[] = [
                    'id' => $contact['id'],
                    'name' => $contact['name'] ?? 'N/A',
                    'email' => $contact['email'] ?? 'N/A',
                    'company' => $contact['company'] ?? 'N/A',
                    'dot' => $contact['dot'] ?? 'N/A',
                    'status' => $contact['status'] ?? 'active',
                    'created_at' => $contact['created_at'],
                    'avatar' => strtoupper(substr($contact['name'] ?? 'C', 0, 2)),
                    'phone' => $contact['phone'] ?? ''
                ];
            }
            
            return [
                'success' => true,
                'data' => $formatted_contacts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => ceil($total / $per_page)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch contacts: ' . $e->getMessage()
            ];
        }
    }
    
    public function getContactById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_recipients WHERE id = ?");
            $stmt->execute([$id]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contact) {
                return [
                    'success' => false,
                    'message' => 'Contact not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $contact
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch contact details: ' . $e->getMessage()
            ];
        }
    }
    
    public function getContactHistory($id) {
        try {
            // For now, return mock history data
            // In a real implementation, you'd have a contact_history table
            $history = [
                [
                    'id' => 1,
                    'action' => 'Contact Created',
                    'description' => 'Contact was added to the system',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'type' => 'create'
                ],
                [
                    'id' => 2,
                    'action' => 'Email Sent',
                    'description' => 'Welcome email sent successfully',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'type' => 'email'
                ],
                [
                    'id' => 3,
                    'action' => 'Contact Updated',
                    'description' => 'Phone number updated',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'type' => 'update'
                ]
            ];
            
            return [
                'success' => true,
                'data' => $history
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch contact history: ' . $e->getMessage()
            ];
        }
    }
    
    public function getContactStats() {
        try {
            // Get total contacts
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM email_recipients");
            $total = $stmt->fetch()['total'];
            
            // Get active contacts (all contacts are considered active since we don't have status column)
            $active = $total;
            
            // Get contacts created this month - MySQL date functions
            $stmt = $this->db->query("SELECT COUNT(*) as new_month FROM email_recipients WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
            $new_month = $stmt->fetch()['new_month'];
            
            // Get total campaigns (mock data for now)
            $campaigns = 23;
            
            return [
                'success' => true,
                'data' => [
                    'total_contacts' => $total,
                    'active_contacts' => $active,
                    'new_this_month' => $new_month,
                    'campaigns' => $campaigns
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ];
        }
    }
    
    public function searchContacts($search_term) {
        try {
            if (empty($search_term)) {
                return ['success' => true, 'data' => []];
            }
            
            $search = '%' . $search_term . '%';
            $stmt = $this->db->prepare("SELECT * FROM email_recipients WHERE name LIKE ? OR email LIKE ? OR company LIKE ? OR dot LIKE ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$search, $search, $search, $search]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format contacts for frontend (same format as list_all)
            $formatted_contacts = [];
            foreach ($contacts as $contact) {
                $formatted_contacts[] = [
                    'id' => $contact['id'],
                    'name' => $contact['name'] ?? 'N/A',
                    'email' => $contact['email'] ?? 'N/A',
                    'company' => $contact['company'] ?? 'N/A',
                    'dot' => $contact['dot'] ?? 'N/A',
                    'status' => 'active', // Default status since table doesn't have status column
                    'created_at' => $contact['created_at'],
                    'avatar' => strtoupper(substr($contact['name'] ?? 'C', 0, 2))
                ];
            }
            
            return [
                'success' => true,
                'data' => $formatted_contacts
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to search contacts: ' . $e->getMessage()
            ];
        }
    }
    
    public function createContact($data) {
        try {
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM email_recipients WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }
            
            // Insert new contact without requiring campaign_id
            $stmt = $this->db->prepare("INSERT INTO email_recipients (name, email, company, dot, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['company'] ?? '',
                $data['dot'] ?? ''
            ]);
            
            $contact_id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Contact created successfully',
                'data' => ['id' => $contact_id]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create contact: ' . $e->getMessage()
            ];
        }
    }
    
    // Alias function for create_contact
    public function create_contact($data) {
        return $this->createContact($data);
    }
    
    public function updateContact($id, $data) {
        try {
            // Check if contact exists
            $stmt = $this->db->prepare("SELECT id FROM email_recipients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Contact not found'
                ];
            }
            
            // Update contact
            $stmt = $this->db->prepare("UPDATE email_recipients SET name = ?, email = ?, company = ?, dot = ? WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['company'] ?? '',
                $data['dot'] ?? '',
                $id
            ]);
            
            return [
                'success' => true,
                'message' => 'Contact updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update contact: ' . $e->getMessage()
            ];
        }
    }
    
    public function deleteContact($id) {
        try {
            // Check if contact exists
            $stmt = $this->db->prepare("SELECT id FROM email_recipients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Contact not found'
                ];
            }
            
            // Delete contact
            $stmt = $this->db->prepare("DELETE FROM email_recipients WHERE id = ?");
            $stmt->execute([$id]);
            
            return [
                'success' => true,
                'message' => 'Contact deleted successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete contact: ' . $e->getMessage()
            ];
        }
    }
    
    public function bulkDeleteContacts($contact_ids) {
        try {
            // Validate all IDs are integers
            $valid_ids = [];
            foreach ($contact_ids as $id) {
                $valid_id = intval($id);
                if ($valid_id > 0) {
                    $valid_ids[] = $valid_id;
                }
            }
            
            if (empty($valid_ids)) {
                return [
                    'success' => false,
                    'message' => 'No valid contact IDs provided'
                ];
            }
            
            // Delete contacts
            $placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
            $stmt = $this->db->prepare("DELETE FROM email_recipients WHERE id IN ($placeholders)");
            $stmt->execute($valid_ids);
            
            $deleted_count = $stmt->rowCount();
            
            return [
                'success' => true,
                'message' => "Successfully deleted $deleted_count contact(s)"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete contacts: ' . $e->getMessage()
            ];
        }
    }
    
    public function importContacts($file, $skip_header = true) {
        try {
            $data = $this->parseImportFile($file, $skip_header);
            $imported = 0;
            $failed = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($data as $contact) {
                try {
                    // Check if email already exists
                    $stmt = $this->db->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
                    $stmt->execute([strtolower(trim($contact['email']))]);
                    
                    if ($stmt->fetch()) {
                        $skipped++;
                        continue;
                    }
                    
                    // Insert new contact
                    $sql = "INSERT INTO email_recipients (email, name, company, dot, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        strtolower(trim($contact['email'])),
                        $contact['name'] ?? '',
                        $contact['company'] ?? '',
                        $contact['dot'] ?? ''
                    ]);
                    
                    $imported++;
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to import {$contact['email']}: " . $e->getMessage();
                    $failed++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Successfully imported $imported contacts.",
                'imported' => $imported,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to import contacts: ' . $e->getMessage()
            ];
        }
    }
    
    public function previewImport($file, $skip_header = true) {
        try {
            $data = $this->parseImportFile($file, $skip_header);
            $valid_records = 0;
            $invalid_records = 0;
            
            foreach ($data as &$row) {
                $row['isValid'] = $this->validateContactData($row);
                if ($row['isValid']) {
                    $valid_records++;
                } else {
                    $invalid_records++;
                }
            }
            
            return [
                'success' => true,
                'data' => $data,
                'stats' => [
                    'total' => count($data),
                    'valid' => $valid_records,
                    'invalid' => $invalid_records
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to preview import: ' . $e->getMessage()
            ];
        }
    }
    
    public function downloadTemplate($type = 'csv') {
        try {
            if ($type === 'csv') {
                $content = "Name,Email,Company,DOT\n";
                $content .= "John Doe,john.doe@example.com,ABC Company,123456\n";
                $content .= "Jane Smith,jane.smith@example.com,XYZ Corp,789012\n";
                
                return [
                    'success' => true,
                    'content' => $content,
                    'content_type' => 'text/csv',
                    'filename' => 'contacts_template.csv'
                ];
            } else if ($type === 'xlsx') {
                // For Excel, we'll create a simple CSV that Excel can open
                $content = "Name,Email,Company,DOT\n";
                $content .= "John Doe,john.doe@example.com,ABC Company,123456\n";
                $content .= "Jane Smith,jane.smith@example.com,XYZ Corp,789012\n";
                
                return [
                    'success' => true,
                    'content' => $content,
                    'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'filename' => 'contacts_template.xlsx'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid template type'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate template: ' . $e->getMessage()
            ];
        }
    }
    
    private function parseImportFile($file, $skip_header = true) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $data = [];
        
        if ($extension === 'csv') {
            $data = $this->parseCSVFile($file['tmp_name'], $skip_header);
        } else if (in_array($extension, ['xlsx', 'xls'])) {
            // For Excel files, convert to CSV first for simplicity
            $data = $this->parseExcelAsCSV($file['tmp_name'], $skip_header);
        } else {
            throw new Exception('Unsupported file format');
        }
        
        return $data;
    }
    
    private function parseCSVFile($filepath, $skip_header = true) {
        $data = [];
        $handle = fopen($filepath, 'r');
        
        if ($handle === false) {
            throw new Exception('Could not open CSV file');
        }
        
        $row_number = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            if ($skip_header && $row_number === 1) {
                continue;
            }
            
            if (count($row) >= 2) {
                $data[] = [
                    'name' => trim($row[0] ?? ''),
                    'email' => trim($row[1] ?? ''),
                    'company' => trim($row[2] ?? ''),
                    'dot' => trim($row[3] ?? '')
                ];
            }
        }
        
        fclose($handle);
        return $data;
    }
    
    private function parseExcelAsCSV($filepath, $skip_header = true) {
        // Simple Excel to CSV conversion
        $data = [];
        
        // Read file content
        $content = file_get_contents($filepath);
        $lines = explode("\n", $content);
        
        $row_number = 0;
        foreach ($lines as $line) {
            $row_number++;
            
            if ($skip_header && $row_number === 1) {
                continue;
            }
            
            // Simple CSV parsing
            $row = str_getcsv($line);
            if (count($row) >= 2) {
                $data[] = [
                    'name' => trim($row[0] ?? ''),
                    'email' => trim($row[1] ?? ''),
                    'company' => trim($row[2] ?? ''),
                    'dot' => trim($row[3] ?? '')
                ];
            }
        }
        
        return $data;
    }
    
    private function validateContactData($data) {
        // Only require email to be valid
        if (empty($data['email'])) {
            return false;
        }
        
        // Basic email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        return true;
    }
    
    // Original methods (keeping existing functionality)
    
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
                    c.id,
                    c.email,
                    CONCAT(c.first_name, ' ', c.last_name) as name,
                    c.company,
                    c.phone as dot,
                    c.created_at,
                    NULL as campaign_name
                FROM contacts c
                WHERE 1=1";
        
        $params = [];
        
        // Add search condition
        if (!empty($search)) {
            $sql .= " AND (CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add date range conditions
        if (!empty($dateFrom)) {
            $sql .= " AND DATE(c.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND DATE(c.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY c.created_at DESC";
        
        // Get total count for pagination
        $countSql = preg_replace('/^SELECT.*FROM/', 'SELECT COUNT(*) as total FROM', $sql);
        $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
        
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = ($result && isset($result['total'])) ? $result['total'] : 0;
        
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
        
        return $result;
    }
    
    public function getContact($id) {
        $sql = "SELECT * FROM contacts WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    

    
    public function bulkUpload() {
        if (!isset($_FILES['file'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }
        
        // Check file type
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Please upload CSV or Excel files only.'];
        }
        
        // Generate unique upload ID
        $uploadId = uniqid('upload_');
        
        // Move uploaded file
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $uploadId . '.' . $fileExtension;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file'];
        }
        
        // Process the file
        $result = $this->processContactFile($uploadPath, $fileExtension, $uploadId);
        
        // Clean up uploaded file
        unlink($uploadPath);
        
        return $result;
    }
    
    private function processContactFile($filepath, $extension, $uploadId) {
        try {
            $contacts = [];
            
            if (strtolower($extension) === 'csv') {
                $contacts = $this->parseCSV($filepath);
            } else {
                $contacts = $this->parseExcel($filepath);
            }
            
            if (empty($contacts)) {
                return ['success' => false, 'message' => 'No valid contacts found in file'];
            }
            
            // Insert contacts
            $inserted = 0;
            $errors = [];
            
            foreach ($contacts as $index => $contact) {
                try {
                    // Validate required fields
                    if (empty($contact['email'])) {
                        $errors[] = "Row " . ($index + 2) . ": Email is required";
                        continue;
                    }
                    
                    // Check if email already exists
                    $stmt = $this->db->prepare("SELECT id FROM contacts WHERE email = ?");
                    $stmt->execute([$contact['email']]);
                    if ($stmt->fetch()) {
                        $errors[] = "Row " . ($index + 2) . ": Email already exists";
                        continue;
                    }
                    
                    // Insert contact
                    $sql = "INSERT INTO contacts (first_name, last_name, email, phone, company, position, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $contact['first_name'] ?? '',
                        $contact['last_name'] ?? '',
                        $contact['email'],
                        $contact['phone'] ?? '',
                        $contact['company'] ?? '',
                        $contact['position'] ?? '',
                        'active'
                    ]);
                    
                    $inserted++;
                    
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            $message = "Successfully imported $inserted contacts.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }
            
            return [
                'success' => true,
                'message' => $message,
                'imported' => $inserted,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to process file: ' . $e->getMessage()];
        }
    }
    
    private function parseCSV($filepath) {
        $contacts = [];
        $handle = fopen($filepath, 'r');
        
        if ($handle === false) {
            throw new Exception('Could not open CSV file');
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new Exception('Invalid CSV format');
        }
        
        // Map headers to expected fields
        $headerMap = [];
                    foreach ($headers as $index => $header) {
            $header = strtolower(trim($header));
            switch ($header) {
                case 'first_name':
                case 'firstname':
                case 'first name':
                    $headerMap['first_name'] = $index;
                    break;
                case 'last_name':
                case 'lastname':
                case 'last name':
                    $headerMap['last_name'] = $index;
                    break;
                case 'email':
                case 'email address':
                    $headerMap['email'] = $index;
                    break;
                case 'phone':
                case 'phone number':
                case 'telephone':
                    $headerMap['phone'] = $index;
                    break;
                case 'company':
                case 'company name':
                    $headerMap['company'] = $index;
                    break;
                case 'position':
                case 'job title':
                case 'title':
                    $headerMap['position'] = $index;
                    break;
            }
        }
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) > 0 && !empty(array_filter($row))) {
                $contact = [];
                foreach ($headerMap as $field => $index) {
                    if (isset($row[$index])) {
                        $contact[$field] = trim($row[$index]);
                    }
                }
                if (!empty($contact)) {
                    $contacts[] = $contact;
                }
            }
        }
        
        fclose($handle);
        return $contacts;
    }
    
    private function parseExcel($filepath) {
        // This would require PhpSpreadsheet library
        // For now, return empty array
        return [];
    }
    
    public function getStats() {
        $stats = [
            'total' => $this->contactModel->count(),
            'by_status' => $this->contactModel->getLeadsByStatus(),
            'by_source' => $this->contactModel->getLeadsBySource()
        ];
        
        return $stats;
    }

    public function exportContacts() {
        // This method is not used in the API, so we'll return data instead of outputting
        $contacts = $this->contactModel->all();
        
        $filename = 'contacts_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Return data instead of outputting headers
        $output = [];
        $output[] = ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Position', 'Status', 'Created At'];
        
        foreach ($contacts as $contact) {
            $output[] = [
                $contact['id'],
                $contact['first_name'],
                $contact['last_name'],
                $contact['email'],
                $contact['phone'],
                $contact['company'],
                $contact['position'],
                $contact['status'],
                $contact['created_at']
            ];
        }
        
        return [
            'success' => true,
            'data' => $output,
            'filename' => $filename
        ];
    }
} 
?> 