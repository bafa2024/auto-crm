<?php
require_once 'services/EmailUploadService.php';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="email_contacts_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create upload service and export template
$uploadService = new EmailUploadService(null);
$uploadService->exportTemplate();
?>