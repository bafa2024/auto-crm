<?php
/**
 * Email Diagnostic Tool for Production
 * This script helps diagnose email configuration issues on Hostinger
 */

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied. Admin login required.');
}

// Get server information
$serverInfo = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'HTTP Host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'Operating System' => PHP_OS,
    'mail() function' => function_exists('mail') ? 'Available' : 'Not Available',
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path'),
];

// Test email functionality
$testResults = [];

if (isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if ($testEmail) {
        // Test 1: Simple mail() function
        $subject = 'Test Email from AutoDial Pro - ' . date('Y-m-d H:i:s');
        $message = "This is a test email from your AutoDial Pro CRM installation.\n\n";
        $message .= "Server: " . $_SERVER['HTTP_HOST'] . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "PHP Version: " . phpversion() . "\n";
        
        // Try different from addresses
        $fromAddresses = [
            'noreply@' . $_SERVER['HTTP_HOST'],
            'noreply@' . str_replace('www.', '', $_SERVER['HTTP_HOST']),
            $_POST['test_email'], // Try using the recipient as sender
        ];
        
        foreach ($fromAddresses as $from) {
            $headers = "From: $from\r\n";
            $headers .= "Reply-To: $from\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            $result = @mail($testEmail, $subject, $message, $headers);
            
            $testResults[] = [
                'from' => $from,
                'to' => $testEmail,
                'result' => $result ? 'Success' : 'Failed',
                'error' => $result ? '' : error_get_last()['message'] ?? 'Unknown error'
            ];
            
            if ($result) {
                break; // Stop if one succeeds
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Diagnostic - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Email Diagnostic Tool</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Server Information</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <?php foreach ($serverInfo as $key => $value): ?>
                    <tr>
                        <th width="200"><?php echo $key; ?></th>
                        <td>
                            <?php 
                            if ($key === 'mail() function' && $value === 'Available') {
                                echo '<span class="badge bg-success">' . $value . '</span>';
                            } elseif ($key === 'mail() function' && $value === 'Not Available') {
                                echo '<span class="badge bg-danger">' . $value . '</span>';
                            } else {
                                echo $value ?: '<span class="text-muted">Not set</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Test Email Sending</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address:</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" required 
                               value="<?php echo $_POST['test_email'] ?? ''; ?>">
                        <small class="form-text text-muted">Enter your email to receive a test message</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                </form>
                
                <?php if (!empty($testResults)): ?>
                <div class="mt-4">
                    <h6>Test Results:</h6>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>From Address</th>
                                <th>To Address</th>
                                <th>Result</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testResults as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test['from']); ?></td>
                                <td><?php echo htmlspecialchars($test['to']); ?></td>
                                <td>
                                    <?php if ($test['result'] === 'Success'): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($test['error']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5>Recommended Configuration for Hostinger</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Use a domain-based email address:</strong> noreply@<?php echo $_SERVER['HTTP_HOST'] ?? 'yourdomain.com'; ?></li>
                    <li><strong>Create the email account in Hostinger:</strong>
                        <ul>
                            <li>Go to hPanel → Emails → Email Accounts</li>
                            <li>Create: noreply@<?php echo $_SERVER['HTTP_HOST'] ?? 'yourdomain.com'; ?></li>
                        </ul>
                    </li>
                    <li><strong>Set up SPF records:</strong>
                        <code>v=spf1 include:_spf.hostinger.com ~all</code>
                    </li>
                    <li><strong>Configure .env file:</strong>
                        <pre>MAIL_DRIVER=mail
MAIL_TEST_MODE=false
MAIL_FROM_ADDRESS=noreply@<?php echo $_SERVER['HTTP_HOST'] ?? 'yourdomain.com'; ?>

MAIL_FROM_NAME="AutoDial Pro"</pre>
                    </li>
                </ol>
            </div>
        </div>
        
        <div class="alert alert-warning">
            <strong>Security Note:</strong> Delete this file after troubleshooting is complete.
        </div>
        
        <div class="text-center mb-5">
            <a href="instant_email.php" class="btn btn-success">Go to Instant Email</a>
            <a href="dashboard" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>