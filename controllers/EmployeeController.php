<?php
require_once "BaseController.php";

class EmployeeController extends BaseController {
    private $userModel;
    private $contactModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            require_once __DIR__ . "/../models/User.php";
            require_once __DIR__ . "/../models/Contact.php";
            $this->userModel = new User($database);
            $this->contactModel = new Contact($database);
        }
    }
    
    /**
     * Get employee dashboard statistics
     */
    public function getStats() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        try {
            // Get total contacts assigned to this employee
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_contacts 
                FROM contacts 
                WHERE assigned_agent_id = ?
            ");
            $stmt->execute([$userId]);
            $totalContacts = $stmt->fetch()['total_contacts'];
            
            // Get new leads (contacts with status 'new')
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as new_leads 
                FROM contacts 
                WHERE assigned_agent_id = ? AND status = 'new'
            ");
            $stmt->execute([$userId]);
            $newLeads = $stmt->fetch()['new_leads'];
            
            // Get pending tasks (contacts that need follow-up)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pending_tasks 
                FROM contacts 
                WHERE assigned_agent_id = ? AND status IN ('contacted', 'qualified')
            ");
            $stmt->execute([$userId]);
            $pendingTasks = $stmt->fetch()['pending_tasks'];
            
            // Get completed tasks (converted contacts)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as completed_tasks 
                FROM contacts 
                WHERE assigned_agent_id = ? AND status = 'converted'
            ");
            $stmt->execute([$userId]);
            $completedTasks = $stmt->fetch()['completed_tasks'];
            
            $stats = [
                'totalContacts' => (int)$totalContacts,
                'newLeads' => (int)$newLeads,
                'pendingTasks' => (int)$pendingTasks,
                'completedTasks' => (int)$completedTasks
            ];
            
            $this->sendSuccess(['stats' => $stats], "Stats retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve stats: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent contacts assigned to the employee
     */
    public function getRecentContacts() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, company, status, 
                       last_contacted, created_at
                FROM contacts 
                WHERE assigned_agent_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess(['contacts' => $contacts], "Recent contacts retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve recent contacts: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get recent activity for the employee
     */
    public function getRecentActivity() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        try {
            // For now, we'll create a simple activity log based on contact updates
            // In a real application, you might have a separate activity_logs table
            $stmt = $this->db->prepare("
                SELECT 
                    'contact_created' as type,
                    CONCAT('New contact added: ', first_name, ' ', last_name) as description,
                    created_at
                FROM contacts 
                WHERE assigned_agent_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'contact_updated' as type,
                    CONCAT('Contact updated: ', first_name, ' ', last_name) as description,
                    updated_at as created_at
                FROM contacts 
                WHERE assigned_agent_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND updated_at != created_at
                
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId, $userId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess(['activities' => $activities], "Recent activity retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve recent activity: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get paginated contacts for the employee
     */
    public function getContacts() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        // Get query parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        
        $offset = ($page - 1) * $limit;
        
        try {
            // Build WHERE clause
            $whereConditions = ["assigned_agent_id = ?"];
            $params = [$userId];
            
            if (!empty($search)) {
                $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($status)) {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = implode(" AND ", $whereConditions);
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM contacts 
                WHERE $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get contacts
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, phone, company, job_title, 
                       status, notes, last_contacted, created_at, updated_at
                FROM contacts 
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate pagination
            $totalPages = ceil($total / $limit);
            
            $pagination = [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $total,
                'per_page' => $limit
            ];
            
            $this->sendSuccess([
                'contacts' => $contacts,
                'pagination' => $pagination
            ], "Contacts retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve contacts: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get a specific contact by ID
     */
    public function getContact($contactId) {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, phone, company, job_title, 
                       status, notes, last_contacted, created_at, updated_at
                FROM contacts 
                WHERE id = ? AND assigned_agent_id = ?
            ");
            $stmt->execute([$contactId, $userId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contact) {
                $this->sendError("Contact not found or access denied", 404);
            }
            
            $this->sendSuccess(['contact' => $contact], "Contact retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve contact: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get employee profile
     */
    public function getProfile() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        $userId = $_SESSION["user_id"];
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, phone, company_name, role, status, 
                       created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profile) {
                $this->sendError("Profile not found", 404);
            }
            
            $this->sendSuccess(['profile' => $profile], "Profile retrieved successfully");
            
        } catch (Exception $e) {
            $this->sendError("Failed to retrieve profile: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update employee profile
     */
    public function updateProfile() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        $userId = $_SESSION["user_id"];
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            $this->sendError("Invalid JSON data", 400);
        }
        
        // Sanitize input
        $data = $this->sanitizeInput($input);
        
        // Validate required fields
        if (empty($data["first_name"]) || empty($data["last_name"])) {
            $this->sendError("First name and last name are required", 400);
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?, company_name = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data["first_name"],
                $data["last_name"],
                $data["phone"] ?? null,
                $data["company_name"] ?? null,
                $userId
            ]);
            
            if ($result) {
                $this->sendSuccess([], "Profile updated successfully");
            } else {
                $this->sendError("Failed to update profile", 500);
            }
            
        } catch (Exception $e) {
            $this->sendError("Failed to update profile: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Change employee password
     */
    public function changePassword() {
        // Check if user is logged in and is an employee
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            $this->sendError("Unauthorized", 401);
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        $userId = $_SESSION["user_id"];
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            $this->sendError("Invalid JSON data", 400);
        }
        
        $currentPassword = $input["current_password"] ?? "";
        $newPassword = $input["new_password"] ?? "";
        
        if (empty($currentPassword) || empty($newPassword)) {
            $this->sendError("Current password and new password are required", 400);
        }
        
        if (strlen($newPassword) < 6) {
            $this->sendError("New password must be at least 6 characters long", 400);
        }
        
        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user["password"])) {
                $this->sendError("Current password is incorrect", 400);
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashedPassword, $userId]);
            
            if ($result) {
                $this->sendSuccess([], "Password changed successfully");
            } else {
                $this->sendError("Failed to change password", 500);
            }
            
        } catch (Exception $e) {
            $this->sendError("Failed to change password: " . $e->getMessage(), 500);
        }
    }
} 