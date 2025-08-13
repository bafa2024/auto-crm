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

// Debug session information
if (isset($_GET['debug'])) {
    echo "<pre>Debug Session Info:\n";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    echo "User Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
    echo "User Name: " . ($_SESSION['user_name'] ?? 'NOT SET') . "\n";
    echo "</pre>";
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
        } else {
            // Handle multiple email addresses (comma-separated)
            $recipients = array_map('trim', explode(',', $to));
            $validRecipients = array_filter($recipients, function($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            
            if (empty($validRecipients)) {
                $error = 'Please enter at least one valid email address.';
            } else {
                // Initialize database and email service
                $database = new Database();
                $db = $database->getConnection();
                $emailService = new EmailService($db);
                
                $successCount = 0;
                $failCount = 0;
                $results = [];
                
                // Send emails to each recipient
                foreach ($validRecipients as $recipient) {
                    $result = $emailService->sendInstantEmail([
                        'to' => $recipient,
                        'subject' => $subject,
                        'message' => $message_content,
                        'from_name' => $from_name,
                        'from_email' => $from_email
                    ]);
                    
                    if ($result === true) {
                        $successCount++;
                        $results[] = "✓ Sent to: $recipient";
                    } else {
                        $failCount++;
                        $results[] = "✗ Failed to send to: $recipient";
                    }
                }
                
                // Prepare success/error messages
                if ($successCount > 0 && $failCount == 0) {
                    $message = "All emails sent successfully! ($successCount sent)";
                    // Clear form data after successful send
                    $_POST = [];
                } elseif ($successCount > 0 && $failCount > 0) {
                    $message = "Partially successful: $successCount sent, $failCount failed";
                    $error = implode('<br>', array_filter($results, function($r) { return strpos($r, '✗') === 0; }));
                } else {
                    $error = 'Failed to send any emails. Please check your email settings.';
                }
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

    <link rel="stylesheet" href="css/sidebar-layout-fix.css">

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
        
        /* Contact dropdown styles */
        .contact-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .contact-item {
            transition: background-color 0.2s ease;
        }

        .contact-item:hover {
            background-color: #f8f9fa;
        }

        .contact-checkbox {
            margin-right: 0.5rem;
        }

        .dropdown-menu {
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: 1px solid #dee2e6;
        }

        #selectedContactsList .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
        }

        #selectedContactsList .btn-close {
            padding: 0;
            width: 0.8em;
            height: 0.8em;
            background-size: 0.8em;
        }

        .dropdown-toggle::after {
            margin-left: auto;
        }

        /* Improve dropdown button appearance */
        #contactDropdownBtn {
            text-align: left;
            padding: 0.5rem 0.75rem;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #contactDropdownBtn:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        /* Search input in dropdown */
        #contactSearch {
            border-radius: 6px;
            border: 1px solid #ced4da;
        }

        #contactSearch:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.125rem rgba(13, 110, 253, 0.25);
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
                                        <div class="col-12">
                                            <label for="to" class="form-label">
                                                <i class="bi bi-person me-1"></i>To *
                                            </label>
                                            <div class="position-relative">
                                                <!-- Searchable contact dropdown -->
                                                <div class="dropdown w-100 mb-2">
                                                    <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" id="contactDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span id="dropdownText"><i class="bi bi-search me-2"></i>Search and select contacts...</span>
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                    <div class="dropdown-menu w-100 p-0" aria-labelledby="contactDropdownBtn" style="max-height: 450px; width: 100% !important;">
                                                        <!-- Search box -->
                                                        <div class="p-3 border-bottom">
                                                            <div class="input-group">
                                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                                <input type="text" class="form-control" id="contactSearch" placeholder="Search by name, email, or company...">
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Contact list -->
                                                        <div class="contact-list" style="max-height: 350px; overflow-y: auto;">
                                                            <div id="contactList">
                                                                <div class="text-center py-4">
                                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                                        <span class="visually-hidden">Loading...</span>
                                                                    </div>
                                                                    <div class="small mt-2">Loading contacts...</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Manual entry option -->
                                                        <div class="border-top p-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="manualEntryMode">
                                                                <label class="form-check-label small text-muted" for="manualEntryMode">
                                                                    Enter email addresses manually
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Selected contacts display -->
                                                <div id="selectedContacts" class="mb-2" style="display: none;">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <small class="text-muted">Selected recipients:</small>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllContacts" style="display: none;">
                                                            <i class="bi bi-x-circle"></i> Clear All
                                                        </button>
                                                    </div>
                                                    <div id="selectedContactsList" class="d-flex flex-wrap gap-1"></div>
                                                </div>
                                                
                                                <!-- Manual email input (hidden by default) -->
                                                <div id="manualEmailInput" style="display: none;">
                                                    <input type="email" class="form-control" id="to" name="to" 
                                                           value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>" 
                                                           placeholder="recipient@example.com">
                                                    <div class="form-text small">Enter email addresses separated by commas</div>
                                                </div>
                                                
                                                <!-- Hidden input to store selected emails for form submission -->
                                                <input type="hidden" id="selectedEmails" name="to" required 
                                                       value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>">
                                            </div>
                                            <div class="form-text">Search and select contacts from your contact list, or check "Enter manually" to type email addresses directly.</div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-12">
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

        // Contact dropdown functionality
        let allContacts = [];
        let selectedContactsSet = new Set();
        let contactSearchTimeout;

        // Initialize contact dropdown
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing dropdown...');
            console.log('Bootstrap version:', window.bootstrap ? 'Loaded' : 'Not loaded');
            setupContactDropdown();
            
            // Test manual API call
            window.testAPICall = async function() {
                console.log('Manual API test...');
                try {
                    const response = await fetch('api/instant-email/all-contacts?limit=5');
                    const data = await response.json();
                    console.log('Manual API result:', data);
                    alert('Check console for API result');
                } catch (error) {
                    console.error('Manual API error:', error);
                    alert('API Error: ' + error.message);
                }
            };
        });

        function setupContactDropdown() {
            console.log('setupContactDropdown() called');
            const contactSearch = document.getElementById('contactSearch');
            const contactDropdownBtn = document.getElementById('contactDropdownBtn');
            const manualEntryMode = document.getElementById('manualEntryMode');
            const manualEmailInput = document.getElementById('manualEmailInput');
            const clearAllBtn = document.getElementById('clearAllContacts');
            
            console.log('contactDropdownBtn found:', !!contactDropdownBtn);
            console.log('contactSearch found:', !!contactSearch);
            
            // Debug: Add click listener to see if button is clickable
            if (contactDropdownBtn) {
                contactDropdownBtn.addEventListener('click', function() {
                    console.log('Dropdown button clicked!');
                });
            }
            
            // Load all contacts when dropdown is opened
            contactDropdownBtn.addEventListener('shown.bs.dropdown', function() {
                console.log('Dropdown shown event fired');
                loadAllContacts();
            });
            
            // Setup search functionality
            if (contactSearch) {
                contactSearch.addEventListener('input', function(e) {
                    console.log('Search input changed:', e.target.value);
                    clearTimeout(contactSearchTimeout);
                    contactSearchTimeout = setTimeout(() => {
                        filterContactList(e.target.value);
                    }, 300);
                });
            }
            
            // Manual entry mode toggle
            manualEntryMode.addEventListener('change', function() {
                if (this.checked) {
                    manualEmailInput.style.display = 'block';
                    document.getElementById('selectedEmails').removeAttribute('required');
                    document.getElementById('to').setAttribute('required', 'required');
                } else {
                    manualEmailInput.style.display = 'none';
                    document.getElementById('to').removeAttribute('required');
                    document.getElementById('selectedEmails').setAttribute('required', 'required');
                }
            });
            
            // Clear all contacts
            clearAllBtn.addEventListener('click', function() {
                selectedContactsSet.clear();
                updateSelectedContactsDisplay();
                updateDropdownButtonText();
                updateHiddenInput();
                
                // Uncheck all checkboxes
                document.querySelectorAll('.contact-checkbox').forEach(cb => cb.checked = false);
            });
            
            // Prevent dropdown from closing when clicking inside
            document.querySelector('.dropdown-menu').addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Load contacts initially
            loadAllContacts();
        }

        async function loadAllContacts() {
            console.log('loadAllContacts() called');
            const contactList = document.getElementById('contactList');
            const searchQuery = document.getElementById('contactSearch').value;
            
            try {
                contactList.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="small mt-2">Loading contacts...</div>
                    </div>
                `;
                
                const apiUrl = `api/instant-email/all-contacts?search=${encodeURIComponent(searchQuery)}&limit=200`;
                console.log('Making API call to:', apiUrl);
                
                const response = await fetch(apiUrl);
                console.log('API Response status:', response.status);
                console.log('API Response headers:', response.headers);
                
                const data = await response.json();
                console.log('API Response data:', data);
                
                if (data.success) {
                    allContacts = data.data.data || [];
                    console.log('Contacts loaded:', allContacts.length);
                    displayContactList(allContacts);
                } else {
                    console.error('API returned error:', data);
                    contactList.innerHTML = `<div class="text-center py-3 text-muted">
                        <i class="bi bi-exclamation-circle"></i><br>
                        Failed to load contacts<br>
                        <small class="text-danger">${data.message || 'Unknown error'}</small>
                    </div>`;
                }
            } catch (error) {
                console.error('Error loading contacts:', error);
                contactList.innerHTML = `<div class="text-center py-3 text-danger">
                    <i class="bi bi-x-circle"></i><br>
                    Error loading contacts<br>
                    <small>${error.message}</small>
                </div>`;
            }
        }

        function filterContactList(searchQuery) {
            if (!searchQuery) {
                displayContactList(allContacts);
                return;
            }
            
            const filtered = allContacts.filter(contact => 
                contact.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                contact.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (contact.company && contact.company.toLowerCase().includes(searchQuery.toLowerCase()))
            );
            
            displayContactList(filtered);
        }

        function displayContactList(contacts) {
            const contactList = document.getElementById('contactList');
            
            if (contacts.length === 0) {
                contactList.innerHTML = `
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-person-x fs-4"></i>
                        <div class="mt-2">No contacts found</div>
                        <div class="small">Try a different search term</div>
                    </div>
                `;
                return;
            }
            
            contactList.innerHTML = contacts.map(contact => `
                <div class="contact-item px-3 py-2 border-bottom" style="cursor: pointer;" 
                     onclick="toggleContactSelection('${contact.id}', '${contact.email}', '${contact.display_name.replace(/'/g, "\\'")}')">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <input type="checkbox" class="form-check-input contact-checkbox" 
                                   id="contact_${contact.id}" 
                                   ${selectedContactsSet.has(contact.email) ? 'checked' : ''}>
                        </div>
                        <div class="me-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                 style="width: 35px; height: 35px; font-size: 0.9rem;">
                                ${contact.name.charAt(0).toUpperCase()}
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">${contact.name}</div>
                            <div class="small text-muted">${contact.email}</div>
                            ${contact.company ? `<div class="small text-muted"><i class="bi bi-building"></i> ${contact.company}</div>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function toggleContactSelection(contactId, email, displayName) {
            const checkbox = document.getElementById(`contact_${contactId}`);
            
            if (selectedContactsSet.has(email)) {
                // Remove from selection
                selectedContactsSet.delete(email);
                checkbox.checked = false;
            } else {
                // Add to selection
                selectedContactsSet.add(email);
                checkbox.checked = true;
            }
            
            updateSelectedContactsDisplay();
            updateDropdownButtonText();
            updateHiddenInput();
        }

        function updateSelectedContactsDisplay() {
            const selectedContactsDiv = document.getElementById('selectedContacts');
            const selectedContactsList = document.getElementById('selectedContactsList');
            const clearAllBtn = document.getElementById('clearAllContacts');
            
            if (selectedContactsSet.size === 0) {
                selectedContactsDiv.style.display = 'none';
                clearAllBtn.style.display = 'none';
                return;
            }
            
            selectedContactsDiv.style.display = 'block';
            clearAllBtn.style.display = 'inline-block';
            
            selectedContactsList.innerHTML = Array.from(selectedContactsSet).map(email => `
                <span class="badge bg-primary d-inline-flex align-items-center me-1 mb-1" style="font-size: 0.8rem;">
                    <i class="bi bi-person-fill me-1"></i>
                    ${email}
                    <button type="button" class="btn-close btn-close-white ms-2" 
                            onclick="removeSelectedContact('${email}')" 
                            style="font-size: 0.6em;"></button>
                </span>
            `).join('');
        }

        function removeSelectedContact(email) {
            selectedContactsSet.delete(email);
            
            // Update checkbox if visible
            const contact = allContacts.find(c => c.email === email);
            if (contact) {
                const checkbox = document.getElementById(`contact_${contact.id}`);
                if (checkbox) checkbox.checked = false;
            }
            
            updateSelectedContactsDisplay();
            updateDropdownButtonText();
            updateHiddenInput();
        }

        function updateDropdownButtonText() {
            const dropdownText = document.getElementById('dropdownText');
            const count = selectedContactsSet.size;
            
            if (count === 0) {
                dropdownText.innerHTML = '<i class="bi bi-search me-2"></i>Search and select contacts...';
            } else if (count === 1) {
                dropdownText.innerHTML = `<i class="bi bi-person-check me-2"></i>1 contact selected`;
            } else {
                dropdownText.innerHTML = `<i class="bi bi-people-fill me-2"></i>${count} contacts selected`;
            }
        }

        function updateHiddenInput() {
            const hiddenInput = document.getElementById('selectedEmails');
            hiddenInput.value = Array.from(selectedContactsSet).join(',');
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</div>

</body>
</html> 