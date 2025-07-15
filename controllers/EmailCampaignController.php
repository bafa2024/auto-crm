<?php
require_once 'BaseController.php';

class EmailCampaignController extends BaseController {
    private $campaignModel;
    private $contactModel;
    private $templateModel;
    
    public function __construct($database) {
        parent::__construct($database);
        $this->campaignModel = new EmailCampaign($database);
        $this->contactModel = new Contact($database);
        $this->templateModel = new EmailTemplate($database);
    }
    
    public function getCampaigns() {
        list($page, $perPage) = $this->getPaginationParams();
        $result = $this->campaignModel->paginate($page, $perPage);
        $this->sendSuccess($result);
    }
    
    public function getCampaign($id) {
        $campaign = $this->campaignModel->getWithStats($id);
        
        if ($campaign) {
            $this->sendSuccess($campaign);
        } else {
            $this->sendError('Campaign not found', 404);
        }
    }
    
    public function createCampaign() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        $required = ['name', 'subject', 'content', 'sender_name', 'sender_email'];
        $errors = $this->validateRequired($data, $required);
        
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        
        session_start();
        $data['created_by'] = $_SESSION['user_id'] ?? 1;
        $data['status'] = 'draft';
        
        $campaign = $this->campaignModel->create($data);
        
        if ($campaign) {
            $this->sendSuccess($campaign, 'Campaign created successfully');
        } else {
            $this->sendError('Failed to create campaign', 500);
        }
    }
    
    public function updateCampaign($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        // Remove fields that shouldn't be updated
        unset($data['id'], $data['created_by'], $data['created_at']);
        
        $campaign = $this->campaignModel->update($id, $data);
        
        if ($campaign) {
            $this->sendSuccess($campaign, 'Campaign updated successfully');
        } else {
            $this->sendError('Failed to update campaign', 500);
        }
    }
    
    public function deleteCampaign($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }
        
        if ($this->campaignModel->delete($id)) {
            $this->sendSuccess([], 'Campaign deleted successfully');
        } else {
            $this->sendError('Failed to delete campaign', 500);
        }
    }
    
    public function addRecipients($campaignId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $contactIds = $input['contact_ids'] ?? [];
        
        if (empty($contactIds)) {
            $this->sendError('No contacts specified');
        }
        
        if ($this->campaignModel->addRecipients($campaignId, $contactIds)) {
            $this->sendSuccess([], 'Recipients added successfully');
        } else {
            $this->sendError('Failed to add recipients', 500);
        }
    }
    
    public function getRecipients($campaignId) {
        $status = $_GET['status'] ?? null;
        $recipients = $this->campaignModel->getRecipients($campaignId, $status);
        $this->sendSuccess($recipients);
    }
    
    public function sendCampaign($campaignId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $campaign = $this->campaignModel->find($campaignId);
        
        if (!$campaign) {
            $this->sendError('Campaign not found', 404);
        }
        
        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            $this->sendError('Campaign cannot be sent in current status');
        }
        
        // Update campaign status
        $this->campaignModel->update($campaignId, ['status' => 'sending']);
        
        // Queue emails for sending
        $emailService = new EmailService($this->db);
        $result = $emailService->queueCampaignEmails($campaignId);
        
        if ($result['success']) {
            $this->sendSuccess($result, 'Campaign queued for sending');
        } else {
            $this->sendError($result['message'], 500);
        }
    }
    
    public function getTemplates() {
        $templates = $this->templateModel->getActive();
        $this->sendSuccess($templates);
    }
    
    public function createTemplate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        $required = ['name', 'subject', 'content'];
        $errors = $this->validateRequired($data, $required);
        
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        
        session_start();
        $data['created_by'] = $_SESSION['user_id'] ?? 1;
        
        $template = $this->templateModel->create($data);
        
        if ($template) {
            $this->sendSuccess($template, 'Template created successfully');
        } else {
            $this->sendError('Failed to create template', 500);
        }
    }
    
    public function getCampaignStats() {
        $stats = $this->campaignModel->getCampaignStats();
        $this->sendSuccess($stats);
    }
} 