<?php
require_once 'BaseController.php';

class AuthController extends BaseController {
    private $userModel;
    
    public function __construct($database) {
        parent::__construct($database);
        $this->userModel = new User($database);
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $this->sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->sendError('Email and password are required');
        }
        
        $user = $this->userModel->authenticate($email, $password);
        
        if (!$user) {
            $this->sendError('Invalid credentials', 401);
        }
        
        // Start session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        $this->sendSuccess([
            'user' => $user,
            'session_id' => session_id()
        ], 'Login successful');
    }
    
    public function logout() {
        session_start();
        session_destroy();
        
        $this->sendSuccess([], 'Logout successful');
    }
    
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        $required = ['email', 'password', 'first_name', 'last_name'];
        $errors = $this->validateRequired($data, $required);
        
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        
        // Check if email already exists
        if ($this->userModel->findBy('email', $data['email'])) {
            $this->sendError('Email already exists');
        }
        
        // Set default role
        $data['role'] = $data['role'] ?? 'agent';
        $data['status'] = 'active';
        
        $user = $this->userModel->create($data);
        
        if ($user) {
            $this->sendSuccess($user, 'User created successfully');
        } else {
            $this->sendError('Failed to create user', 500);
        }
    }
    
    public function getProfile() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Not authenticated', 401);
        }
        
        $user = $this->userModel->find($_SESSION['user_id']);
        
        if ($user) {
            $this->sendSuccess($user);
        } else {
            $this->sendError('User not found', 404);
        }
    }
    
    public function updateProfile() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Not authenticated', 401);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->sanitizeInput($input);
        
        // Remove fields that shouldn't be updated via profile
        unset($data['id'], $data['role'], $data['status'], $data['created_at'], $data['updated_at']);
        
        $user = $this->userModel->update($_SESSION['user_id'], $data);
        
        if ($user) {
            $this->sendSuccess($user, 'Profile updated successfully');
        } else {
            $this->sendError('Failed to update profile', 500);
        }
    }
} 