<?php
// Check if we can access the web interface
echo "Starting Local Server Check\n";
echo "===========================\n\n";

// Check PHP built-in server capability
echo "PHP Version: " . phpversion() . "\n";
echo "Current directory: " . __DIR__ . "\n";

// Check if we can start a built-in server
$port = 8080;
$host = 'localhost';

echo "\nStarting PHP built-in server...\n";
echo "URL: http://$host:$port\n";
echo "Document root: " . __DIR__ . "\n\n";

echo "To start the server manually, run:\n";
echo "cd \"" . __DIR__ . "\"\n";
echo "php -S $host:$port\n\n";

echo "Then access:\n";
echo "- Main app: http://$host:$port/\n";
echo "- Email upload: http://$host:$port/test_email_upload_form.php\n";
echo "- Database check: http://$host:$port/check_local_setup.php\n\n";

// Check if ports are available
echo "Checking port availability...\n";
$socket = @fsockopen($host, $port, $errno, $errstr, 5);
if ($socket) {
    echo "⚠ Port $port is already in use\n";
    fclose($socket);
} else {
    echo "✓ Port $port is available\n";
}

// Check alternative ports
$altPorts = [8081, 8082, 8083, 3000];
foreach ($altPorts as $altPort) {
    $socket = @fsockopen($host, $altPort, $errno, $errstr, 1);
    if (!$socket) {
        echo "✓ Alternative port $altPort is available\n";
        break;
    } else {
        fclose($socket);
    }
}

echo "\nTo test email upload functionality:\n";
echo "1. Start the PHP server using the command above\n";
echo "2. Open http://$host:$port/test_email_upload_form.php\n";
echo "3. Upload the test_contacts.csv file\n";
echo "4. Check the results in the database\n";
?>