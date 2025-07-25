<?php
require_once 'BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/TeamMember.php';
require_once __DIR__ . '/../models/Team.php';

class UserController extends BaseController {
    private $userModel;
    private $teamMemberModel;
    private $teamModel;

    public function __construct($database) {
        parent::__construct($database);
        $this->userModel = new User($database);
        $this->teamMemberModel = new TeamMember($database);
        $this->teamModel = new Team($database);
    }

    // GET /api/employees/list?q=...
    public function listEmployees() {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $sql = "SELECT id, first_name, last_name, email, role, status FROM users";
        $params = [];
        if ($q) {
            $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR role LIKE ? OR status LIKE ?";
            $qLike = "%$q%";
            $params = [$qLike, $qLike, $qLike, $qLike, $qLike];
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        $this->sendSuccess($users);
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
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
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
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
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
} 