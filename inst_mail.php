<?php
/**
 * Instant Mail - Simple Email Sending Interface
 * Send emails directly using PHP mail() function
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/EmailService.php';

$message = '';
$error = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: views/auth/login.php');
    exit;
}

// Process email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $to = trim($_POST['to']);
        $subject = trim($_POST['subject']);
        $message_content = $_POST['message'];
        $from_name = trim($_POST['from_name'] ?? 'AutoDial Pro');
        $from_email = trim($_POST['from_email'] ?? '');
        
        // If no from email provided, use domain-based email
        if (empty($from_email)) {
            if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost') {
                $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);
                $from_email = 'noreply@' . $domain;
            } else {
                $from_email = 'noreply@localhost';
            }
        }
        
        // Validate inputs
        if (empty($to) || empty($subject) || empty(trim($message_content))) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Initialize database and email service
            $database = new Database();
            $db = $database->getConnection();
            $emailService = new EmailService($db);
            
            // Send email
            $result = $emailService->sendInstantEmail([
                'to' => $to,
                'subject' => $subject,
                'message' => $message_content,
                'from_name' => $from_name,
                'from_email' => $from_email
            ]);
            
            if ($result === true) {
                $message = 'Email sent successfully to ' . htmlspecialchars($to);
                // Clear form after successful send
                $_POST = [];
            } else {
                $error = 'Failed to send email. Please check your email configuration.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get domain for default from address
$domain = 'localhost';
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost') {
    $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Mail - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .email-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .email-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        .email-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .email-body {
            padding: 40px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        .btn-send {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-clear {
            background: #f5f5f5;
            border: none;
            color: #666;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .btn-clear:hover {
            background: #e0e0e0;
            color: #333;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .template-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .template-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .template-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <a href="dashboard" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
    
    <div class="email-container">
        <div class="email-card">
            <div class="email-header">
                <h1><i class="bi bi-envelope-paper"></i> Instant Mail</h1>
                <p>Send emails quickly and easily</p>
            </div>
            
            <div class="email-body">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="emailForm">
                    <div class="mb-4">
                        <label for="to" class="form-label">
                            <i class="bi bi-person"></i> To Email Address *
                        </label>
                        <input type="email" class="form-control" id="to" name="to" 
                               value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>" 
                               placeholder="recipient@example.com" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="subject" class="form-label">
                            <i class="bi bi-chat-square-text"></i> Subject *
                        </label>
                        <input type="text" class="form-control" id="subject" name="subject" 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                               placeholder="Enter email subject" required>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="from_name" class="form-label">
                                <i class="bi bi-person-badge"></i> From Name
                            </label>
                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                   value="<?php echo htmlspecialchars($_POST['from_name'] ?? 'AutoDial Pro'); ?>" 
                                   placeholder="Your Name">
                        </div>
                        <div class="col-md-6">
                            <label for="from_email" class="form-label">
                                <i class="bi bi-envelope"></i> From Email
                            </label>
                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                   value="<?php echo htmlspecialchars($_POST['from_email'] ?? 'noreply@' . $domain); ?>" 
                                   placeholder="noreply@<?php echo $domain; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-lightning"></i> Quick Templates
                        </label>
                        <div class="template-buttons">
                            <button type="button" class="template-btn" onclick="loadTemplate('test')">
                                Test Email
                            </button>
                            <button type="button" class="template-btn" onclick="loadTemplate('welcome')">
                                Welcome
                            </button>
                            <button type="button" class="template-btn" onclick="loadTemplate('followup')">
                                Follow-up
                            </button>
                            <button type="button" class="template-btn" onclick="loadTemplate('thank')">
                                Thank You
                            </button>
                            <button type="button" class="template-btn" onclick="loadTemplate('reminder')">
                                Reminder
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="message" class="form-label">
                            <i class="bi bi-pencil-square"></i> Message *
                        </label>
                        <textarea class="form-control" id="message" name="message" 
                                  placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-clear" onclick="clearForm()">
                            <i class="bi bi-x-circle me-2"></i>Clear
                        </button>
                        <button type="submit" class="btn btn-send">
                            <i class="bi bi-send me-2"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p style="color: white; opacity: 0.8;">
                <i class="bi bi-info-circle"></i> 
                Emails are sent using PHP mail() function
            </p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Email templates
        const templates = {
            test: {
                subject: 'Test Email',
                message: 'This is a test email.\n\nIf you receive this message, the email system is working correctly.\n\nBest regards,\nAutoDial Pro Team'
            },
            welcome: {
                subject: 'Welcome to AutoDial Pro!',
                message: 'Dear Customer,\n\nWelcome to AutoDial Pro! We\'re excited to have you on board.\n\nOur platform provides powerful tools to help you manage your business communications effectively.\n\nIf you have any questions, please don\'t hesitate to reach out.\n\nBest regards,\nThe AutoDial Pro Team'
            },
            followup: {
                subject: 'Following up on our conversation',
                message: 'Hi there,\n\nI wanted to follow up on our recent conversation.\n\nPlease let me know if you have any questions or if there\'s anything else I can help you with.\n\nLooking forward to hearing from you.\n\nBest regards'
            },
            thank: {
                subject: 'Thank You',
                message: 'Dear Customer,\n\nThank you for your business and continued support.\n\nWe appreciate your trust in our services and look forward to serving you.\n\nBest regards,\nAutoDial Pro Team'
            },
            reminder: {
                subject: 'Friendly Reminder',
                message: 'Hi there,\n\nThis is a friendly reminder about [TOPIC].\n\nPlease let me know if you need any assistance.\n\nBest regards'
            }
        };
        
        function loadTemplate(templateName) {
            const template = templates[templateName];
            if (template) {
                document.getElementById('subject').value = template.subject;
                document.getElementById('message').value = template.message;
            }
        }
        
        function clearForm() {
            if (confirm('Are you sure you want to clear the form?')) {
                document.getElementById('to').value = '';
                document.getElementById('subject').value = '';
                document.getElementById('message').value = '';
            }
        }
        
        // Auto-resize textarea
        const textarea = document.getElementById('message');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    </script>
</body>
</html>