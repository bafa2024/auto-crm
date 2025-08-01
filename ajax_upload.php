<?php
// Disable error display to prevent breaking JSON output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Increase memory limit and execution time for uploads
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Include required files
    require_once 'config/database.php';
    
    try {
        $database = (new Database())->getConnection();
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    // Check if vendor autoload exists
    $vendorPath = __DIR__ . '/vendor/autoload.php';
    $useSimpleService = false;
    
    if (!file_exists($vendorPath)) {
        // Use simple service that doesn't require PhpSpreadsheet
        require_once 'services/SimpleEmailUploadService.php';
        $useSimpleService = true;
    } else {
        require_once 'services/EmailUploadService.php';
    }
    
    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_file'])) {
        // Use appropriate service
        if ($useSimpleService) {
            $uploadService = new SimpleEmailUploadService($database);
        } else {
            $uploadService = new EmailUploadService($database);
        }
        
        $file = $_FILES['email_file'];
        $campaignId = $_POST['campaign_id'] ?? null;
        
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'File upload failed: ' . $errorMsg]);
            exit;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload CSV or Excel file.']);
            exit;
        }
        
        // Process the file
        error_log("AJAX upload: Processing " . $file['name'] . " (" . $file['size'] . " bytes)");
        $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
        
        if ($result['success']) {
            $message = "Upload successful! Imported: {$result['imported']} contacts";
            if (isset($result['skipped']) && $result['skipped'] > 0) {
                $message .= ", Skipped: {$result['skipped']} (already imported)";
            }
            if (isset($result['failed']) && $result['failed'] > 0) {
                $message .= ", Failed: {$result['failed']}";
            }
            
            // Add note if using simple service
            if ($useSimpleService && $extension !== 'csv') {
                $message .= ". Note: Only CSV files are supported without PhpSpreadsheet.";
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $result['imported'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'errors' => $result['errors'] ?? []
            ]);
        } else {
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Upload failed: ' . ($result['message'] ?? 'Unknown error')
            ]);
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} catch (Exception $e) {
    error_log("AJAX upload error: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// End output buffering and send clean JSON
ob_end_flush();