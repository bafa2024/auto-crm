<?php
/**
 * API Migration Endpoint for ACRM
 * URL: /migrate_api.php
 * 
 * Returns JSON response for programmatic migration
 */

// Simple security check
$secretKey = 'migrate_api_2025';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Access denied', 'usage' => '/migrate_api.php?key=' . $secretKey]));
}

require_once 'config/database.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'migrations' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    $db = (new Database())->getConnection();
    $response['database_connected'] = true;
    
    // Migration 1: Check and add can_send_instant_emails column
    $stmt = $db->query("DESCRIBE employee_permissions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('can_send_instant_emails', $columns)) {
        try {
            $sql = "ALTER TABLE employee_permissions 
                    ADD COLUMN can_send_instant_emails TINYINT(1) DEFAULT 1 
                    AFTER can_view_all_campaigns";
            $db->exec($sql);
            
            $response['migrations'][] = [
                'name' => 'add_can_send_instant_emails_column',
                'status' => 'success',
                'message' => 'Column added successfully'
            ];
        } catch (Exception $e) {
            $response['migrations'][] = [
                'name' => 'add_can_send_instant_emails_column',
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    } else {
        $response['migrations'][] = [
            'name' => 'add_can_send_instant_emails_column',
            'status' => 'skipped',
            'message' => 'Column already exists'
        ];
    }
    
    // Migration 2: Update existing permissions
    try {
        $stmt = $db->prepare("UPDATE employee_permissions SET can_send_instant_emails = 1 WHERE can_send_instant_emails IS NULL OR can_send_instant_emails = 0");
        $stmt->execute();
        $affected = $stmt->rowCount();
        
        $response['migrations'][] = [
            'name' => 'update_default_permissions',
            'status' => 'success',
            'message' => "Updated $affected user permissions"
        ];
    } catch (Exception $e) {
        $response['migrations'][] = [
            'name' => 'update_default_permissions',
            'status' => 'failed',
            'message' => $e->getMessage()
        ];
    }
    
    // Check if all migrations succeeded
    $allSuccess = true;
    foreach ($response['migrations'] as $migration) {
        if ($migration['status'] === 'failed') {
            $allSuccess = false;
            break;
        }
    }
    
    $response['success'] = $allSuccess;
    $response['message'] = $allSuccess ? 'All migrations completed successfully' : 'Some migrations failed';
    
} catch (Exception $e) {
    $response['database_connected'] = false;
    $response['error'] = $e->getMessage();
    $response['message'] = 'Database connection failed';
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
