<?php
// generate_excel_template.php - Generate Excel template for email campaigns

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=email_campaign_template.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Create Excel content
echo "<table border=\"1\">";
echo "<tr>";
echo "<th>Email</th>";
echo "<th>Name</th>";
echo "<th>Company</th>";
echo "<th>Custom Field 1</th>";
echo "<th>Custom Field 2</th>";
echo "</tr>";

// Sample data
$sampleData = [
    ["john.doe@example.com", "John Doe", "ABC Company", "VIP Customer", "New York"],
    ["jane.smith@example.com", "Jane Smith", "XYZ Corp", "Regular", "Los Angeles"],
    ["mike.johnson@example.com", "Mike Johnson", "Tech Solutions", "Premium", "Chicago"],
];

foreach ($sampleData as $row) {
    echo "<tr>";
    foreach ($row as $cell) {
        echo "<td>" . htmlspecialchars($cell) . "</td>";
    }
    echo "</tr>";
}

echo "</table>";