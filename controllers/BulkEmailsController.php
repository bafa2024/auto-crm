<?php
// BulkEmailsController.php
// Controller for handling bulk email operations

require_once __DIR__ . '/../models/BulkEmail.php';

class BulkEmailsController
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function sendBulkEmail($subject, $body, $recipients)
    {
        // Validate and sanitize input
        // ...

        // Use BulkEmail model to send emails
        $bulkEmail = new BulkEmail($this->db);
        return $bulkEmail->send($subject, $body, $recipients);
    }

    //get all the contacts from the database to list for selection
    public function getAllContacts()
    {
        $query = "SELECT id, name, email FROM email_recipients ORDER BY name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a contact by ID
    public function getContactById($id)
    {
        $query = "SELECT id, name, email FROM email_recipients WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

