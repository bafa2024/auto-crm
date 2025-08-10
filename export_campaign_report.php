<?php
// export_campaign_report.php - Export campaign report in CSV or PDF format

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';
require_once 'services/EmailCampaignService.php';

$database = new Database();
$db = $database->getConnection();
$campaignService = new EmailCampaignService($database);

$campaignId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

if (!$campaignId) {
    die("No campaign ID specified");
}

$campaign = $campaignService->getCampaignById($campaignId);
if (!$campaign) {
    die("Campaign not found");
}

// Get all campaign sends data
$sql = "SELECT 
        cs.recipient_email,
        r.name,
        r.company,
        cs.status,
        cs.sent_at,
        cs.opened_at,
        cs.clicked_at,
        CASE 
            WHEN cs.recipient_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$' THEN 'Invalid Email Format'
            WHEN cs.status = 'sent' THEN 'Delivered Successfully'
            WHEN cs.status = 'failed' THEN 'Delivery Failed'
            ELSE 'Pending'
        END as delivery_status
        FROM campaign_sends cs
        LEFT JOIN email_recipients r ON cs.recipient_id = r.id
        WHERE cs.campaign_id = ?
        ORDER BY cs.sent_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$campaignId]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="campaign_' . $campaignId . '_report_' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write campaign info
    fputcsv($output, ['Campaign Report: ' . $campaign['name']]);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Write summary stats
    $sent_count = count(array_filter($recipients, function($r) { return $r['status'] === 'sent'; }));
    $failed_count = count(array_filter($recipients, function($r) { return $r['status'] === 'failed'; }));
    $total = count($recipients);
    
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Total Recipients', $total]);
    fputcsv($output, ['Successfully Delivered', $sent_count, number_format(($sent_count/$total)*100, 1) . '%']);
    fputcsv($output, ['Failed Delivery', $failed_count, number_format(($failed_count/$total)*100, 1) . '%']);
    fputcsv($output, []);
    
    // Write headers
    fputcsv($output, ['Email', 'Name', 'Company', 'Delivery Status', 'Sent Date', 'Sent Time', 'Opened', 'Clicked']);
    
    // Write data
    foreach ($recipients as $recipient) {
        $sent_date = $recipient['sent_at'] ? date('Y-m-d', strtotime($recipient['sent_at'])) : '';
        $sent_time = $recipient['sent_at'] ? date('H:i:s', strtotime($recipient['sent_at'])) : '';
        $opened = $recipient['opened_at'] ? 'Yes (' . date('Y-m-d H:i', strtotime($recipient['opened_at'])) . ')' : 'No';
        $clicked = $recipient['clicked_at'] ? 'Yes (' . date('Y-m-d H:i', strtotime($recipient['clicked_at'])) . ')' : 'No';
        
        fputcsv($output, [
            $recipient['recipient_email'],
            $recipient['name'] ?: '',
            $recipient['company'] ?: '',
            $recipient['delivery_status'],
            $sent_date,
            $sent_time,
            $opened,
            $clicked
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // For PDF export, we'll use a simple HTML to PDF approach
    // In a production environment, you'd use a library like TCPDF or DomPDF
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Campaign Report - <?php echo htmlspecialchars($campaign['name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .delivered { color: #28a745; }
            .failed { color: #dc3545; }
            .invalid { color: #ffc107; }
            @media print {
                body { margin: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <h1>Campaign Report: <?php echo htmlspecialchars($campaign['name']); ?></h1>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <h2>Summary Statistics</h2>
        <?php
        $sent_count = count(array_filter($recipients, function($r) { return $r['status'] === 'sent'; }));
        $failed_count = count(array_filter($recipients, function($r) { return $r['status'] === 'failed'; }));
        $total = count($recipients);
        ?>
        <ul>
            <li>Total Recipients: <?php echo number_format($total); ?></li>
            <li>Successfully Delivered: <?php echo number_format($sent_count); ?> (<?php echo number_format(($sent_count/$total)*100, 1); ?>%)</li>
            <li>Failed Delivery: <?php echo number_format($failed_count); ?> (<?php echo number_format(($failed_count/$total)*100, 1); ?>%)</li>
        </ul>
        
        <h2>Detailed Recipients Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <th>Opened</th>
                    <th>Clicked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recipients as $recipient): ?>
                <tr>
                    <td><?php echo htmlspecialchars($recipient['recipient_email']); ?></td>
                    <td><?php echo htmlspecialchars($recipient['name'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($recipient['company'] ?: '-'); ?></td>
                    <td class="<?php echo $recipient['status']; ?>">
                        <?php echo $recipient['delivery_status']; ?>
                    </td>
                    <td><?php echo $recipient['sent_at'] ? date('Y-m-d H:i', strtotime($recipient['sent_at'])) : '-'; ?></td>
                    <td><?php echo $recipient['opened_at'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo $recipient['clicked_at'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>