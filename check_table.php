<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=autocrm', 'root', '');
    $stmt = $pdo->query('DESCRIBE email_recipients');
    echo "Table structure for email_recipients:\n";
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?> 