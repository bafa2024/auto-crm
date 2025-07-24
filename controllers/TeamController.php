<?php
require_once 'BaseController.php';
require_once __DIR__ . '/../models/Team.php';
require_once __DIR__ . '/../models/TeamMember.php';
require_once __DIR__ . '/../models/WorkerPrivilege.php';

class TeamController extends BaseController {
    private $teamModel;
    private $teamMemberModel;
    private $privilegeModel;

    public function __construct($database) {
        parent::__construct($database);
        $this->teamModel = new Team($database);
        $this->teamMemberModel = new TeamMember($database);
        $this->privilegeModel = new WorkerPrivilege($database);
    }

    public function createTeam($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['name'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $data['created_by'] = $_SESSION['user_id'] ?? 1;
        $team = $this->teamModel->create($data);
        if ($team) {
            // Add creator as owner
            $this->teamMemberModel->create([
                'team_id' => $team['id'],
                'user_id' => $data['created_by'],
                'role' => 'owner',
                'status' => 'active'
            ]);
            $this->sendSuccess($team, 'Team created successfully');
        } else {
            $this->sendError('Failed to create team', 500);
        }
    }

    public function addMember($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['team_id', 'user_id'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        $data['role'] = $data['role'] ?? 'worker';
        $data['status'] = $data['status'] ?? 'active';
        $member = $this->teamMemberModel->create($data);
        if ($member) {
            $this->sendSuccess($member, 'Member added successfully');
        } else {
            $this->sendError('Failed to add member', 500);
        }
    }

    public function removeMember($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['team_id', 'user_id'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        // Remove member by team_id and user_id
        $deleted = $this->teamMemberModel->deleteWhere(['team_id' => $data['team_id'], 'user_id' => $data['user_id']]);
        if ($deleted) {
            $this->sendSuccess([], 'Member removed successfully');
        } else {
            $this->sendError('Failed to remove member', 500);
        }
    }

    public function setPrivilege($request = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        $input = $request && isset($request->body) ? $request->body : json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        $required = ['team_id', 'user_id', 'privilege', 'allowed'];
        $errors = $this->validateRequired($data, $required);
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        // Upsert privilege
        $existing = $this->privilegeModel->findByFields(['team_id' => $data['team_id'], 'user_id' => $data['user_id'], 'privilege' => $data['privilege']]);
        if ($existing) {
            $updated = $this->privilegeModel->update($existing['id'], ['allowed' => $data['allowed']]);
            $this->sendSuccess($updated, 'Privilege updated');
        } else {
            $created = $this->privilegeModel->create($data);
            $this->sendSuccess($created, 'Privilege set');
        }
    }

    public function getTeam($teamId) {
        $team = $this->teamModel->find($teamId);
        if ($team) {
            $this->sendSuccess($team);
        } else {
            $this->sendError('Team not found', 404);
        }
    }

    public function getMembers($teamId) {
        $members = $this->teamMemberModel->findAllBy('team_id', $teamId);
        $this->sendSuccess($members);
    }

    public function getPrivileges($teamId, $userId) {
        $privs = $this->privilegeModel->findAllByFields(['team_id' => $teamId, 'user_id' => $userId]);
        $this->sendSuccess($privs);
    }
} 