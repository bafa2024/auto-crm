<?php
require_once "BaseController.php";

class EmailCampaignController extends BaseController {
    private $campaignModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            require_once __DIR__ . "/../models/EmailCampaign.php";
            $this->campaignModel = new EmailCampaign($database);
        }
    }
    
    public function createCampaign() {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        // Handle file upload
        if (isset($_FILES["email_file"])) {
            $uploadResult = $this->handleFileUpload($_FILES["email_file"]);
            if (!$uploadResult["success"]) {
                $this->sendError($uploadResult["message"], 400);
            }
        }
        
        // Get form data
        $data = [
            "name" => $_POST["campaign_name"] ?? "",
            "subject" => $_POST["subject"] ?? "",
            "sender_name" => $_POST["from_name"] ?? "",
            "sender_email" => $_POST["from_email"] ?? "",
            "content" => $_POST["email_content"] ?? "",
            "created_by" => $_SESSION["user_id"] ?? 1,
            "status" => "draft"
        ];
        
        try {
            $campaign = $this->campaignModel->create($data);
            
            if ($campaign) {
                $this->sendSuccess($campaign, "Campaign created successfully");
            } else {
                $this->sendError("Failed to create campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error creating campaign: " . $e->getMessage(), 500);
        }
    }
    
    private function handleFileUpload($file) {
        $allowedTypes = ["text/csv", "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"];
        
        if (!in_array($file["type"], $allowedTypes)) {
            return ["success" => false, "message" => "Invalid file type. Please upload CSV or Excel file."];
        }
        
        if ($file["size"] > 5 * 1024 * 1024) { // 5MB limit
            return ["success" => false, "message" => "File size exceeds 5MB limit."];
        }
        
        $uploadDir = __DIR__ . "/../uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . "_" . basename($file["name"]);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
            // Process the file here (parse CSV/Excel)
            return ["success" => true, "file" => $fileName];
        }
        
        return ["success" => false, "message" => "Failed to upload file."];
    }
}