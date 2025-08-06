<?php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get unique companies
    $stmt = $pdo->query("SELECT DISTINCT company FROM email_recipients WHERE company IS NOT NULL AND company != '' ORDER BY company");
    $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get counts for different statuses (simulated since we don't have status column)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM email_recipients");
    $totalContacts = $stmt->fetch()['total'];
    
    $response = [
        'success' => true,
        'companies' => $companies,
        'statusCounts' => [
            'all' => $totalContacts,
            'active' => $totalContacts, // All contacts are considered active
            'inactive' => 0,
            'pending' => 0
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>