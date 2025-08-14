<?php
//intiate connection to BulkEmailsController

require_once __DIR__ . '/../controllers/BulkEmailsController.php';

$bulkEmailsController = new BulkEmailsController($db);

// Handle API requests
//first get all the contacts to be accessed in the view, by ajax call,

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $contacts = $bulkEmailsController->getAllContacts();
    echo json_encode($contacts);
}
