<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check if created_by column exists
$stmt = $conn->query("PRAGMA table_info(email_campaigns)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasCreatedBy = false;

foreach ($columns as $column) {
    if ($column['name'] === 'created_by') {
        $hasCreatedBy = true;
        break;
    }
}

if ($hasCreatedBy) {
    $conn->exec('CREATE INDEX IF NOT EXISTS idx_email_campaigns_created_by ON email_campaigns(created_by)');
    echo "✓ Index created successfully\n";
} else {
    echo "⚠ created_by column not found, skipping index\n";
}
?>