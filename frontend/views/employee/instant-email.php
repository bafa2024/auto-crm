<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Check permissions
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
                <h1 class="h3 mb-0">Instant Email</h1>
                <p class="text-muted">Send quick emails to contacts and prospects</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="loadEmailHistory()">
                    <i class="bi bi-clock-history"></i> Email History
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Email Composer -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-envelope-paper"></i> Compose Email
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="instantEmailForm">
                            <!-- Recipients -->
                            <div class="mb-3">
                                <label for="emailTo" class="form-label">To *</label>
                                <div class="position-relative">
                                    <textarea class="form-control" id="emailTo" name="to" rows="2" 
                                        placeholder="Enter email addresses separated by commas, semicolons, or new lines"
                                        required></textarea>
                                    <div id="contactSuggestions" class="position-absolute w-100 bg-white border rounded shadow-sm" 
                                         style="display: none; z-index: 1000; max-height: 200px; overflow-y: auto; top: 100%;"></div>
                                </div>
                                <div class="form-text">You can type email addresses or search for contacts by name or company</div>
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
                                <textarea class="form-control" id="emailMessage" name="message" rows="8" 
                                    placeholder="Type your message here..." required></textarea>
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
                                    <button type="submit" class="btn btn-primary" id="sendEmailBtn">
                                        <i class="bi bi-send"></i> Send Email
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sending Progress -->
                <div id="sendingProgress" class="card mt-3" style="display: none;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                <span class="visually-hidden">Sending...</span>
                            </div>
                            <span>Sending email...</span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates and Tools -->
            <div class="col-lg-4">
                <!-- Quick Templates -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-file-text"></i> Quick Templates
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="templatesLoading" class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="templatesContent" style="display: none;">
                            <div class="d-grid gap-2" id="templatesList"></div>
                        </div>
                    </div>
                </div>

                <!-- Email Tips -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightbulb"></i> Email Tips
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="mb-2">
                                <strong>✓ Keep it concise:</strong> Short emails get better responses
                            </div>
                            <div class="mb-2">
                                <strong>✓ Personal touch:</strong> Use the recipient's name when possible
                            </div>
                            <div class="mb-2">
                                <strong>✓ Clear subject:</strong> Be specific about the email's purpose
                            </div>
                            <div class="mb-2">
                                <strong>✓ Call to action:</strong> End with what you want them to do
                            </div>
                            <div>
                                <strong>✓ Professional tone:</strong> Maintain a friendly but professional voice
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock"></i> Recent Emails
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recentEmailsLoading" class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="recentEmailsContent" style="display: none;">
                            <div id="recentEmailsList"></div>
                        </div>
                        <div id="recentEmailsEmpty" class="text-center py-3 text-muted" style="display: none;">
                            <i class="bi bi-envelope"></i>
                            <div class="small">No recent emails</div>
                        </div>
                    </div>
                </div>
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
                    <i class="bi bi-clock-history"></i> Email History
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
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody"></tbody>
                        </table>
                    </div>
                    <nav id="historyPagination"></nav>
                </div>
                <div id="historyEmpty" class="text-center py-4" style="display: none;">
                    <i class="bi bi-envelope text-muted fs-1"></i>
                    <p class="text-muted mt-2">No emails sent yet</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-detect base path for live hosting compatibility
const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';

let contactSuggestionsTimeout;
let currentContactQuery = '';

// Load page data on load
document.addEventListener('DOMContentLoaded', function() {
    loadEmailTemplates();
    loadRecentEmails();
    setupContactAutocomplete();
    setupFormSubmission();
});

// Setup contact autocomplete
function setupContactAutocomplete() {
    const emailToField = document.getElementById('emailTo');
    const suggestionsDiv = document.getElementById('contactSuggestions');
    
    emailToField.addEventListener('input', function(e) {
        clearTimeout(contactSuggestionsTimeout);
        const query = getLastEmailQuery(e.target.value);
        
        if (query && query.length >= 2 && query !== currentContactQuery) {
            currentContactQuery = query;
            contactSuggestionsTimeout = setTimeout(() => {
                searchContacts(query);
            }, 300);
        } else if (!query || query.length < 2) {
            hideSuggestions();
        }
    });
    
    emailToField.addEventListener('blur', function() {
        setTimeout(hideSuggestions, 200); // Delay to allow clicking suggestions
    });
    
    // Hide suggestions when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (!emailToField.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            hideSuggestions();
        }
    });
}

function getLastEmailQuery(text) {
    const cursor = document.getElementById('emailTo').selectionStart;
    const beforeCursor = text.substring(0, cursor);
    const match = beforeCursor.match(/[^,;\n\r]*$/);
    return match ? match[0].trim() : '';
}

async function searchContacts(query) {
    if (!query || query.length < 2) {
        hideSuggestions();
        return;
    }
    
    try {
        const response = await fetch(`${basePath}/api/instant-email/contacts?q=${encodeURIComponent(query)}&limit=8`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            showContactSuggestions(data.data);
        } else {
            hideSuggestions();
        }
    } catch (error) {
        console.error('Error searching contacts:', error);
        hideSuggestions();
    }
}

function showContactSuggestions(contacts) {
    const suggestionsDiv = document.getElementById('contactSuggestions');
    
    suggestionsDiv.innerHTML = contacts.map(contact => `
        <div class="suggestion-item p-2 border-bottom" style="cursor: pointer;" 
             onclick="selectContact('${contact.email}', '${contact.name}', '${contact.company || ''}')">
            <div class="d-flex align-items-center">
                <div class="me-2">
                    <i class="bi bi-person-circle text-primary"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-medium">${contact.name}</div>
                    <div class="small text-muted">${contact.email}</div>
                    ${contact.company ? `<div class="small text-muted">${contact.company}</div>` : ''}
                </div>
            </div>
        </div>
    `).join('');
    
    suggestionsDiv.style.display = 'block';
}

function selectContact(email, name, company) {
    const emailToField = document.getElementById('emailTo');
    const currentValue = emailToField.value;
    const cursor = emailToField.selectionStart;
    
    // Replace the current query with the selected email
    const beforeCursor = currentValue.substring(0, cursor);
    const afterCursor = currentValue.substring(cursor);
    const lastCommaIndex = Math.max(beforeCursor.lastIndexOf(','), beforeCursor.lastIndexOf(';'), beforeCursor.lastIndexOf('\n'));
    
    let newValue;
    if (lastCommaIndex >= 0) {
        newValue = beforeCursor.substring(0, lastCommaIndex + 1) + ' ' + email + afterCursor;
    } else {
        newValue = email + afterCursor;
    }
    
    emailToField.value = newValue;
    hideSuggestions();
    emailToField.focus();
}

function hideSuggestions() {
    document.getElementById('contactSuggestions').style.display = 'none';
}

// Load email templates
async function loadEmailTemplates() {
    try {
        const response = await fetch(`${basePath}/api/instant-email/templates`);
        const data = await response.json();
        
        if (data.success) {
            displayTemplates(data.data);
        }
    } catch (error) {
        console.error('Error loading templates:', error);
    } finally {
        document.getElementById('templatesLoading').style.display = 'none';
        document.getElementById('templatesContent').style.display = 'block';
    }
}

function displayTemplates(templates) {
    const templatesList = document.getElementById('templatesList');
    
    templatesList.innerHTML = templates.map(template => `
        <button type="button" class="btn btn-outline-primary btn-sm text-start" 
                onclick="applyTemplate('${template.id}', '${template.subject.replace(/'/g, "\\'")}', '${template.message.replace(/'/g, "\\'")}')">
            <div class="fw-medium">${template.name}</div>
            <div class="small text-muted">${template.subject}</div>
        </button>
    `).join('');
}

function applyTemplate(templateId, subject, message) {
    document.getElementById('emailSubject').value = subject;
    document.getElementById('emailMessage').value = message;
}

// Load recent emails
async function loadRecentEmails() {
    try {
        const response = await fetch(`${basePath}/api/instant-email/history?limit=5`);
        const data = await response.json();
        
        const loadingEl = document.getElementById('recentEmailsLoading');
        const contentEl = document.getElementById('recentEmailsContent');
        const emptyEl = document.getElementById('recentEmailsEmpty');
        
        if (data.success && data.data.emails.length > 0) {
            displayRecentEmails(data.data.emails);
            contentEl.style.display = 'block';
        } else {
            emptyEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading recent emails:', error);
        document.getElementById('recentEmailsEmpty').style.display = 'block';
    } finally {
        document.getElementById('recentEmailsLoading').style.display = 'none';
    }
}

function displayRecentEmails(emails) {
    const recentEmailsList = document.getElementById('recentEmailsList');
    
    recentEmailsList.innerHTML = emails.map(email => `
        <div class="d-flex align-items-center mb-2 p-2 border rounded">
            <div class="me-2">
                <i class="bi bi-envelope${email.status === 'sent' ? '-check' : '-x'} 
                   text-${email.status === 'sent' ? 'success' : 'danger'}"></i>
            </div>
            <div class="flex-grow-1 small">
                <div class="fw-medium">${email.recipient_email}</div>
                <div class="text-muted">${email.subject}</div>
                <div class="text-muted">${new Date(email.sent_at).toLocaleDateString()}</div>
            </div>
        </div>
    `).join('');
}

// Form submission
function setupFormSubmission() {
    document.getElementById('instantEmailForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        await sendInstantEmail();
    });
}

async function sendInstantEmail() {
    const form = document.getElementById('instantEmailForm');
    const sendBtn = document.getElementById('sendEmailBtn');
    const progressDiv = document.getElementById('sendingProgress');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Get form data
    const formData = {
        to: document.getElementById('emailTo').value.trim(),
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
        const response = await fetch(`${basePath}/api/instant-email/send`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', result.message || 'Email sent successfully!');
            clearForm();
            loadRecentEmails(); // Refresh recent emails
        } else {
            showAlert('danger', result.error || 'Failed to send email');
        }
    } catch (error) {
        console.error('Error sending email:', error);
        showAlert('danger', 'Network error. Please try again.');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send"></i> Send Email';
        progressDiv.style.display = 'none';
    }
}

function clearForm() {
    document.getElementById('instantEmailForm').reset();
    hideSuggestions();
}

function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert at top of main content
    const mainContent = document.querySelector('.main-content .container-fluid');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
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

.card-body {
    padding: 1.25rem;
}

/* Form styling */
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

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
    padding: 0.5rem 1rem;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn-outline-primary.btn-sm {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}

/* Contact suggestions */
.suggestion-item:hover {
    background-color: #f8f9fa;
}

#contactSuggestions {
    border-top: none !important;
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
    max-height: 200px;
    overflow-y: auto;
}

/* Progress bar */
.progress {
    height: 4px;
    border-radius: 2px;
}

/* Card titles */
.card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #495057;
}

/* Alert styling */
.alert {
    margin-bottom: 1.5rem;
    border-radius: 8px;
    border: none;
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

/* Table styling */
.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.75rem;
}

.table td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}

/* Badge styling */
.badge {
    font-weight: 500;
    border-radius: 6px;
    padding: 0.35em 0.65em;
}

/* Responsive improvements */
@media (max-width: 992px) {
    .col-lg-4 .card {
        margin-top: 1rem;
    }
    
    .main-content {
        padding: 15px;
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .h3 {
        font-size: 1.5rem;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 10px;
    }
    
    .card-header {
        padding: 0.75rem 1rem;
    }
    
    .card-body {
        padding: 0.75rem;
    }
    
    .form-control {
        font-size: 14px;
    }
    
    .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

/* Sidebar mobile toggle */
@media (max-width: 768px) {
    .modern-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .modern-sidebar.show {
        transform: translateX(0);
    }
    
    /* Add overlay when sidebar is open on mobile */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1030;
        display: none;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
}

/* Loading states */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Improved focus states */
.form-control:focus,
.btn:focus {
    outline: none;
}

/* Email composition specific styles */
.email-composer {
    background: white;
    border-radius: 12px;
}

.template-btn {
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Quick actions improvements */
.quick-actions .btn {
    justify-content: flex-start;
    text-align: left;
}

/* Recent emails styling */
.recent-emails .email-item {
    border-left: 3px solid transparent;
    transition: border-color 0.2s ease;
}

.recent-emails .email-item:hover {
    border-left-color: #007bff;
    background-color: #f8f9fa;
}
</style>

<?php include __DIR__ . "/../components/footer.php"; ?>
