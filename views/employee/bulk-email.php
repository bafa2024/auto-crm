<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Check permissions for bulk email (using same permission as instant email for now)
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

$database = new Database();
$db = $database->getConnection();
$permissionModel = new EmployeePermission($db);

if (!$permissionModel->hasPermission($_SESSION["user_id"], 'can_send_instant_emails')) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/dashboard'));
    exit;
}

include __DIR__ . "/../components/header.php";
include __DIR__ . "/../components/employee-sidebar.php";
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Bulk Email</h1>
                <p class="text-muted">Send emails to multiple recipients simultaneously</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="loadEmailHistory()">
                    <i class="bi bi-clock-history"></i> Email History
                </button>
                <button class="btn btn-outline-secondary" onclick="loadContactList()">
                    <i class="bi bi-people"></i> Load Contacts
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Email Composer -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-envelope-at"></i> Compose Bulk Email
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="bulkEmailForm">
                            <!-- Recipients -->
                            <div class="mb-3">
                                <label for="emailTo" class="form-label">To (Multiple Recipients) *</label>
                                <div class="position-relative">
                                    <!-- Contact selection area -->
                                    <div class="border rounded p-3 mb-2" style="min-height: 120px; background-color: #f8f9fa;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">Selected Recipients:</small>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="selectAllContacts()">
                                                    <i class="bi bi-check-all"></i> Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllRecipients()">
                                                    <i class="bi bi-x-circle"></i> Clear All
                                                </button>
                                            </div>
                                        </div>
                                        <div id="selectedRecipientsList" class="d-flex flex-wrap gap-1">
                                            <div class="text-muted small">No recipients selected. Use "Load Contacts" button above or enter emails manually below.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Manual email input -->
                                    <div>
                                        <label for="manualEmails" class="form-label small">Or enter email addresses manually:</label>
                                        <textarea class="form-control" id="manualEmails" name="to" rows="4" 
                                            placeholder="Enter email addresses separated by commas, semicolons, or new lines&#10;Example:&#10;user1@example.com, user2@example.com&#10;user3@example.com"></textarea>
                                        <div class="form-text">Separate multiple email addresses with commas, semicolons, or new lines</div>
                                        <div id="emailCount" class="small text-muted mt-1"></div>
                                    </div>
                                    
                                    <!-- Hidden input for selected contacts -->
                                    <input type="hidden" id="selectedContactEmails" name="selected_contacts">
                                </div>
                            </div>

                            <!-- CC and BCC -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emailCc" class="form-label">CC</label>
                                        <textarea class="form-control" id="emailCc" name="cc" rows="1" 
                                            placeholder="CC recipients (optional)"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emailBcc" class="form-label">BCC</label>
                                        <textarea class="form-control" id="emailBcc" name="bcc" rows="1" 
                                            placeholder="BCC recipients (optional)"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="mb-3">
                                <label for="emailSubject" class="form-label">Subject *</label>
                                <input type="text" class="form-control" id="emailSubject" name="subject" 
                                    placeholder="Enter email subject" required>
                            </div>

                            <!-- Message -->
                            <div class="mb-3">
                                <label for="emailMessage" class="form-label">Message *</label>
                                <textarea class="form-control" id="emailMessage" name="message" rows="10" 
                                    placeholder="Type your bulk email message here..." required></textarea>
                                <div class="form-text">This message will be sent to all recipients listed above.</div>
                            </div>

                            <!-- Send Options -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="saveDraft">
                                    <label class="form-check-label" for="saveDraft">
                                        Save as draft
                                    </label>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="sendBulkEmailBtn">
                                        <i class="bi bi-send-fill"></i> Send Bulk Email
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sending Progress -->
                <div id="sendingProgress" class="card mt-3" style="display: none;">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                <span class="visually-hidden">Sending...</span>
                            </div>
                            <span>Sending bulk email...</span>
                        </div>
                        <div class="progress">
                            <div id="sendProgress" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="sendStatus" class="small text-muted mt-1">Preparing to send...</div>
                    </div>
                </div>
            </div>

            <!-- Templates and Tools -->
            <div class="col-lg-4">
                <!-- Quick Templates -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-file-text"></i> Bulk Email Templates
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyTemplate('announcement')">
                                <i class="bi bi-megaphone me-1"></i>Announcement
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyTemplate('newsletter')">
                                <i class="bi bi-newspaper me-1"></i>Newsletter
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyTemplate('promotion')">
                                <i class="bi bi-tag me-1"></i>Mass Promotion
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyTemplate('update')">
                                <i class="bi bi-arrow-clockwise me-1"></i>System Update
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bulk Email Tips -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightbulb"></i> Bulk Email Best Practices
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="mb-2">
                                <strong>✓ Clear subject line:</strong> Make it relevant to all recipients
                            </div>
                            <div class="mb-2">
                                <strong>✓ Personalize when possible:</strong> Use merge fields or general greetings
                            </div>
                            <div class="mb-2">
                                <strong>✓ Concise content:</strong> Keep bulk emails brief and focused
                            </div>
                            <div class="mb-2">
                                <strong>✓ Call to action:</strong> Include clear next steps for recipients
                            </div>
                            <div class="mb-2">
                                <strong>✓ Test send:</strong> Send to yourself first to check formatting
                            </div>
                            <div>
                                <strong>✓ Compliance:</strong> Include unsubscribe options when required
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Send Statistics -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up"></i> Send Statistics
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Recipients:</span>
                                <span id="totalRecipients" class="fw-bold">0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>From Contacts:</span>
                                <span id="contactRecipients" class="fw-bold">0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Manual Entries:</span>
                                <span id="manualRecipients" class="fw-bold">0</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span>Estimated Send Time:</span>
                                <span id="estimatedTime" class="fw-bold text-muted">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contact Selection Modal -->
<div class="modal fade" id="contactSelectionModal" tabindex="-1" aria-labelledby="contactSelectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactSelectionModalLabel">
                    <i class="bi bi-people"></i> Select Contacts for Bulk Email
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Search and filters -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="contactSearchInput" placeholder="Search by name, email, or company...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllVisibleContacts()">
                                <i class="bi bi-check-all"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAllContacts()">
                                <i class="bi bi-x-circle"></i> Deselect All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contact list -->
                <div id="contactListContainer" class="border rounded" style="height: 400px; overflow-y: auto;">
                    <div id="contactListLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Loading contacts...</div>
                    </div>
                    <div id="contactListContent" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <small class="text-muted">
                        <span id="selectedContactCount">0</span> contact(s) selected
                    </small>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addSelectedContacts()">
                    Add Selected Contacts
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Email History Modal -->
<div class="modal fade" id="emailHistoryModal" tabindex="-1" aria-labelledby="emailHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailHistoryModalLabel">
                    <i class="bi bi-clock-history"></i> Bulk Email History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historyLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="historyContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Recipients</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="historyEmpty" class="text-center py-4" style="display: none;">
                    <i class="bi bi-envelope text-muted fs-1"></i>
                    <p class="text-muted mt-2">No bulk emails sent yet</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-detect base path for live hosting compatibility
const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';

// Global variables
let allContacts = [];
let selectedContacts = new Set();
let bulkEmailTemplates = {};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setupBulkEmailTemplates();
    setupFormHandlers();
    updateRecipientCounts();
});

// Bulk email templates
function setupBulkEmailTemplates() {
    bulkEmailTemplates = {
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
}

// Apply template
function applyTemplate(templateName) {
    const template = bulkEmailTemplates[templateName];
    if (template) {
        document.getElementById('emailSubject').value = template.subject;
        document.getElementById('emailMessage').value = template.message;
    }
}

// Setup form handlers
function setupFormHandlers() {
    // Manual email input handler
    document.getElementById('manualEmails').addEventListener('input', function() {
        updateRecipientCounts();
    });

    // Form submission
    document.getElementById('bulkEmailForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await sendBulkEmail();
    });
}

// Load contact list modal
async function loadContactList() {
    const modal = new bootstrap.Modal(document.getElementById('contactSelectionModal'));
    modal.show();
    
    try {
        const response = await fetch(`${basePath}/api/instant-email/all-contacts?limit=1000`);
        const data = await response.json();
        
        if (data.success) {
            allContacts = data.data.data || [];
            displayContactList(allContacts);
        } else {
            showContactListError('Failed to load contacts');
        }
    } catch (error) {
        console.error('Error loading contacts:', error);
        showContactListError('Error loading contacts');
    } finally {
        document.getElementById('contactListLoading').style.display = 'none';
        document.getElementById('contactListContent').style.display = 'block';
    }
}

// Display contact list
function displayContactList(contacts) {
    const container = document.getElementById('contactListContent');
    
    if (contacts.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-person-x fs-1"></i>
                <p class="mt-2">No contacts found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = contacts.map(contact => {
        const displayName = contact.name || `${contact.first_name || ''} ${contact.last_name || ''}`.trim() || contact.email;
        const initial = displayName.charAt(0).toUpperCase() || 'U';
        const isSelected = selectedContacts.has(contact.email);
        
        return `
            <div class="contact-item p-3 border-bottom" style="cursor: pointer;" 
                 onclick="toggleContactSelection('${contact.email}', '${displayName.replace(/'/g, "\\'")}')">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <input type="checkbox" class="form-check-input contact-checkbox" 
                               id="contact_${contact.id}" 
                               ${isSelected ? 'checked' : ''}>
                    </div>
                    <div class="me-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px; font-size: 1rem;">
                            ${initial}
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-medium">${displayName}</div>
                        <div class="small text-muted">${contact.email}</div>
                        ${contact.company ? `<div class="small text-muted"><i class="bi bi-building"></i> ${contact.company}</div>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    updateSelectedContactCount();
}

// Toggle contact selection
function toggleContactSelection(email, displayName) {
    if (selectedContacts.has(email)) {
        selectedContacts.delete(email);
    } else {
        selectedContacts.add(email);
    }
    
    // Update checkbox
    const contact = allContacts.find(c => c.email === email);
    if (contact) {
        const checkbox = document.getElementById(`contact_${contact.id}`);
        if (checkbox) checkbox.checked = selectedContacts.has(email);
    }
    
    updateSelectedContactCount();
}

// Select all visible contacts
function selectAllVisibleContacts() {
    allContacts.forEach(contact => {
        selectedContacts.add(contact.email);
        const checkbox = document.getElementById(`contact_${contact.id}`);
        if (checkbox) checkbox.checked = true;
    });
    updateSelectedContactCount();
}

// Deselect all contacts
function deselectAllContacts() {
    selectedContacts.clear();
    document.querySelectorAll('.contact-checkbox').forEach(cb => cb.checked = false);
    updateSelectedContactCount();
}

// Update selected contact count
function updateSelectedContactCount() {
    document.getElementById('selectedContactCount').textContent = selectedContacts.size;
}

// Add selected contacts to form
function addSelectedContacts() {
    updateSelectedRecipientsDisplay();
    updateRecipientCounts();
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('contactSelectionModal'));
    modal.hide();
}

// Update selected recipients display
function updateSelectedRecipientsDisplay() {
    const container = document.getElementById('selectedRecipientsList');
    
    if (selectedContacts.size === 0) {
        container.innerHTML = '<div class="text-muted small">No recipients selected. Use "Load Contacts" button above or enter emails manually below.</div>';
        return;
    }
    
    container.innerHTML = Array.from(selectedContacts).map(email => `
        <span class="badge bg-primary d-inline-flex align-items-center me-1 mb-1">
            <i class="bi bi-person-fill me-1"></i>
            ${email}
            <button type="button" class="btn-close btn-close-white ms-2" 
                    onclick="removeRecipient('${email}')" 
                    style="font-size: 0.6em;"></button>
        </span>
    `).join('');
    
    // Update hidden input
    document.getElementById('selectedContactEmails').value = Array.from(selectedContacts).join(',');
}

// Remove recipient
function removeRecipient(email) {
    selectedContacts.delete(email);
    updateSelectedRecipientsDisplay();
    updateRecipientCounts();
}

// Clear all recipients
function clearAllRecipients() {
    selectedContacts.clear();
    updateSelectedRecipientsDisplay();
    updateRecipientCounts();
}

// Select all contacts
function selectAllContacts() {
    if (allContacts.length === 0) {
        loadContactList();
        return;
    }
    
    allContacts.forEach(contact => selectedContacts.add(contact.email));
    updateSelectedRecipientsDisplay();
    updateRecipientCounts();
}

// Update recipient counts
function updateRecipientCounts() {
    const manualEmails = document.getElementById('manualEmails').value;
    const manualEmailList = manualEmails ? manualEmails.split(/[,;\n\r]/).map(e => e.trim()).filter(e => e && validateEmail(e)) : [];
    
    const contactCount = selectedContacts.size;
    const manualCount = manualEmailList.length;
    const totalCount = contactCount + manualCount;
    
    document.getElementById('contactRecipients').textContent = contactCount;
    document.getElementById('manualRecipients').textContent = manualCount;
    document.getElementById('totalRecipients').textContent = totalCount;
    
    // Update email count display
    const emailCountEl = document.getElementById('emailCount');
    if (manualCount > 0) {
        emailCountEl.textContent = `${manualCount} valid email(s) detected`;
        emailCountEl.className = 'small text-success mt-1';
    } else if (manualEmails.trim()) {
        emailCountEl.textContent = 'No valid emails detected';
        emailCountEl.className = 'small text-warning mt-1';
    } else {
        emailCountEl.textContent = '';
    }
    
    // Estimate send time (assuming 1 second per email)
    const estimatedTime = totalCount > 0 ? `~${Math.ceil(totalCount / 10)} seconds` : '--';
    document.getElementById('estimatedTime').textContent = estimatedTime;
}

// Validate email format
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Send bulk email
async function sendBulkEmail() {
    const form = document.getElementById('bulkEmailForm');
    const sendBtn = document.getElementById('sendBulkEmailBtn');
    const progressDiv = document.getElementById('sendingProgress');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Get all recipients
    const manualEmails = document.getElementById('manualEmails').value;
    const manualEmailList = manualEmails ? manualEmails.split(/[,;\n\r]/).map(e => e.trim()).filter(e => e && validateEmail(e)) : [];
    const contactEmails = Array.from(selectedContacts);
    const allRecipients = [...new Set([...contactEmails, ...manualEmailList])]; // Remove duplicates
    
    if (allRecipients.length === 0) {
        alert('Please select contacts or enter email addresses manually.');
        return;
    }
    
    // Confirm bulk send
    if (!confirm(`Send bulk email to ${allRecipients.length} recipient(s)?`)) {
        return;
    }
    
    // Get form data
    const formData = {
        recipients: allRecipients,
        cc: document.getElementById('emailCc').value.trim(),
        bcc: document.getElementById('emailBcc').value.trim(),
        subject: document.getElementById('emailSubject').value.trim(),
        message: document.getElementById('emailMessage').value.trim()
    };
    
    // Show progress
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
    progressDiv.style.display = 'block';
    
    try {
        // Send emails in batches
        const batchSize = 10;
        let successCount = 0;
        let failCount = 0;
        
        for (let i = 0; i < allRecipients.length; i += batchSize) {
            const batch = allRecipients.slice(i, i + batchSize);
            const progress = Math.round(((i + batch.length) / allRecipients.length) * 100);
            
            // Update progress
            document.getElementById('sendProgress').style.width = progress + '%';
            document.getElementById('sendStatus').textContent = `Sending to batch ${Math.ceil((i + 1) / batchSize)} of ${Math.ceil(allRecipients.length / batchSize)}...`;
            
            // Send batch
            for (const recipient of batch) {
                try {
                    const response = await fetch(`${basePath}/api/instant-email/send`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            to: recipient,
                            cc: formData.cc,
                            bcc: formData.bcc,
                            subject: formData.subject,
                            message: formData.message
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        successCount++;
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    console.error('Error sending to', recipient, error);
                    failCount++;
                }
            }
            
            // Small delay between batches
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        // Show results
        if (successCount > 0 && failCount === 0) {
            showAlert('success', `All ${successCount} emails sent successfully!`);
            clearForm();
        } else if (successCount > 0 && failCount > 0) {
            showAlert('warning', `Partially successful: ${successCount} sent, ${failCount} failed`);
        } else {
            showAlert('danger', 'Failed to send emails. Please check your email settings.');
        }
    } catch (error) {
        console.error('Error sending bulk email:', error);
        showAlert('danger', 'Network error. Please try again.');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send-fill"></i> Send Bulk Email';
        progressDiv.style.display = 'none';
    }
}

// Clear form
function clearForm() {
    document.getElementById('bulkEmailForm').reset();
    selectedContacts.clear();
    updateSelectedRecipientsDisplay();
    updateRecipientCounts();
}

// Show alert
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const mainContent = document.querySelector('.main-content .container-fluid');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Show contact list error
function showContactListError(message) {
    document.getElementById('contactListContent').innerHTML = `
        <div class="text-center py-4 text-danger">
            <i class="bi bi-x-circle fs-1"></i>
            <p class="mt-2">${message}</p>
        </div>
    `;
}

// Email history modal
async function loadEmailHistory() {
    const modal = new bootstrap.Modal(document.getElementById('emailHistoryModal'));
    modal.show();
    
    const loadingEl = document.getElementById('historyLoading');
    const contentEl = document.getElementById('historyContent');
    const emptyEl = document.getElementById('historyEmpty');
    
    // Reset display
    loadingEl.style.display = 'block';
    contentEl.style.display = 'none';
    emptyEl.style.display = 'none';
    
    try {
        const response = await fetch(`${basePath}/api/instant-email/history?limit=50`);
        const data = await response.json();
        
        if (data.success && data.data.emails.length > 0) {
            displayEmailHistory(data.data.emails);
            contentEl.style.display = 'block';
        } else {
            emptyEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading email history:', error);
        emptyEl.style.display = 'block';
    } finally {
        loadingEl.style.display = 'none';
    }
}

// Display email history
function displayEmailHistory(emails) {
    const tbody = document.getElementById('historyTableBody');
    
    tbody.innerHTML = emails.map(email => `
        <tr>
            <td>${email.recipient_email}</td>
            <td>${email.subject}</td>
            <td>
                <span class="badge bg-${email.status === 'sent' ? 'success' : 'danger'}">
                    ${email.status}
                </span>
            </td>
            <td>${new Date(email.sent_at).toLocaleString()}</td>
        </tr>
    `).join('');
}

// Contact search functionality
document.getElementById('contactSearchInput').addEventListener('input', function(e) {
    const searchQuery = e.target.value.toLowerCase();
    
    if (!searchQuery) {
        displayContactList(allContacts);
        return;
    }
    
    const filtered = allContacts.filter(contact => {
        const name = contact.name || `${contact.first_name || ''} ${contact.last_name || ''}`.trim() || '';
        const email = contact.email || '';
        const company = contact.company || '';
        
        return name.toLowerCase().includes(searchQuery) ||
               email.toLowerCase().includes(searchQuery) ||
               company.toLowerCase().includes(searchQuery);
    });
    
    displayContactList(filtered);
});
</script>

<style>
/* Main layout fix for sidebar */
.main-content {
    margin-left: 260px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
}

/* Card styling */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.25rem;
}

/* Contact item styling */
.contact-item {
    transition: background-color 0.2s ease;
}

.contact-item:hover {
    background-color: #f8f9fa;
}

/* Progress bar styling */
.progress {
    height: 6px;
    border-radius: 3px;
}

/* Badge styling for recipients */
#selectedRecipientsList .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    padding: 0.4rem 0.6rem;
}

#selectedRecipientsList .btn-close {
    padding: 0;
    width: 0.8em;
    height: 0.8em;
    background-size: 0.8em;
}

/* Statistics card */
.card .small {
    font-size: 0.875rem;
}

/* Modal improvements */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0;
}

/* Form improvements */
.form-control {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Button styling */
.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Responsive improvements */
@media (max-width: 992px) {
    .col-lg-4 .card {
        margin-top: 1rem;
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
}
</style>

<?php include __DIR__ . "/../components/footer.php"; ?>
