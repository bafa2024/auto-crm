<?php
/**
 * Bulk Email API Endpoint
 * Handles all API requests for bulk email functionality
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/BulkEmailsController.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Initialize controller
try {
    $bulkEmailsController = new BulkEmailsController($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Controller initialization failed: ' . $e->getMessage()
    ]);
    exit();
}

// Parse request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'contacts':
                    // Get all contacts
                    try {
                        $contacts = $bulkEmailsController->getAllContacts();
                        echo json_encode([
                            'success' => true,
                            'data' => $contacts,
                            'count' => count($contacts)
                        ]);
                    } catch (Exception $e) {
                        throw new Exception('Failed to fetch contacts: ' . $e->getMessage());
                    }
                    break;
                    
                case 'contact':
                    // Get single contact by ID
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        throw new Exception('Contact ID is required');
                    }
                    $contact = $bulkEmailsController->getContactById($id);
                    echo json_encode([
                        'success' => true,
                        'data' => $contact
                    ]);
                    break;
                    
                case 'templates':
                    // Get email templates
                    $templates = $bulkEmailsController->getEmailTemplates();
                    echo json_encode([
                        'success' => true,
                        'data' => $templates
                    ]);
                    break;
                    
                case 'history':
                    // Get email history
                    $limit = $_GET['limit'] ?? 10;
                    $history = $bulkEmailsController->getEmailHistory($limit);
                    echo json_encode([
                        'success' => true,
                        'data' => $history
                    ]);
                    break;
                    
                default:
                    // Default action - get all contacts
                    $contacts = $bulkEmailsController->getAllContacts();
                    echo json_encode([
                        'success' => true,
                        'data' => $contacts
                    ]);
                    break;
            }
            break;
            
        case 'POST':
            // Get POST data
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'send':
                    // Send bulk email
                    $subject = $input['subject'] ?? '';
                    $body = $input['body'] ?? '';
                    $recipients = $input['recipients'] ?? [];
                    $fromName = $input['from_name'] ?? 'AutoDial Pro';
                    $fromEmail = $input['from_email'] ?? 'noreply@acrm.regrowup.ca';
                    
                    // Validate required fields
                    if (empty($subject) || empty($body) || empty($recipients)) {
                        throw new Exception('Subject, body, and recipients are required');
                    }
                    
                    $result = $bulkEmailsController->sendBulkEmail(
                        $subject,
                        $body,
                        $recipients,
                        $fromName,
                        $fromEmail
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Bulk email sent successfully',
                        'data' => $result
                    ]);
                    break;
                    
                case 'save_template':
                    // Save email template
                    $name = $input['name'] ?? '';
                    $subject = $input['subject'] ?? '';
                    $body = $input['body'] ?? '';
                    
                    if (empty($name) || empty($subject) || empty($body)) {
                        throw new Exception('Template name, subject, and body are required');
                    }
                    
                    $result = $bulkEmailsController->saveEmailTemplate($name, $subject, $body);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Template saved successfully',
                        'data' => $result
                    ]);
                    break;
                    
                case 'validate_emails':
                    // Validate email addresses
                    $emails = $input['emails'] ?? [];
                    $result = $bulkEmailsController->validateEmails($emails);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            // Update template
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'update_template') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('Template ID is required');
                }
                
                $name = $input['name'] ?? '';
                $subject = $input['subject'] ?? '';
                $body = $input['body'] ?? '';
                
                $result = $bulkEmailsController->updateEmailTemplate($id, $name, $subject, $body);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Template updated successfully',
                    'data' => $result
                ]);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            // Delete template
            if ($action === 'delete_template') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('Template ID is required');
                }
                
                $result = $bulkEmailsController->deleteEmailTemplate($id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Template deleted successfully',
                    'data' => $result
                ]);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
