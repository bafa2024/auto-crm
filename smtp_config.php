<?php
session_start();
require_once 'config/database.php';

// Simple admin check (you can enhance this)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // For testing purposes, allow access. In production, implement proper admin auth.
    // die("Access denied");
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'smtp_enabled' => $_POST['smtp_enabled'] ?? '0',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO smtp_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        $message = "SMTP settings saved successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error saving settings: " . $e->getMessage();
        $messageType = "error";
    }
}

// Load current settings
$currentSettings = [];
try {
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM smtp_settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist, ignore
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Configuration - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">📧 SMTP Email Configuration</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="smtp_enabled" value="1" 
                                           id="smtp_enabled" <?= ($currentSettings['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="smtp_enabled">
                                        <strong>Enable SMTP Email</strong>
                                    </label>
                                </div>
                                <small class="text-muted">Check this to enable SMTP email sending</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="<?= htmlspecialchars($currentSettings['smtp_host'] ?? 'smtp.gmail.com') ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label">Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                               value="<?= htmlspecialchars($currentSettings['smtp_port'] ?? '587') ?>"
                                               placeholder="587">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?= ($currentSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                                    <option value="ssl" <?= ($currentSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="" <?= ($currentSettings['smtp_encryption'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="smtp_username" class="form-label">SMTP Username (Email Address)</label>
                                <input type="email" class="form-control" id="smtp_username" name="smtp_username" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_username'] ?? '') ?>"
                                       placeholder="your-email@gmail.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_password'] ?? '') ?>"
                                       placeholder="Your app password">
                                <small class="text-muted">For Gmail, use an App Password, not your regular password</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="smtp_from_email" class="form-label">From Email Address</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_from_email'] ?? '') ?>"
                                       placeholder="noreply@yoursite.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="smtp_from_name" class="form-label">From Name</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" 
                                       value="<?= htmlspecialchars($currentSettings['smtp_from_name'] ?? 'AutoCRM System') ?>"
                                       placeholder="AutoCRM System">
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                                <a href="test_instant_email.php" class="btn btn-outline-secondary">🧪 Test Email</a>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">📝 Quick Setup Guide</h6>
                                <p class="card-text small">
                                    <strong>For Gmail:</strong><br>
                                    1. Enable 2-Factor Authentication on your Google account<br>
                                    2. Go to Account Settings → Security → App passwords<br>
                                    3. Generate an app password for "Mail"<br>
                                    4. Use your Gmail address as username and the app password as password<br><br>
                                    
                                    <strong>Common Settings:</strong><br>
                                    • Gmail: smtp.gmail.com:587 (TLS)<br>
                                    • Outlook: smtp-mail.outlook.com:587 (STARTTLS)<br>
                                    • Yahoo: smtp.mail.yahoo.com:587 (TLS)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
