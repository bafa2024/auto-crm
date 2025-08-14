<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Email Recipients Duplicate Check</h1>";

// Check total count
$stmt = $conn->query("SELECT COUNT(*) as total FROM email_recipients");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "<p>Total records in email_recipients: <strong>$total</strong></p>";

// Check unique emails
$stmt = $conn->query("SELECT COUNT(DISTINCT LOWER(email)) as unique_emails FROM email_recipients");
$unique = $stmt->fetch(PDO::FETCH_ASSOC)['unique_emails'];
echo "<p>Unique email addresses: <strong>$unique</strong></p>";
echo "<p>Duplicate records: <strong>" . ($total - $unique) . "</strong></p>";

// Show duplicates
echo "<h2>Duplicate Emails (Top 20):</h2>";
$stmt = $conn->query("
    SELECT email, COUNT(*) as count 
    FROM email_recipients 
    GROUP BY LOWER(email) 
    HAVING count > 1 
    ORDER BY count DESC 
    LIMIT 20
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($duplicates)) {
    echo "<p>No duplicates found!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Email</th><th>Count</th></tr>";
    foreach ($duplicates as $dup) {
        echo "<tr><td>" . htmlspecialchars($dup['email']) . "</td><td>" . $dup['count'] . "</td></tr>";
    }
    echo "</table>";
}

// Check recent inserts
echo "<h2>Recent Email Recipients (Last 10):</h2>";
$stmt = $conn->query("SELECT id, email, name, created_at FROM email_recipients ORDER BY id DESC LIMIT 10");
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Created At</th></tr>";
foreach ($recent as $rec) {
    echo "<tr>";
    echo "<td>" . $rec['id'] . "</td>";
    echo "<td>" . htmlspecialchars($rec['email']) . "</td>";
    echo "<td>" . htmlspecialchars($rec['name']) . "</td>";
    echo "<td>" . $rec['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if campaign_sends is creating duplicates
echo "<h2>Campaign Sends Analysis:</h2>";
$stmt = $conn->query("
    SELECT campaign_id, recipient_email, COUNT(*) as send_count
    FROM campaign_sends
    GROUP BY campaign_id, LOWER(recipient_email)
    HAVING send_count > 1
    ORDER BY send_count DESC
    LIMIT 10
");

$dupSends = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($dupSends)) {
    echo "<p>No duplicate sends found in campaign_sends table!</p>";
} else {
    echo "<p><strong>Warning:</strong> Found duplicate sends for same email in campaigns:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campaign ID</th><th>Email</th><th>Times Sent</th></tr>";
    foreach ($dupSends as $send) {
        echo "<tr>";
        echo "<td>" . $send['campaign_id'] . "</td>";
        echo "<td>" . htmlspecialchars($send['recipient_email']) . "</td>";
        echo "<td>" . $send['send_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>