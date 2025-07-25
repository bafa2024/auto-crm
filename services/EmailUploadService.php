<?php

// Only include autoload if not already included
if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EmailUploadService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Process uploaded email file (CSV or Excel)
     */
    public function processUploadedFile($filePath, $campaignId = null, $originalFileName = null) {
        // Use original filename if provided, otherwise use the file path
        $fileName = $originalFileName ?? basename($filePath);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return $this->processCSV($filePath, $campaignId);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            return $this->processExcel($filePath, $campaignId);
        } else {
            return [
                'success' => false,
                'message' => 'Unsupported file format. Please upload CSV or Excel file.'
            ];
        }
    }
    
    /**
     * Simple email validation using filter_var (case-insensitive)
     */
    private function isValidEmail($email) {
        $email = trim($email);
        if (empty($email)) return false;
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Process CSV file
     */
    private function processCSV($filePath, $campaignId) {
        $contacts = [];
        $errors = [];
        $rowNumber = 0;
        
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            // Read headers
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                return [
                    'success' => false,
                    'message' => 'Invalid CSV file - no headers found'
                ];
            }
            
            // Clean headers
            $headers = array_map('trim', $headers);
            
            // Map headers to database fields
            $fieldMap = $this->mapHeaders($headers);
            
            if (!isset($fieldMap['email'])) {
                fclose($handle);
                return [
                    'success' => false,
                    'message' => 'Email column not found in CSV file'
                ];
            }
            
            // Process data rows
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowNumber++;
                
                if (count($data) < count($headers)) {
                    $errors[] = "Row $rowNumber: Incomplete data";
                    continue;
                }
                
                $contact = [];
                foreach ($fieldMap as $dbField => $csvIndex) {
                    if ($csvIndex !== null && isset($data[$csvIndex])) {
                        $contact[$dbField] = trim($data[$csvIndex]);
                    }
                }
                
                // Handle multiple emails
                $emails = [];
                if (!empty($contact['email'])) {
                    $emails = preg_split('/[;,]+/', $contact['email']);
                }
                $hasValid = false;
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (empty($email)) continue;
                    if (!$this->isValidEmail($email)) {
                        $errors[] = "Row $rowNumber: Invalid email address ('$email')";
                        continue;
                    }
                    $hasValid = true;
                    $newContact = $contact;
                    $newContact['email'] = $email;
                    $newContact['campaign_id'] = $campaignId;
                    $contacts[] = $newContact;
                }
                if (!$hasValid && !empty($contact['email'])) {
                    $errors[] = "Row $rowNumber: No valid email addresses found ('{$contact['email']}')";
                }
            }
            
            fclose($handle);
        } else {
            return [
                'success' => false,
                'message' => 'Failed to open file'
            ];
        }
        
        // Insert contacts into database
        $result = $this->insertContacts($contacts);
        
        return [
            'success' => true,
            'total_rows' => $rowNumber,
            'imported' => $result['imported'],
            'failed' => $result['failed'],
            'errors' => array_merge($errors, $result['errors'])
        ];
    }
    
    /**
     * Process Excel file (.xlsx, .xls) using PhpSpreadsheet
     */
    private function processExcel($filePath, $campaignId) {
        $contacts = [];
        $errors = [];
        $rowNumber = 0;
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            if (count($rows) < 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid Excel file - no data found'
                ];
            }
            
            // Read headers
            $headers = array_map('trim', array_values($rows[0]));
            $fieldMap = $this->mapHeaders($headers);
            if (!isset($fieldMap['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email column not found in Excel file'
                ];
            }
            
            // Process data rows
            foreach (array_slice($rows, 1) as $rowIdx => $row) {
                $rowNumber = $rowIdx + 2; // Excel rows are 1-indexed, + header
                $contact = [];
                foreach ($fieldMap as $dbField => $colIndex) {
                    if ($colIndex !== null && isset($row[$colIndex])) {
                        $contact[$dbField] = trim($row[$colIndex]);
                    }
                }
                // Handle multiple emails
                $emails = [];
                if (!empty($contact['email'])) {
                    $emails = preg_split('/[;,]+/', $contact['email']);
                }
                $hasValid = false;
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (empty($email)) continue;
                    if (!$this->isValidEmail($email)) {
                        $errors[] = "Row $rowNumber: Invalid email address ('$email')";
                        continue;
                    }
                    $hasValid = true;
                    $newContact = $contact;
                    $newContact['email'] = $email;
                    $newContact['campaign_id'] = $campaignId;
                    $contacts[] = $newContact;
                }
                if (!$hasValid && !empty($contact['email'])) {
                    $errors[] = "Row $rowNumber: No valid email addresses found ('{$contact['email']}')";
                }
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to read Excel file: ' . $e->getMessage()
            ];
        }
        // Insert contacts into database
        $result = $this->insertContacts($contacts);
        return [
            'success' => true,
            'total_rows' => $rowNumber,
            'imported' => $result['imported'],
            'failed' => $result['failed'],
            'errors' => array_merge($errors, $result['errors'])
        ];
    }

    // Helper to convert 0-based index to Excel column letter (A, B, C...)
    private function columnLetter($index) {
        $letters = range('A', 'Z');
        if ($index < 26) return $letters[$index];
        $first = $letters[floor($index / 26) - 1];
        $second = $letters[$index % 26];
        return $first . $second;
    }
    
    /**
     * Map CSV headers to database fields
     */
    private function mapHeaders($headers) {
        $fieldMap = [
            'email' => null,
            'name' => null,
            'company' => null,
            'dot' => null
        ];
        foreach ($headers as $index => $header) {
            $headerLower = strtolower(trim($header));
            // Map email field
            if ($headerLower === 'email' || strpos($headerLower, 'email') !== false) {
                $fieldMap['email'] = $index;
            }
            // Map name field
            elseif ($headerLower === 'name' || $headerLower === 'customer name' || $headerLower === 'full name' || $headerLower === 'fullname' || $headerLower === 'contact name') {
                $fieldMap['name'] = $index;
            }
            // Map company field
            elseif ($headerLower === 'company' || $headerLower === 'company name' || $headerLower === 'organization') {
                $fieldMap['company'] = $index;
            }
            // Map DOT field
            elseif ($headerLower === 'dot' || $headerLower === 'dot number' || $headerLower === 'dot_number') {
                $fieldMap['dot'] = $index;
            }
        }
        return $fieldMap;
    }
    
    /**
     * Insert contacts into database
     */
    private function insertContacts($contacts) {
        $imported = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($contacts as $contact) {
            try {
                // Normalize email to lowercase to handle case sensitivity
                $normalizedEmail = strtolower(trim($contact['email']));
                
                // Check if email already exists for this campaign (case-insensitive)
                if ($contact['campaign_id']) {
                    $checkSql = "SELECT id FROM email_recipients WHERE LOWER(email) = :email AND campaign_id = :campaign_id";
                    $stmt = $this->db->prepare($checkSql);
                    $stmt->execute([
                        ':email' => $normalizedEmail,
                        ':campaign_id' => $contact['campaign_id']
                    ]);
                    
                    if ($stmt->fetch()) {
                        $errors[] = "Email {$contact['email']} already exists in this campaign";
                        $failed++;
                        continue;
                    }
                }
                
                // Insert into email_recipients table with normalized email
                $currentTime = date('Y-m-d H:i:s');
                $sql = "INSERT INTO email_recipients (campaign_id, email, name, company, dot, status, tracking_id, created_at) 
                        VALUES (:campaign_id, :email, :name, :company, :dot, 'pending', :tracking_id, :created_at)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':campaign_id' => $contact['campaign_id'] ?? null,
                    ':email' => $normalizedEmail, // Store normalized email
                    ':name' => $contact['name'] ?? null,
                    ':company' => $contact['company'] ?? null,
                    ':dot' => $contact['dot'] ?? null,
                    ':tracking_id' => uniqid('track_', true),
                    ':created_at' => $currentTime
                ]);
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Failed to import {$contact['email']}: " . $e->getMessage();
                $failed++;
            }
        }
        
        // Update campaign recipient count if campaign_id is provided
        if (!empty($contacts[0]['campaign_id'])) {
            $this->updateCampaignRecipientCount($contacts[0]['campaign_id']);
        }
        
        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Update campaign recipient count
     */
    private function updateCampaignRecipientCount($campaignId) {
        try {
            // Check if total_recipients column exists, if not add it
            $checkColumn = $this->db->query("PRAGMA table_info(email_campaigns)");
            $columns = $checkColumn->fetchAll(PDO::FETCH_ASSOC);
            $hasColumn = false;
            
            foreach ($columns as $column) {
                if ($column['name'] === 'total_recipients') {
                    $hasColumn = true;
                    break;
                }
            }
            
            if (!$hasColumn) {
                $this->db->exec("ALTER TABLE email_campaigns ADD COLUMN total_recipients INTEGER DEFAULT 0");
            }
            
            $sql = "UPDATE email_campaigns 
                    SET total_recipients = (SELECT COUNT(*) FROM email_recipients WHERE campaign_id = :campaign_id)
                    WHERE id = :campaign_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':campaign_id' => $campaignId]);
        } catch (Exception $e) {
            // Log error but don't fail the import
            error_log("Failed to update campaign recipient count: " . $e->getMessage());
        }
    }
    
    /**
     * Export sample CSV template
     */
    public function exportTemplate() {
        $headers = ['Email', 'Name', 'Company'];
        $sampleData = [
            ['john.doe@example.com', 'John Doe', 'Example Corp'],
            ['jane.smith@example.com', 'Jane Smith', 'Tech Solutions'],
            ['bob.wilson@example.com', 'Bob Wilson', 'Marketing Agency']
        ];
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write sample data
        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    /**
     * Get upload statistics
     */
    public function getUploadStats() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(DISTINCT campaign_id) as total_campaigns,
                    COUNT(*) as total_recipients,
                    DATE(created_at) as upload_date,
                    COUNT(*) as daily_uploads
                FROM email_recipients 
                WHERE created_at >= date('now', '-30 days')
                GROUP BY DATE(created_at)
                ORDER BY upload_date DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting upload stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate file before processing
     */
    public function validateFile($filePath) {
        $errors = [];
        
        if (!file_exists($filePath)) {
            $errors[] = "File does not exist";
            return $errors;
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $errors[] = "Unsupported file format. Only CSV and Excel files are supported.";
            return $errors;
        }
        
        $fileSize = filesize($filePath);
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            $errors[] = "File size too large. Maximum size is 10MB.";
        }
        
        if ($fileSize == 0) {
            $errors[] = "File is empty.";
        }
        
        return $errors;
    }
}