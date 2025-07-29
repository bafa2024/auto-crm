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
            
            $result = $this->permissionModel->updateUserPermissions($userId, $permissions);
            
            if ($result) {
                $this->sendSuccess([], "Permissions updated successfully");
            } else {
                $this->sendError("Failed to update permissions");
            }
        } catch (Exception $e) {
            $this->sendError("Failed to update permissions: " . $e->getMessage());
        }
    }
} 