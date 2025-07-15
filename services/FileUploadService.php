<?php
class FileUploadService {
    private $db;
    private $allowedTypes;
    private $maxSize;
    private $uploadPath;
    
    public function __construct($database) {
        $this->db = $database;
        $this->allowedTypes = ALLOWED_UPLOAD_TYPES;
        $this->maxSize = MAX_UPLOAD_SIZE;
        $this->uploadPath = UPLOAD_PATH;
    }
    
    public function validateFile($file) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $this->getUploadError($file['error']);
        }
        
        if ($file['size'] > $this->maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . $this->formatBytes($this->maxSize);
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes);
        }
        
        return $errors;
    }
    
    public function uploadFile($file, $userId) {
        $errors = $this->validateFile($file);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '.' . $extension;
        $filepath = $this->uploadPath . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'errors' => ['Failed to save uploaded file']];
        }
        
        // Create upload record
        $bulkUploadModel = new BulkUpload($this->db);
        $uploadRecord = $bulkUploadModel->create([
            'filename' => $filename,
            'original_filename' => $file['name'],
            'file_size' => $file['size'],
            'status' => 'uploading',
            'uploaded_by' => $userId
        ]);
        
        return [
            'success' => true,
            'upload_id' => $uploadRecord['id'],
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
    
    public function processContactFile($uploadId, $filepath) {
        try {
            $bulkUploadModel = new BulkUpload($this->db);
            $upload = $bulkUploadModel->find($uploadId);
            
            if (!$upload) {
                throw new Exception('Upload record not found');
            }
            
            $bulkUploadModel->update($uploadId, ['status' => 'processing']);
            
            $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            $contacts = [];
            
            if ($extension === 'csv') {
                $contacts = $this->parseCSV($filepath);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $contacts = $this->parseExcel($filepath);
            }
            
            $bulkUploadModel->update($uploadId, ['total_records' => count($contacts)]);
            
            // Process contacts in batches
            $batchSize = 100;
            $processed = 0;
            $successful = 0;
            $failed = 0;
            $errors = [];
            
            $contactModel = new Contact($this->db);
            
            for ($i = 0; $i < count($contacts); $i += $batchSize) {
                $batch = array_slice($contacts, $i, $batchSize);
                $result = $contactModel->bulkInsert($batch);
                
                $processed += count($batch);
                $successful += $result['imported'];
                $failed += (count($batch) - $result['imported']);
                $errors = array_merge($errors, $result['errors'] ?? []);
                
                // Update progress
                $bulkUploadModel->updateProgress($uploadId, $processed, $successful, $failed, $errors);
            }
            
            // Clean up file
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $bulkUploadModel = new BulkUpload($this->db);
            $bulkUploadModel->update($uploadId, [
                'status' => 'failed',
                'error_log' => json_encode(['error' => $e->getMessage()])
            ]);
            
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
                    $headers = array_map(function($header) {
                        return strtolower(str_replace([' ', '-'], '_', trim($header)));
                    }, $data);
                } else {
                    $contact = [];
                    foreach ($headers as $index => $header) {
                        $value = isset($data[$index]) ? trim($data[$index]) : '';
                        $contact[$header] = $value;
                    }
                    
                    // Map common field variations
                    $contact = $this->mapContactFields($contact);
                    
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
        // composer require phpoffice/phpspreadsheet
        
        /*
        require_once 'vendor/autoload.php';
        use PhpOffice\PhpSpreadsheet\IOFactory;
        
        $spreadsheet = IOFactory::load($filepath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        $contacts = [];
        $headers = [];
        
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false);
            
            if ($row === 1) {
                $headers = array_map(function($header) {
                    return strtolower(str_replace([' ', '-'], '_', trim($header)));
                }, $rowData[0]);
            } else {
                $contact = [];
                foreach ($headers as $index => $header) {
                    $value = isset($rowData[0][$index]) ? trim($rowData[0][$index]) : '';
                    $contact[$header] = $value;
                }
                
                $contact = $this->mapContactFields($contact);
                
                if (!empty($contact['first_name']) && !empty($contact['phone'])) {
                    $contacts[] = $contact;
                }
            }
        }
        
        return $contacts;
        */
        
        // For now, return empty array
        return [];
    }
    
    private function mapContactFields($contact) {
        $fieldMap = [
            'fname' => 'first_name',
            'firstname' => 'first_name',
            'lname' => 'last_name',
            'lastname' => 'last_name',
            'phone_number' => 'phone',
            'mobile' => 'phone',
            'cell' => 'phone',
            'email_address' => 'email',
            'organization' => 'company',
            'job_title' => 'job_title',
            'title' => 'job_title',
            'position' => 'job_title'
        ];
        
        $mapped = [];
        foreach ($contact as $key => $value) {
            $mappedKey = $fieldMap[$key] ?? $key;
            $mapped[$mappedKey] = $value;
        }
        
        return $mapped;
    }
    
    private function getUploadError($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 