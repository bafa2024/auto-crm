<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Email Recipients Table Structure</h1>";

// Check table structure
try {
    $stmt = $conn->query("SHOW CREATE TABLE email_recipients");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Table Definition:</h2>";
    echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    
    // Check for unique constraints/indexes
    echo "<h2>Indexes:</h2>";
    $stmt = $conn->query("SHOW INDEXES FROM email_recipients");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr>";
        echo "<td>" . $index['Key_name'] . "</td>";
        echo "<td>" . $index['Column_name'] . "</td>";
        echo "<td>" . ($index['Non_unique'] ? 'No' : 'Yes') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>