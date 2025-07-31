<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Get user permissions
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

$database = new Database();
$db = $database->getConnection();

$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Helper function to check permissions
function hasPermission($permissions, $permission) {
    return isset($permissions[$permission]) && $permissions[$permission];
}

// Check if user has permission to view contacts
if (!hasPermission($permissions, 'can_upload_contacts')) {
    header("Location: " . base_path('employee/email-dashboard'));
    exit;
}

include __DIR__ . "/../components/header.php";
include __DIR__ . "/../components/employee-sidebar.php";
?>

<div class="main-content" style="margin-left: 260px;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">My Contacts</h1>
                <p class="text-muted">Manage your assigned contacts</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshContacts()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                <button class="btn btn-primary" onclick="addNewContact()">
                    <i class="bi bi-person-plus"></i> Add Contact
                </button>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_export_contacts')): ?>
                <button class="btn btn-success" onclick="exportContacts()">
                    <i class="bi bi-download"></i> Export
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search contacts...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary w-100" onclick="applyFilters()">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contacts Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people"></i> Contacts List
                </h5>
            </div>
            <div class="card-body">
                <div id="contactsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading contacts...</p>
                </div>
                
                <div id="contactsContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Last Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contactsTable">
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Contacts pagination" class="mt-4">
                        <ul class="pagination justify-content-center" id="pagination">
                        </ul>
                    </nav>
                </div>
                
                <!-- No Contacts Message -->
                <div id="noContactsMessage" style="display: none;" class="text-center py-5">
                    <i class="bi bi-people fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No contacts found</h5>
                    <p class="text-muted">Start by adding your first contact</p>
                    <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                    <button class="btn btn-primary" onclick="addNewContact()">
                        <i class="bi bi-person-plus"></i> Add Contact
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalTitle">Add New Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="contactForm">
                    <div class="mb-3">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" required>
                    </div>
                    <div class="mb-3">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone">
                    </div>
                    <div class="mb-3">
                        <label for="company" class="form-label">Company</label>
                        <input type="text" class="form-control" id="company">
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status">
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveContact()">Save Contact</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentContactId = null;

// Load contacts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadContacts();
});

function loadContacts(page = 1) {
    currentPage = page;
    
    document.getElementById('contactsLoading').style.display = 'block';
    document.getElementById('contactsContent').style.display = 'none';
    document.getElementById('noContactsMessage').style.display = 'none';
    
    const searchTerm = document.getElementById('searchInput').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    fetch(`<?php echo base_path('api/contacts'); ?>?page=${page}&search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter)}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('contactsLoading').style.display = 'none';
            
            if (data.success) {
                if (data.contacts.length === 0) {
                    document.getElementById('noContactsMessage').style.display = 'block';
                } else {
                    displayContacts(data.contacts);
                    totalPages = data.totalPages;
                    displayPagination();
                    document.getElementById('contactsContent').style.display = 'block';
                }
            } else {
                alert('Failed to load contacts: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('contactsLoading').style.display = 'none';
            console.error('Error:', error);
            alert('Failed to load contacts');
        });
}

function displayContacts(contacts) {
    const tbody = document.getElementById('contactsTable');
    tbody.innerHTML = '';
    
    contacts.forEach(contact => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${contact.first_name} ${contact.last_name}</td>
            <td>${contact.email}</td>
            <td>${contact.phone || '-'}</td>
            <td>${contact.company || '-'}</td>
            <td><span class="badge bg-${getStatusColor(contact.status)}">${contact.status}</span></td>
            <td>${contact.last_contact ? new Date(contact.last_contact).toLocaleDateString() : '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editContact(${contact.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteContact(${contact.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function displayPagination() {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous button
    if (currentPage > 1) {
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item';
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${currentPage - 1})">Previous</a>`;
        pagination.appendChild(prevLi);
    }
    
    // Page numbers
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${i})">${i}</a>`;
        pagination.appendChild(li);
    }
    
    // Next button
    if (currentPage < totalPages) {
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item';
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="loadContacts(${currentPage + 1})">Next</a>`;
        pagination.appendChild(nextLi);
    }
}

function getStatusColor(status) {
    const colors = {
        'new': 'primary',
        'contacted': 'info',
        'qualified': 'warning',
        'converted': 'success',
        'lost': 'danger'
    };
    return colors[status] || 'secondary';
}

function applyFilters() {
    loadContacts(1);
}

function refreshContacts() {
    loadContacts(currentPage);
}

function addNewContact() {
    currentContactId = null;
    document.getElementById('contactModalTitle').textContent = 'Add New Contact';
    document.getElementById('contactForm').reset();
    new bootstrap.Modal(document.getElementById('contactModal')).show();
}

function editContact(contactId) {
    currentContactId = contactId;
    document.getElementById('contactModalTitle').textContent = 'Edit Contact';
    
    // Load contact data
    fetch(`<?php echo base_path('api/contacts'); ?>/${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const contact = data.contact;
                document.getElementById('firstName').value = contact.first_name;
                document.getElementById('lastName').value = contact.last_name;
                document.getElementById('email').value = contact.email;
                document.getElementById('phone').value = contact.phone || '';
                document.getElementById('company').value = contact.company || '';
                document.getElementById('status').value = contact.status;
                document.getElementById('notes').value = contact.notes || '';
                
                new bootstrap.Modal(document.getElementById('contactModal')).show();
            } else {
                alert('Failed to load contact: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load contact');
        });
}

function saveContact() {
    const formData = {
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        company: document.getElementById('company').value,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value
    };
    
    const url = currentContactId 
        ? `<?php echo base_path('api/contacts'); ?>/${currentContactId}`
        : `<?php echo base_path('api/contacts'); ?>`;
    
    const method = currentContactId ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
            loadContacts(currentPage);
            alert(currentContactId ? 'Contact updated successfully!' : 'Contact added successfully!');
        } else {
            alert(data.message || 'Failed to save contact');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save contact');
    });
}

function deleteContact(contactId) {
    if (confirm('Are you sure you want to delete this contact?')) {
        fetch(`<?php echo base_path('api/contacts'); ?>/${contactId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadContacts(currentPage);
                alert('Contact deleted successfully!');
            } else {
                alert(data.message || 'Failed to delete contact');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete contact');
        });
    }
}

function exportContacts() {
    const searchTerm = document.getElementById('searchInput').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    const url = `<?php echo base_path('api/contacts/export'); ?>?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusFilter)}`;
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = url;
    link.download = 'contacts.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Search input event listener
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 