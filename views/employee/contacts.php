<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
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
                <h1 class="h3 mb-0">My Contacts</h1>
                <p class="text-muted">Manage your assigned contacts</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshContacts()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-primary" onclick="addNewContact()">
                    <i class="bi bi-person-plus"></i> Add Contact
                </button>
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
                
                <div id="contactsEmpty" class="text-center py-5" style="display: none;">
                    <i class="bi bi-people text-muted fs-1"></i>
                    <h5 class="text-muted mt-3">No contacts found</h5>
                    <p class="text-muted">You don't have any contacts assigned yet.</p>
                    <button class="btn btn-primary" onclick="addNewContact()">
                        <i class="bi bi-person-plus"></i> Add Your First Contact
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contact Details Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contact Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contactModalBody">
                <!-- Contact details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editContact()">Edit Contact</button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-detect base path for live hosting compatibility
const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';

let currentPage = 1;
let totalPages = 1;
let currentFilters = {};

// Load contacts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadContacts();
    
    // Add search functionality
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
});

async function loadContacts(page = 1, filters = {}) {
    const loadingEl = document.getElementById('contactsLoading');
    const contentEl = document.getElementById('contactsContent');
    const emptyEl = document.getElementById('contactsEmpty');
    const tableEl = document.getElementById('contactsTable');

    try {
        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';
        emptyEl.style.display = 'none';

        // Build query parameters
        const params = new URLSearchParams({
            page: page,
            limit: 10
        });
        
        if (filters.search) params.append('search', filters.search);
        if (filters.status) params.append('status', filters.status);

        const response = await fetch(basePath + '/api/employee/contacts?' + params.toString());
        const data = await response.json();

        if (response.ok && data.success) {
            if (data.contacts && data.contacts.length > 0) {
                tableEl.innerHTML = data.contacts.map(contact => `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                    ${contact.first_name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="fw-medium">${contact.first_name} ${contact.last_name}</div>
                                    <small class="text-muted">ID: ${contact.id}</small>
                                </div>
                            </div>
                        </td>
                        <td>${contact.email || '-'}</td>
                        <td>${contact.phone || '-'}</td>
                        <td>${contact.company || '-'}</td>
                        <td>
                            <span class="badge bg-${getStatusColor(contact.status)}">${contact.status}</span>
                        </td>
                        <td>${contact.last_contacted ? new Date(contact.last_contacted).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewContact(${contact.id})" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-success" onclick="updateStatus(${contact.id})" title="Update Status">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                
                // Update pagination
                currentPage = data.pagination.current_page || 1;
                totalPages = data.pagination.total_pages || 1;
                updatePagination();
                
                contentEl.style.display = 'block';
            } else {
                emptyEl.style.display = 'block';
            }
        } else {
            throw new Error(data.message || 'Failed to load contacts');
        }
    } catch (error) {
        console.error('Error loading contacts:', error);
        emptyEl.style.display = 'block';
    } finally {
        loadingEl.style.display = 'none';
    }
}

function updatePagination() {
    const paginationEl = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        paginationEl.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Previous button
    paginationHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
        </li>
    `;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            paginationHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next button
    paginationHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
        </li>
    `;
    
    paginationEl.innerHTML = paginationHTML;
}

function changePage(page) {
    if (page >= 1 && page <= totalPages) {
        loadContacts(page, currentFilters);
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    
    currentFilters = {};
    if (search) currentFilters.search = search;
    if (status) currentFilters.status = status;
    
    loadContacts(1, currentFilters);
}

function refreshContacts() {
    loadContacts(currentPage, currentFilters);
}

function getStatusColor(status) {
    const colors = {
        'new': 'primary',
        'contacted': 'warning',
        'qualified': 'info',
        'converted': 'success',
        'lost': 'danger'
    };
    return colors[status] || 'secondary';
}

async function viewContact(id) {
    try {
        const response = await fetch(basePath + '/api/employee/contacts/' + id);
        const data = await response.json();
        
        if (response.ok && data.success) {
            const contact = data.contact;
            const modalBody = document.getElementById('contactModalBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Name:</strong> ${contact.first_name} ${contact.last_name}</p>
                        <p><strong>Email:</strong> ${contact.email || 'Not provided'}</p>
                        <p><strong>Phone:</strong> ${contact.phone || 'Not provided'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Business Information</h6>
                        <p><strong>Company:</strong> ${contact.company || 'Not provided'}</p>
                        <p><strong>Job Title:</strong> ${contact.job_title || 'Not provided'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(contact.status)}">${contact.status}</span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Notes</h6>
                        <p>${contact.notes || 'No notes available'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>Created:</strong> ${new Date(contact.created_at).toLocaleString()}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Last Updated:</strong> ${new Date(contact.updated_at).toLocaleString()}</p>
                    </div>
                </div>
            `;
            
            // Store contact ID for edit function
            modalBody.dataset.contactId = id;
            
            const modal = new bootstrap.Modal(document.getElementById('contactModal'));
            modal.show();
        } else {
            alert('Failed to load contact details: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading contact details:', error);
        alert('Error loading contact details');
    }
}

function editContact() {
    const contactId = document.getElementById('contactModalBody').dataset.contactId;
    if (contactId) {
        window.location.href = basePath + '/employee/contacts/' + contactId + '/edit';
    }
}

function updateStatus(contactId) {
    // For now, just redirect to edit page
    window.location.href = basePath + '/employee/contacts/' + contactId + '/edit';
}

function addNewContact() {
    window.location.href = basePath + '/employee/contacts/add';
}
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 