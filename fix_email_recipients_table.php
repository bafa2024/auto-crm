<?php
require_once 'autoload.php';
require_once 'config/database.php';

echo "=== Fixing Email Recipients Table Schema ===\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check current table structure
    echo "Checking current table structure...\n";
    $stmt = $db->query("PRAGMA table_info(email_recipients)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['name']} ({$col['type']})\n";
    }
    
    // Check if campaign_id column exists
    $hasCampaignId = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'campaign_id') {
            $hasCampaignId = true;
            break;
        }
    }
    
    if (!$hasCampaignId) {
        echo "\nAdding campaign_id column...\n";
        $db->exec("ALTER TABLE email_recipients ADD COLUMN campaign_id INTEGER");
        echo "campaign_id column added successfully!\n";
    } else {
        echo "\ncampaign_id column already exists.\n";
    }
    
    // Check if other required columns exist
    $requiredColumns = ['tracking_id', 'status', 'created_at'];
    foreach ($requiredColumns as $colName) {
        $hasColumn = false;
        foreach ($columns as $col) {
            if ($col['name'] === $colName) {
                $hasColumn = true;
                break;
            }
        }
        
        if (!$hasColumn) {
            echo "Adding $colName column...\n";
            switch ($colName) {
                case 'tracking_id':
                    $db->exec("ALTER TABLE email_recipients ADD COLUMN tracking_id TEXT");
                    break;
                case 'status':
                    $db->exec("ALTER TABLE email_recipients ADD COLUMN status TEXT DEFAULT 'pending'");
                    break;
                case 'created_at':
                    $db->exec("ALTER TABLE email_recipients ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                    break;
            }
            echo "$colName column added successfully!\n";
        }
    }
    
    // Verify final structure
    echo "\nFinal table structure:\n";
    $stmt = $db->query("PRAGMA table_info(email_recipients)");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalColumns as $col) {
        echo "- {$col['name']} ({$col['type']})\n";
    }
    
    echo "\n=== Schema Fix Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 