<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    $stmt = $db->query('DESCRIBE employee_permissions');
    echo 'employee_permissions table structure:' . PHP_EOL;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '- ' . $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
