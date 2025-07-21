<?php
// cron_test.php - Simple cron job test script
$logFile = __DIR__ . '/../logs/cron_test.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Cron test executed\n", FILE_APPEND);
?> 