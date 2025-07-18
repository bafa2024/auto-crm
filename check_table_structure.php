<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Email Campaigns Table Structure:\n";
echo "================================\n";

$stmt = $conn->query('PRAGMA table_info(email_campaigns)');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . "\n";
}

echo "\nEmail Recipients Table Structure:\n";
echo "=================================\n";

$stmt = $conn->query('PRAGMA table_info(email_recipients)');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['name'] . ' (' . $col['type'] . ')' . "\n";
}
?>