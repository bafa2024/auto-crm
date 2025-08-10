<?php
require_once 'BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/TeamMember.php';
require_once __DIR__ . '/../models/Team.php';
require_once __DIR__ . '/../models/EmployeePermission.php';

class UserController extends BaseController {
    private $userModel;
    private $teamMemberModel;
    private $teamModel;
    private $permissionModel;

    public function __construct($database) {
        parent::__construct($database);
        $this->userModel = new User($database);
        $this->teamMemberModel = new TeamMember($database);
        $this->teamModel = new Team($database);
        $this->permissionModel = new EmployeePermission($database);
    }

    // GET /api/employees/list?q=...
    public function listEmployees() {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $sql = "SELECT id, first_name, last_name, email, role, status FROM users";
        $params = [];
        
        // Base condition to show all users (or filter by role if needed)
        $conditions = [];
        
        if ($q) {
            $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR role LIKE ? OR status LIKE ?)";
            $qLike = "%$q%";
            $params = [$qLike, $qLike, $qLike, $qLike, $qLike];
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY id DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            $this->sendSuccess($users);
        } catch (Exception $e) {
            error_log("Error fetching employees: " . $e->getMessage());
            $this->sendError("Failed to fetch employees", 500);
        }
    }

    // GET /api/employees/{id}
    public function getEmployee($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $sql = "SELECT id, first_name, last_name, email, role, status, password FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->sendSuccess($user);
            } else {
                $this->sendError('Employee not found', 404);
            }
        } catch (Exception $e) {
            error_log("Error fetching employee: " . $e->getMessage());
            $this->sendError("Failed to fetch employee", 500);
        }
    }

    // POST /api/employees/create
    public function createEmployee($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['first_name', 'last_name', 'email', 'password', 'role', 'status'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        if ($this->userModel->findBy('email', $data['email'])) {
            $this->sendError('Email already exists.', 409);
        }
        // User model will handle password hashing based on role
        $user = $this->userModel->create($data);
        if ($user) {
            $this->sendSuccess($user, 'Employee created');
        } else {
            $this->sendError('Failed to create employee', 500);
        }
    }

    // POST /api/employees/edit
    public function editEmployee($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['id', 'first_name', 'last_name', 'email', 'role', 'status'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status']
        ];
        if (!empty($data['password'])) {
            // Check if user is employee for plain text password
            $user = $this->userModel->find($data['id']);
            if ($user && in_array($user['role'], ['agent', 'manager'])) {
                $updateData['password'] = $data['password']; // Plain text for employees
            } else {
                $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
        }
        $user = $this->userModel->update($data['id'], $updateData);
        if ($user) {
            $this->sendSuccess($user, 'Employee updated');
        } else {
            $this->sendError('Failed to update employee', 500);
        }
    }

    // POST /api/employees/delete
    public function deleteEmployee($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['id'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        $deleted = $this->userModel->delete($data['id']);
        if ($deleted) {
            $this->sendSuccess([], 'Employee deleted');
        } else {
            $this->sendError('Failed to delete employee', 500);
        }
    }

    // GET /api/employees/{id}/teams
    public function getEmployeeTeams($userId) {
        $sql = "SELECT t.id, t.name FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $teams = $stmt->fetchAll();
        $this->sendSuccess($teams);
    }

    // POST /api/employees/{id}/add-to-team
    public function addToTeam($userId, $request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['team_id'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        $exists = $this->teamMemberModel->findByFields(['team_id' => $data['team_id'], 'user_id' => $userId]);
        if ($exists) {
            $this->sendError('User already in team', 409);
        }
        $member = $this->teamMemberModel->create([
            'team_id' => $data['team_id'],
            'user_id' => $userId,
            'role' => $data['role'] ?? 'worker',
            'status' => 'active'
        ]);
        if ($member) {
            $this->sendSuccess($member, 'Added to team');
        } else {
            $this->sendError('Failed to add to team', 500);
        }
    }

    // POST /api/employees/{id}/remove-from-team
    public function removeFromTeam($userId, $request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['team_id'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        $deleted = $this->teamMemberModel->deleteWhere(['team_id' => $data['team_id'], 'user_id' => $userId]);
        if ($deleted) {
            $this->sendSuccess([], 'Removed from team');
        } else {
            $this->sendError('Failed to remove from team', 500);
        }
    }
    
    // GET /api/employees/{id}/permissions
    public function getEmployeePermissions($userId) {
        try {
            // Check if user is admin
            if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
                $this->sendError("Unauthorized", 401);
            }
            
            $permissions = $this->permissionModel->getUserPermissions($userId);
            $this->sendSuccess($permissions, "Permissions retrieved successfully");
        } catch (Exception $e) {
            $this->sendError("Failed to get permissions: " . $e->getMessage());
        }
    }
    
    // PUT /api/employees/{id}/permissions
    public function updateEmployeePermissions($userId, $request = null) {
        try {
            // Check if user is admin
            if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
                $this->sendError("Unauthorized", 401);
            }
            
            $permissions = $request->body ?? json_decode(file_get_contents("php://input"), true);
            
            if (!$permissions) {
                $this->sendError("Invalid permissions data");
            }
            
            // Use updateOrCreate method to handle missing permissions records
            $result = $this->permissionModel->updateOrCreateUserPermissions($userId, $permissions);
            
            if ($result) {
                $this->sendSuccess([], "Permissions updated successfully");
            } else {
                $this->sendError("Failed to update permissions");
            }
        } catch (Exception $e) {
            $this->sendError("Failed to update permissions: " . $e->getMessage());
        }
    }

    // GET /api/employee/profile
    public function getEmployeeProfile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Not authenticated', 401);
        }
        
        try {
            $sql = "SELECT id, first_name, last_name, email, role, status, phone, company_name FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->sendSuccess(['profile' => $user]);
            } else {
                $this->sendError('Profile not found', 404);
            }
        } catch (Exception $e) {
            error_log("Error fetching employee profile: " . $e->getMessage());
            $this->sendError("Failed to fetch profile", 500);
        }
    }

    // PUT /api/employee/profile
    public function updateEmployeeProfile($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Not authenticated', 401);
        }
        
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        // Only allow updating certain fields
        $allowedFields = ['first_name', 'last_name', 'phone', 'company_name'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (empty($updateData)) {
            $this->sendError('No valid fields to update', 400);
        }
        
        try {
            $sql = "UPDATE users SET ";
            $fields = [];
            $values = [];
            
            foreach ($updateData as $field => $value) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
            
            $sql .= implode(', ', $fields);
            $sql .= ", updated_at = ? WHERE id = ?";
            $values[] = date('Y-m-d H:i:s');
            $values[] = $_SESSION['user_id'];
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                $this->sendSuccess(null, 'Profile updated successfully');
            } else {
                $this->sendError('Failed to update profile', 500);
            }
        } catch (Exception $e) {
            error_log("Error updating employee profile: " . $e->getMessage());
            $this->sendError("Failed to update profile", 500);
        }
    }

    // Get employee dashboard stats
    public function getEmployeeStats() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $this->sendError('User not authenticated', 401);
                return;
            }

            // Get total contacts for this employee
            $contactsStmt = $this->db->prepare("SELECT COUNT(*) as total FROM email_recipients");
            $contactsStmt->execute();
            $totalContacts = $contactsStmt->fetch()['total'] ?? 0;

            // Get emails sent this month (assuming we have email logs)
            $emailsStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM instant_email_log 
                WHERE sent_by = ? 
                AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $emailsStmt->execute([$userId]);
            $emailsSent = $emailsStmt->fetch()['total'] ?? 0;

            // Get emails sent today
            $todayEmailsStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM instant_email_log 
                WHERE sent_by = ? 
                AND DATE(created_at) = CURDATE()
            ");
            $todayEmailsStmt->execute([$userId]);
            $emailsToday = $todayEmailsStmt->fetch()['total'] ?? 0;

            // Get emails sent this week
            $weekEmailsStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM instant_email_log 
                WHERE sent_by = ? 
                AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
            ");
            $weekEmailsStmt->execute([$userId]);
            $emailsWeek = $weekEmailsStmt->fetch()['total'] ?? 0;

            // Get active campaigns (if employee has access)
            $campaignsStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM email_campaigns 
                WHERE created_by = ? 
                AND status = 'active'
            ");
            $campaignsStmt->execute([$userId]);
            $activeCampaigns = $campaignsStmt->fetch()['total'] ?? 0;

            // Calculate conversion rate (placeholder - you may need to adjust based on your metrics)
            $conversionRate = $totalContacts > 0 ? round(($emailsSent / $totalContacts) * 100, 1) : 0;

            $stats = [
                'total_contacts' => $totalContacts,
                'emails_sent' => $emailsSent,
                'emails_today' => $emailsToday,
                'emails_week' => $emailsWeek,
                'active_campaigns' => $activeCampaigns,
                'conversion_rate' => $conversionRate
            ];

            $this->sendSuccess($stats);
        } catch (Exception $e) {
            error_log("Error fetching employee stats: " . $e->getMessage());
            $this->sendError("Failed to fetch stats", 500);
        }
    }

    // Get recent contacts for employee
    public function getRecentContacts() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $this->sendError('User not authenticated', 401);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT id, name, email, company, dot as phone, 
                       DATE_FORMAT(created_at, '%M %d, %Y at %h:%i %p') as created_at
                FROM email_recipients 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $contacts = $stmt->fetchAll();

            $this->sendSuccess(['contacts' => $contacts]);
        } catch (Exception $e) {
            error_log("Error fetching recent contacts: " . $e->getMessage());
            $this->sendError("Failed to fetch recent contacts", 500);
        }
    }

    // Get recent activity for employee
    public function getRecentActivity() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $this->sendError('User not authenticated', 401);
                return;
            }

            $activities = [];

            // Get recent contact additions
            $contactsStmt = $this->db->prepare("
                SELECT 'contact_added' as type, 
                       CONCAT('Added contact: ', name) as title,
                       CONCAT('Contact ', name, ' was added to the system') as description,
                       DATE_FORMAT(created_at, '%M %d at %h:%i %p') as time
                FROM email_recipients 
                ORDER BY created_at DESC 
                LIMIT 3
            ");
            $contactsStmt->execute();
            $contacts = $contactsStmt->fetchAll();
            $activities = array_merge($activities, $contacts);

            // Get recent email sends
            $emailsStmt = $this->db->prepare("
                SELECT 'email_sent' as type,
                       CONCAT('Email sent to ', recipient_email) as title,
                       CONCAT('Instant email with subject: \"', subject, '\"') as description,
                       DATE_FORMAT(created_at, '%M %d at %h:%i %p') as time
                FROM instant_email_log 
                WHERE sent_by = ?
                ORDER BY created_at DESC 
                LIMIT 3
            ");
            $emailsStmt->execute([$userId]);
            $emails = $emailsStmt->fetchAll();
            $activities = array_merge($activities, $emails);

            // Sort all activities by time (most recent first)
            usort($activities, function($a, $b) {
                return strtotime($b['time']) - strtotime($a['time']);
            });

            // Limit to 5 most recent
            $activities = array_slice($activities, 0, 5);

            $this->sendSuccess(['activities' => $activities]);
        } catch (Exception $e) {
            error_log("Error fetching recent activity: " . $e->getMessage());
            $this->sendError("Failed to fetch recent activity", 500);
        }
    }
} 