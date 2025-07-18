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
                $campaignId = $campaign["id"];
                
                // Handle file upload after campaign creation
                if (isset($_FILES["email_file"])) {
                    $uploadResult = $this->handleFileUpload($_FILES["email_file"], $campaignId);
                    if (!$uploadResult["success"]) {
                        $this->sendError($uploadResult["message"], 400);
                        return;
                    }
                    
                    // Add upload results to response
                    $campaign["upload_results"] = $uploadResult;
                }
                
                $this->sendSuccess($campaign, "Campaign created successfully");
            } else {
                $this->sendError("Failed to create campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error creating campaign: " . $e->getMessage(), 500);
        }
    }
    
    private function handleFileUpload($file, $campaignId = null) {
        require_once __DIR__ . "/../services/EmailUploadService.php";
        
        $allowedTypes = [
            "text/csv", 
            "application/csv",
            "text/comma-separated-values",
            "application/vnd.ms-excel", 
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/octet-stream" // Some browsers send this for Excel files
        ];
        
        // Also check file extension as fallback
        $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowedExtensions = ["csv", "xlsx", "xls"];
        
        if (!in_array($file["type"], $allowedTypes) && !in_array($extension, $allowedExtensions)) {
            return ["success" => false, "message" => "Invalid file type. Please upload CSV or Excel file. (Detected: " . $file["type"] . ", Extension: " . $extension . ")"];
        }
        
        if ($file["size"] > 10 * 1024 * 1024) { // 10MB limit
            return ["success" => false, "message" => "File size exceeds 10MB limit."];
        }
        
        $uploadDir = __DIR__ . "/../uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . "_" . basename($file["name"]);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
            // Process the file using EmailUploadService
            $uploadService = new EmailUploadService($this->db);
            
            // Validate file first
            $validationErrors = $uploadService->validateFile($uploadPath);
            if (!empty($validationErrors)) {
                // Clean up uploaded file
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                return ["success" => false, "message" => implode(", ", $validationErrors)];
            }
            
            $result = $uploadService->processUploadedFile($uploadPath, $campaignId, $file["name"]);
            
            // Clean up uploaded file
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            return $result;
        }
        
        return ["success" => false, "message" => "Failed to upload file."];
    }
}