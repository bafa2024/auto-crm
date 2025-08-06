<?php
/**
 * Instant Email Sending Interface
 * Send emails directly to custom email addresses
 * Access: https://acrm.regrowup.ca/instant_email.php
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/base_path.php';
require_once __DIR__ . '/services/EmailService.php';

$message = '';
$error = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . base_path('views/auth/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $to = trim($_POST['to']);
        $subject = trim($_POST['subject']);
        $message_content = trim($_POST['message']);
        $from_name = trim($_POST['from_name'] ?? 'AutoDial Pro');
        $from_email = trim($_POST['from_email'] ?? 'noreply@acrm.regrowup.ca');
        
        // Validate inputs
        if (empty($to) || empty($subject) || empty($message_content)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Initialize database and email service
            $database = new Database();
            $db = $database->getConnection();
            $emailService = new EmailService($db);
            
            // Send the email
            $result = $emailService->sendInstantEmail([
                'to' => $to,
                'subject' => $subject,
                'message' => $message_content,
                'from_name' => $from_name,
                'from_email' => $from_email
            ]);
            
            if ($result['success']) {
                $message = 'Email sent successfully!';
                // Clear form data after successful send
                $_POST = [];
            } else {
                $error = 'Failed to send email: ' . $result['message'];
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get recent sent emails for display
$recentEmails = [];
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("
        SELECT 
            cs.id,
            cs.recipient_email,
            cs.subject,
            cs.sent_at,
            c.name as campaign_name
        FROM campaign_sends cs
        LEFT JOIN email_campaigns c ON cs.campaign_id = c.id
        WHERE cs.status = 'sent'
        ORDER BY cs.sent_at DESC
        LIMIT 10
    ");
    
    $recentEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors for recent emails display
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Email - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <style>
        .email-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .btn-send {
            background: linear-gradient(45deg, #0d6efd, #0b5ed7);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        .recent-email {
            transition: all 0.3s ease;
        }
        .recent-email:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .email-status {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .status-sent {
            background-color: #d4edda;
            color: #155724;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<!-- Include Sidebar -->
<?php include 'views/components/sidebar.php'; ?>

<!-- Include Header -->
<?php include 'views/components/header.php'; ?>

<!-- Main Content Area -->
<div class="main-content" style="margin-left: 260px; padding: 20px;">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-envelope-plus me-2 text-primary"></i>
                Instant Email
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="<?php echo base_path('dashboard'); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a href="<?php echo base_path('contacts.php'); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-people me-1"></i>Contacts
                    </a>
                </div>
            </div>
        </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Email Form -->
                    <div class="col-lg-8">
                        <div class="card email-form">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-envelope me-2"></i>
                                    Compose Email
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="emailForm">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="to" class="form-label">
                                                <i class="bi bi-person me-1"></i>To *
                                            </label>
                                            <input type="email" class="form-control" id="to" name="to" 
                                                   value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>" 
                                                   placeholder="recipient@example.com" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="subject" class="form-label">
                                                <i class="bi bi-tag me-1"></i>Subject *
                                            </label>
                                            <input type="text" class="form-control" id="subject" name="subject" 
                                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                                                   placeholder="Email subject" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="from_name" class="form-label">
                                                <i class="bi bi-person-badge me-1"></i>From Name
                                            </label>
                                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                                   value="<?php echo htmlspecialchars($_POST['from_name'] ?? 'AutoDial Pro'); ?>" 
                                                   placeholder="Your Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="from_email" class="form-label">
                                                <i class="bi bi-envelope me-1"></i>From Email
                                            </label>
                                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                                   value="<?php echo htmlspecialchars($_POST['from_email'] ?? 'noreply@acrm.regrowup.ca'); ?>" 
                                                   placeholder="noreply@acrm.regrowup.ca">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="message" class="form-label">
                                            <i class="bi bi-chat-text me-1"></i>Message *
                                        </label>
                                        <textarea class="form-control" id="message" name="message" rows="10" 
                                                  placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="saveAsDraft">
                                            <label class="form-check-label" for="saveAsDraft">
                                                Save as draft
                                            </label>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-outline-secondary me-2" onclick="clearForm()">
                                                <i class="bi bi-trash me-1"></i>Clear
                                            </button>
                                            <button type="submit" class="btn btn-send text-white">
                                                <i class="bi bi-send me-1"></i>Send Email
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Emails -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-clock-history me-2"></i>
                                    Recent Sent Emails
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentEmails)): ?>
                                    <div class="p-3 text-muted text-center">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2">No recent emails</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentEmails as $email): ?>
                                            <div class="list-group-item recent-email">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($email['recipient_email']); ?></h6>
                                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($email['subject']); ?></p>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y g:i A', strtotime($email['sent_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <span class="email-status status-sent">Sent</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-lightning me-2"></i>
                                    Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('welcome')">
                                        <i class="bi bi-star me-1"></i>Welcome Email
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('followup')">
                                        <i class="bi bi-arrow-repeat me-1"></i>Follow-up Email
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('newsletter')">
                                        <i class="bi bi-newspaper me-1"></i>Newsletter
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('promotion')">
                                        <i class="bi bi-tag me-1"></i>Promotion
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Email templates
        const templates = {
            welcome: {
                subject: 'Welcome to AutoDial Pro!',
                message: `Dear [Name],

Welcome to AutoDial Pro! We're excited to have you on board.

Our platform provides powerful email marketing tools to help you grow your business. Here are some key features:

✅ Easy campaign creation
✅ Contact management
✅ Email scheduling
✅ Performance tracking

If you have any questions, feel free to reach out to our support team.

Best regards,
The AutoDial Pro Team`
            },
            followup: {
                subject: 'Following up on our conversation',
                message: `Hi [Name],

I wanted to follow up on our recent conversation about [Topic].

[Your follow-up message here]

Please let me know if you have any questions or if there's anything else I can help you with.

Best regards,
[Your Name]`
            },
            newsletter: {
                subject: 'Monthly Newsletter - [Month] [Year]',
                message: `Dear [Name],

Here's your monthly newsletter with the latest updates and insights:

📰 **What's New**
- [Update 1]
- [Update 2]
- [Update 3]

📊 **Industry Insights**
[Your insights here]

🎯 **Upcoming Events**
[Event details here]

Thank you for being part of our community!

Best regards,
[Your Name]`
            },
            promotion: {
                subject: 'Special Offer - [Offer Details]',
                message: `Dear [Name],

We have an exciting offer just for you!

🎉 **Special Promotion**
[Offer details here]

⏰ **Limited Time Only**
This offer expires on [Date]

💡 **Why Act Now**
[Benefits and urgency]

Don't miss out on this amazing opportunity!

Best regards,
[Your Name]`
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
                document.getElementById('emailForm').reset();
                document.getElementById('to').value = '';
                document.getElementById('subject').value = '';
                document.getElementById('message').value = '';
                document.getElementById('from_name').value = 'AutoDial Pro';
                document.getElementById('from_email').value = 'noreply@acrm.regrowup.ca';
            }
        }

        // Auto-save draft functionality
        let autoSaveTimer;
        document.getElementById('message').addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Auto-save draft (can be implemented later)
                console.log('Auto-saving draft...');
            }, 3000);
        });

        // Character counter for message
        document.getElementById('message').addEventListener('input', function() {
            const charCount = this.value.length;
            const maxChars = 10000;
            const remaining = maxChars - charCount;
            
            // Update character count display
            let counter = document.getElementById('charCounter');
            if (!counter) {
                counter = document.createElement('small');
                counter.id = 'charCounter';
                counter.className = 'text-muted mt-1';
                this.parentNode.appendChild(counter);
            }
            
            if (remaining < 0) {
                counter.textContent = `Character limit exceeded by ${Math.abs(remaining)}`;
                counter.className = 'text-danger mt-1';
            } else {
                counter.textContent = `${charCount}/${maxChars} characters`;
                counter.className = 'text-muted mt-1';
            }
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</div>

</body>
</html> 