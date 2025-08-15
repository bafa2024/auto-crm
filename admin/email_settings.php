<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /acrm/login');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'smtp_enabled' => $_POST['smtp_enabled'] ?? '0',
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '587',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
        'smtp_from_name' => $_POST['smtp_from_name'] ?? ''
    ];
    
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Save settings
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("INSERT INTO smtp_settings (setting_key, setting_value) 
                             VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    $success = "Email settings saved successfully!";
}

// Load current settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM smtp_settings");
$stmt->execute();
$currentSettings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <h2>Email Configuration</h2>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="smtp_enabled" 
                                       name="smtp_enabled" value="1" 
                                       <?php echo ($currentSettings['smtp_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="smtp_enabled">
                                    Enable SMTP (Recommended for reliable email delivery)
                                </label>
                            </div>
                            
                            <div id="smtp-settings">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_host'] ?? 'smtp.gmail.com'); ?>"
                                           placeholder="smtp.gmail.com">
                                    <small class="text-muted">Common: smtp.gmail.com, smtp.office365.com, smtp.sendgrid.net</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_port'] ?? '587'); ?>">
                                    <small class="text-muted">Common ports: 587 (TLS), 465 (SSL), 25 (Unencrypted)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select class="form-control" name="smtp_encryption">
                                        <option value="tls" <?php echo ($currentSettings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($currentSettings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="" <?php echo ($currentSettings['smtp_encryption'] ?? '') == '' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" name="smtp_username" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_username'] ?? ''); ?>"
                                           placeholder="your-email@gmail.com">
                                    <small class="text-muted">Usually your email address</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" name="smtp_password" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_password'] ?? ''); ?>"
                                           placeholder="••••••••">
                                    <small class="text-muted">For Gmail, use an App Password (not your regular password)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" name="smtp_from_email" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_from_email'] ?? 'noreply@yourdomain.com'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="smtp_from_name" 
                                           value="<?php echo htmlspecialchars($currentSettings['smtp_from_name'] ?? 'AutoDial Pro'); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <a href="test_email.php" class="btn btn-secondary">Test Email</a>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>Email Queue Status</h5>
                        <?php
                        // Get queue stats
                        $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
                        $stmt->execute();
                        $queueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if ($queueStats): ?>
                            <table class="table table-sm">
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                </tr>
                                <?php foreach ($queueStats as $stat): ?>
                                <tr>
                                    <td><?php echo ucfirst($stat['status']); ?></td>
                                    <td><?php echo $stat['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <p>No emails in queue</p>
                        <?php endif; ?>
                        
                        <a href="../process_email_queue.bat" class="btn btn-sm btn-info">Process Queue Now</a>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>Setup Instructions</h5>
                        <ol>
                            <li><strong>For Gmail:</strong>
                                <ul>
                                    <li>Enable 2-factor authentication</li>
                                    <li>Generate an App Password at <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
                                    <li>Use the app password (not your regular password)</li>
                                </ul>
                            </li>
                            <li><strong>For Office 365:</strong>
                                <ul>
                                    <li>Host: smtp.office365.com</li>
                                    <li>Port: 587</li>
                                    <li>Encryption: TLS</li>
                                </ul>
                            </li>
                            <li><strong>Without SMTP:</strong>
                                <ul>
                                    <li>Emails will be queued in database</li>
                                    <li>Run the queue processor regularly to attempt delivery</li>
                                    <li>Less reliable but works without external SMTP</li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('smtp_enabled').addEventListener('change', function() {
            document.getElementById('smtp-settings').style.display = this.checked ? 'block' : 'none';
        });
        
        // Initial state
        document.getElementById('smtp-settings').style.display = 
            document.getElementById('smtp_enabled').checked ? 'block' : 'none';
    </script>
</body>
</html>