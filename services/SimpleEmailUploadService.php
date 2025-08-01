<?php
/**
 * Simple Email Upload Service - Works without PhpSpreadsheet
 * Handles CSV files only
 */
class SimpleEmailUploadService {
    private $db;
    
    public function __construct($database) {
        $this->db = is_object($database) && method_exists($database, 'getConnection') 
            ? $database->getConnection() 
            : $database;
    }
    
    /**
     * Process uploaded file (CSV only)
     */
    public function processUploadedFile($filePath, $campaignId = null, $originalFileName = null) {
        $fileName = $originalFileName ?? basename($filePath);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($extension !== 'csv') {
            return [
                'success' => false,
                'message' => 'This simple uploader only supports CSV files. For Excel files, please install PhpSpreadsheet.'
            ];
        }
        
        return $this->processCSV($filePath, $campaignId);
    }
    
    /**
     * Simple email validation
     */
    private function isValidEmail($email) {
        $email = trim($email);
        if (empty($email)) return false;
        
        // Normalize to lowercase
        $email = strtolower($email);
        
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Length check
        if (strlen($email) > 254) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Process CSV file
     */
    private function processCSV($filePath, $campaignId) {
        $contacts = [];
        $errors = [];
        $rowNumber = 0;
        
        // Open file
        $handle = @fopen($filePath, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'message' => 'Failed to open CSV file'
            ];
        }
        
        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        
        // Read headers
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Invalid CSV file - no headers found'
            ];
        }
        
        // Clean headers
        $headers = array_map('trim', $headers);
        
        // Map headers
        $fieldMap = $this->mapHeaders($headers);
        
        if (!isset($fieldMap['email'])) {
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Email column not found. Please ensure your CSV has an Email column.'
            ];
        }
        
        // Process rows
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Check data count
            if (count($data) < count($headers)) {
                $errors[] = "Row $rowNumber: Incomplete data";
                continue;
            }
            
            // Extract contact data
            $contact = [];
            foreach ($fieldMap as $dbField => $csvIndex) {
                if ($csvIndex !== null && isset($data[$csvIndex])) {
                    $contact[$dbField] = trim($data[$csvIndex]);
                } else {
                    $contact[$dbField] = '';
                }
            }
            
            // Handle multiple emails (separated by semicolon or comma)
            $emails = [];
            if (!empty($contact['email'])) {
                // Split by semicolon or comma
                $emailList = preg_split('/[;,]+/', $contact['email']);
                foreach ($emailList as $email) {
                    $email = trim($email);
                    if (!empty($email)) {
                        $emails[] = $email;
                    }
                }
            }
            
            // Process each email
            if (empty($emails)) {
                $errors[] = "Row $rowNumber: No email address found";
                continue;
            }
            
            foreach ($emails as $email) {
                if (!$this->isValidEmail($email)) {
                    $errors[] = "Row $rowNumber: Invalid email address ('$email')";
                    continue;
                }
                
                // Create contact record
                $newContact = $contact;
                $newContact['email'] = strtolower(trim($email));
                $newContact['campaign_id'] = $campaignId;
                $contacts[] = $newContact;
            }
        }
        
        fclose($handle);
        
        // Insert contacts
        $result = $this->insertContacts($contacts);
        
        return [
            'success' => true,
            'total_rows' => $rowNumber,
            'imported' => $result['imported'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'errors' => array_merge($errors, $result['errors'])
        ];
    }
    
    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter($line) {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }
        
        // Return delimiter with highest count
        return array_search(max($counts), $counts);
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
            
            // Email mapping
            if (in_array($headerLower, ['email', 'e-mail', 'e_mail', 'mail', 'email address', 'emailaddress'])) {
                $fieldMap['email'] = $index;
            }
            // Name mapping
            elseif (in_array($headerLower, ['name', 'customer name', 'full name', 'fullname', 'contact name', 'customer', 'contact'])) {
                $fieldMap['name'] = $index;
            }
            // Company mapping
            elseif (in_array($headerLower, ['company', 'company name', 'organization', 'business', 'firm', 'corp', 'corporation'])) {
                $fieldMap['company'] = $index;
            }
            // DOT mapping
            elseif (in_array($headerLower, ['dot', 'dot number', 'dot_number', 'dotnumber', 'dot #', 'dot#', 'dot id', 'dotid'])) {
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
        $skipped = 0;
        $errors = [];
        
        foreach ($contacts as $contact) {
            try {
                // Check if email exists
                $stmt = $this->db->prepare("SELECT id FROM email_recipients WHERE LOWER(email) = ?");
                $stmt->execute([strtolower($contact['email'])]);
                
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                // Insert new contact
                $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, status, tracking_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $contact['email'],
                    $contact['name'] ?? '',
                    $contact['company'] ?? '',
                    $contact['dot'] ?? '',
                    $contact['campaign_id'],
                    uniqid('track_', true)
                ]);
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Failed to import {$contact['email']}: " . $e->getMessage();
                $failed++;
            }
        }
        
        return [
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
}