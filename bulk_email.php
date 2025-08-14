<?php
/**
 * Bulk Email Sending Interface
 * Send emails to multiple recipients simultaneously
 * Access: https://acrm.regrowup.ca/bulk_email.php
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
        $message_content = $_POST['message']; // Don't trim the message content to preserve line breaks
        $from_name = trim($_POST['from_name'] ?? 'AutoDial Pro');
        $from_email = trim($_POST['from_email'] ?? 'noreply@acrm.regrowup.ca');
        
        // Debug: Check message content format (remove in production)
        if (isset($_GET['debug'])) {
            echo "<pre>Raw message content:\n";
            echo htmlspecialchars($message_content);
            echo "\n\nLength: " . strlen($message_content);
            echo "\n\nLine breaks count: " . substr_count($message_content, "\n");
            echo "</pre>";
        }
        
        // Validate inputs (only trim for empty check)
        if (empty($to) || empty($subject) || empty(trim($message_content))) {
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
                    // Debug: Log the message content before sending
                    error_log("DEBUG bulk_email - Message content before sending: " . var_export($message_content, true));
                    error_log("DEBUG bulk_email - Line breaks count: " . substr_count($message_content, "\n"));
                    
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
                    $message = "All bulk emails sent successfully! ($successCount sent)";
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
    <title>Bulk Email - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/email-editor.css">
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
        
        /* Contact Dropdown Styles */
        #contactsDropdown {
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
            border: 2px solid #0d6efd;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        
        #contactsList {
            max-height: 200px;
            overflow-y: auto;
        }
        
        #contactsList::-webkit-scrollbar {
            width: 8px;
        }
        
        #contactsList::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        #contactsList::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 4px;
        }
        
        #contactsList::-webkit-scrollbar-thumb:hover {
            background: #0b5ed7;
        }
        
        #contactsList .list-group-item {
            border: 1px solid #e9ecef;
            margin-bottom: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 5px !important;
        }
        
        #contactsList .list-group-item:hover {
            background-color: #e7f3ff;
            border-color: #0d6efd;
            transform: translateX(3px);
        }
        
        #contactsList .list-group-item.bg-light {
            background-color: #d1ecf1 !important;
            border-left: 4px solid #0d6efd;
        }
        
        #contactSearch {
            border: 2px solid #dee2e6;
            border-radius: 20px;
            padding-left: 35px;
        }
        
        #contactSearch:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
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
                <i class="bi bi-envelope-at me-2 text-primary"></i>
                Bulk Email
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
                                    <i class="bi bi-envelope-at me-2"></i>
                                    Compose Bulk Email
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="emailForm">
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <label for="to" class="form-label">
                                                <i class="bi bi-people me-1"></i>To (Multiple Recipients) *
                                            </label>
                                            
                                            <!-- Contact Selection Section -->
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div>
                                                        <span class="fw-semibold"><i class="bi bi-person-lines-fill me-1"></i>Select Recipients from Contacts</span>
                                                        <span id="selectedCount" class="ms-2 badge bg-primary">0 selected</span>
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-success btn-sm" onclick="selectAllContacts()">
                                                            <i class="bi bi-check-all"></i> Select All
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm ms-1" onclick="clearAllContacts()">
                                                            <i class="bi bi-x-circle"></i> Clear
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Always Visible Contacts List -->
                                                <div id="contactsDropdown" class="border rounded p-2" style="max-height: 250px; overflow-y: auto; background-color: #f8f9fa;">
                                                    <input type="text" class="form-control form-control-sm mb-2" id="contactSearch" placeholder="🔍 Search contacts by name or email..." onkeyup="filterContacts()">
                                                    <div id="contactsList" class="list-group list-group-flush">
                                                        <div class="text-center p-3">
                                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                                <span class="visually-hidden">Loading contacts...</span>
                                                            </div>
                                                            <div class="mt-2 text-muted">Loading contacts...</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <textarea class="form-control" id="to" name="to" rows="3"
                                                   placeholder="recipient1@example.com, recipient2@example.com, recipient3@example.com..." required><?php echo htmlspecialchars($_POST['to'] ?? ''); ?></textarea>
                                            <div class="form-text">Enter email addresses separated by commas or select from contacts above.</div>
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
                                        <textarea class="form-control" id="message" name="message" rows="12" 
                                                  placeholder="Type your bulk email message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                        <div class="form-text">This message will be sent to all recipients listed above.</div>
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
                                                <i class="bi bi-send-fill me-1"></i>Send Bulk Email
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
                                    Recent Bulk Emails
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
                                    Bulk Email Templates
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('announcement')">
                                        <i class="bi bi-megaphone me-1"></i>Announcement
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('newsletter')">
                                        <i class="bi bi-newspaper me-1"></i>Newsletter
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('promotion')">
                                        <i class="bi bi-tag me-1"></i>Mass Promotion
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('update')">
                                        <i class="bi bi-arrow-clockwise me-1"></i>System Update
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
        // Global variable to store contacts
        let allContacts = [];
        let selectedEmails = [];
        
        // Load contacts when page loads - merged with existing DOMContentLoaded below
        
        // Function to load contacts from API
        async function loadContacts() {
            try {
                console.log('Loading contacts from API...');
                // Use the simpler endpoint
                const response = await fetch('/api/get_all_contacts.php');
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('API response:', data);
                
                if (data.success && data.contacts) {
                    allContacts = data.contacts;
                    console.log(`Loaded ${data.count} contacts:`, allContacts);
                    renderContactsList();
                    updateSelectedCount(); // Initialize count display
                } else {
                    console.error('No contacts found in response');
                    document.getElementById('contactsList').innerHTML = '<div class="text-center text-muted p-3"><i class="bi bi-inbox fs-3"></i><br>No contacts available</div>';
                }
            } catch (error) {
                console.error('Error loading contacts:', error);
                document.getElementById('contactsList').innerHTML = '<div class="text-center text-danger p-3"><i class="bi bi-exclamation-triangle fs-3"></i><br>Error loading contacts. Please refresh the page.</div>';
            }
        }
        
        // Function to render contacts in the dropdown
        function renderContactsList(filteredContacts = null) {
            const contactsList = document.getElementById('contactsList');
            const contacts = filteredContacts || allContacts;
            
            if (contacts.length === 0) {
                contactsList.innerHTML = '<div class="text-center text-muted p-3"><i class="bi bi-inbox fs-3"></i><br>No contacts found</div>';
                return;
            }
            
            let html = '';
            contacts.forEach((contact, index) => {
                const isChecked = selectedEmails.includes(contact.email);
                const bgColor = isChecked ? 'bg-light' : '';
                html += `
                    <label class="list-group-item list-group-item-action d-flex align-items-center py-2 ${bgColor}" style="cursor: pointer;">
                        <input class="form-check-input me-3" type="checkbox" 
                               value="${contact.email}" 
                               data-name="${contact.name || ''}"
                               onchange="toggleContact(this)"
                               ${isChecked ? 'checked' : ''}>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">${contact.name || 'No Name'}</div>
                                    <small class="text-primary">${contact.email}</small>
                                </div>
                                <small class="text-muted">#${index + 1}</small>
                            </div>
                        </div>
                    </label>
                `;
            });
            contactsList.innerHTML = html;
        }
        
        // Function to toggle individual contact selection
        function toggleContact(checkbox) {
            const email = checkbox.value;
            if (checkbox.checked) {
                if (!selectedEmails.includes(email)) {
                    selectedEmails.push(email);
                }
            } else {
                const index = selectedEmails.indexOf(email);
                if (index > -1) {
                    selectedEmails.splice(index, 1);
                }
            }
            updateEmailField();
            updateSelectedCount();
        }
        
        // Function to select all contacts
        function selectAllContacts() {
            selectedEmails = allContacts.map(contact => contact.email);
            renderContactsList();
            updateEmailField();
            updateSelectedCount();
        }
        
        // Function to clear all selections
        function clearAllContacts() {
            selectedEmails = [];
            renderContactsList();
            updateEmailField();
            updateSelectedCount();
        }
        
        // Function to filter contacts based on search
        function filterContacts() {
            const searchTerm = document.getElementById('contactSearch').value.toLowerCase();
            const filtered = allContacts.filter(contact => {
                const name = (contact.name || '').toLowerCase();
                const email = (contact.email || '').toLowerCase();
                return name.includes(searchTerm) || email.includes(searchTerm);
            });
            renderContactsList(filtered);
        }
        
        // Function to update the email textarea field
        function updateEmailField() {
            const emailField = document.getElementById('to');
            const currentEmails = emailField.value.split(/[,\n]/).map(e => e.trim()).filter(e => e);
            
            // Get manually entered emails (not in selectedEmails)
            const manualEmails = currentEmails.filter(email => !allContacts.some(c => c.email === email));
            
            // Combine selected and manual emails
            const allEmails = [...new Set([...selectedEmails, ...manualEmails])];
            emailField.value = allEmails.join(', ');
        }
        
        // Function to update selected count display
        function updateSelectedCount() {
            const countElement = document.getElementById('selectedCount');
            const count = selectedEmails.length;
            countElement.textContent = `${count} selected`;
            
            // Update badge color based on selection
            if (count === 0) {
                countElement.className = 'ms-2 badge bg-secondary';
            } else if (count === allContacts.length) {
                countElement.className = 'ms-2 badge bg-success';
            } else {
                countElement.className = 'ms-2 badge bg-primary';
            }
            
            // Update visual feedback in the list
            renderContactsList();
        }
        
        // Email templates for bulk emails
        const templates = {
            announcement: {
                subject: 'Important Announcement - [Title]',
                message: `Dear Valued Customers,

We have an important announcement to share with you:

📢 **ANNOUNCEMENT**
[Your announcement details here]

📅 **Effective Date:** [Date]

🔍 **What This Means for You:**
• [Benefit/Change 1]
• [Benefit/Change 2] 
• [Benefit/Change 3]

❓ **Questions?**
If you have any questions, please don't hesitate to contact our support team.

Thank you for your continued trust in AutoDial Pro.

Best regards,
The AutoDial Pro Team`
            },
            newsletter: {
                subject: 'Monthly Newsletter - [Month] [Year]',
                message: `Dear Subscribers,

Welcome to our monthly newsletter! Here's what's new:

📰 **LATEST NEWS**
• [News Item 1]
• [News Item 2]
• [News Item 3]

🚀 **NEW FEATURES**
• [Feature 1]: [Description]
• [Feature 2]: [Description]

📊 **INDUSTRY INSIGHTS**
[Your insights here]

🎯 **UPCOMING EVENTS**
• [Event 1] - [Date]
• [Event 2] - [Date]

💡 **TIP OF THE MONTH**
[Your monthly tip]

Thank you for being part of our community!

Best regards,
[Your Team]`
            },
            promotion: {
                subject: 'Special Offer - Limited Time Only!',
                message: `Dear Customers,

We're excited to offer you an exclusive promotion!

🎉 **SPECIAL OFFER**
[Promotion details here]

💰 **SAVINGS:** [Amount/Percentage]
⏰ **EXPIRES:** [Date]

🌟 **FEATURES INCLUDED:**
• [Feature/Benefit 1]
• [Feature/Benefit 2]
• [Feature/Benefit 3]

🚀 **HOW TO CLAIM:**
1. [Step 1]
2. [Step 2]
3. [Step 3]

⚡ **ACT NOW** - This offer is available for a limited time only!

Best regards,
[Your Team]`
            },
            update: {
                subject: 'System Update - [Date]',
                message: `Dear Users,

We're pleased to inform you about our latest system update:

🔧 **WHAT'S NEW**
• [Update 1]
• [Update 2] 
• [Update 3]

🛠️ **IMPROVEMENTS**
• Enhanced performance
• Bug fixes
• Security improvements

📅 **UPDATE SCHEDULE**
• Start Time: [Time]
• Expected Duration: [Duration]
• Completion: [Time]

⚠️ **DURING THE UPDATE**
• [Service status information]
• [Alternative access if any]

We apologize for any inconvenience and thank you for your patience.

Best regards,
The AutoDial Pro Technical Team`
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

        // Email validation with bulk support
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Bulk email form loaded');
            
            // Load contacts
            loadContacts();
            
            // Add email validation
            const emailInput = document.getElementById('to');
            const emailForm = document.getElementById('emailForm');
            
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    validateEmails(this.value);
                });
            }
            
            if (emailForm) {
                emailForm.addEventListener('submit', function(e) {
                    const emails = emailInput.value;
                    if (!validateEmails(emails)) {
                        e.preventDefault();
                        alert('Please enter valid email addresses separated by commas or line breaks');
                    }
                });
            }
        });
        
        function validateEmails(emailString) {
            if (!emailString.trim()) return false;
            
            // Support both comma and line break separation
            const emails = emailString.split(/[,\n\r]/).map(email => email.trim()).filter(email => email.length > 0);
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            for (const email of emails) {
                if (!emailRegex.test(email)) {
                    return false;
                }
            }
            
            return true;
        }

        // Email count display
        document.getElementById('to').addEventListener('input', function() {
            const emailString = this.value;
            const emails = emailString.split(/[,\n\r]/).map(email => email.trim()).filter(email => email.length > 0);
            const validEmails = emails.filter(email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
            
            let counter = document.getElementById('emailCounter');
            if (!counter) {
                counter = document.createElement('small');
                counter.id = 'emailCounter';
                counter.className = 'text-muted mt-1';
                this.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${validEmails.length} valid email(s) detected`;
            if (validEmails.length !== emails.length && emails.length > 0) {
                counter.textContent += ` (${emails.length - validEmails.length} invalid)`;
                counter.className = 'text-warning mt-1';
            } else {
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
