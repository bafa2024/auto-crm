<?php
// views/dashboard/employee_management.php
require_once __DIR__ . '/../../config/base_path.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . base_path('login'));
    exit;
}
?>
<?php include __DIR__ . '/../components/header.php'; ?>
<?php include __DIR__ . '/../components/sidebar.php'; ?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    overflow-y: auto;
    z-index: 100;
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
}
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background-color: #ffffff;
}
.stat-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-radius: 12px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.sidebar-link {
    color: #495057;
    text-decoration: none;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    border-radius: 8px;
    margin: 4px 10px;
    transition: all 0.3s;
}
.sidebar-link:hover {
    background-color: #e9ecef;
    color: #212529;
}
.sidebar-link.active {
    background-color: #5B5FDE;
    color: white;
}
.sidebar-link i {
    margin-right: 10px;
    width: 20px;
}
.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-radius: 12px;
    margin-bottom: 2em;
    transition: box-shadow 0.2s, transform 0.2s;
}
.card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.13);
    transform: translateY(-2px);
}
.card-title {
    font-weight: 600;
    font-size: 1.2em;
    margin-bottom: 1em;
    display: flex;
    align-items: center;
    gap: 0.5em;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 1em;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}
.table th, .table td {
    border: 1px solid #eee;
    padding: 0.75em;
    text-align: left;
}
.table-striped tbody tr:nth-of-type(odd) {
    background-color: #f8f9fa;
}
.table-hover tbody tr:hover {
    background-color: #e9ecef;
}
.empty-msg, .loading {
    color: #888;
    font-style: italic;
}
.success-msg, .error-msg {
    margin-bottom: 1em;
}
.actions .btn {
    margin-right: 0.3em;
    margin-bottom: 0.2em;
}
.section-divider {
    border-bottom: 1px solid #e9ecef;
    margin: 2em 0 1.5em 0;
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .main-content {
        margin-left: 0;
    }
}
@media (max-width: 700px) { .table, .card { font-size: 0.95em; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0"><i class="bi bi-person-badge"></i> Employee Management</h2>
                <p class="text-muted">Manage your team members and their roles</p>
            </div>
        </div>
        <div id="msg"></div>
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="card-title mb-0"><i class="bi bi-people"></i> Employees</div>
                            <button class="btn btn-primary" onclick="showAddEmployeeModal()">
                                <i class="bi bi-plus-circle"></i> Add Employee
                            </button>
                        </div>
                        <input type="text" id="search" class="form-control mb-3" placeholder="Search by name, email, role, status">
                        <div id="employees-loading" class="loading" style="display:none;">Loading employees...</div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Teams</th><th>Permissions</th><th>Actions</th></tr>
                                </thead>
                                <tbody id="employees"></tbody>
                            </table>
                        </div>
                        <div id="employees-empty" class="empty-msg" style="display:none;">No employees found.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showMsg(msg, type) {
    const el = document.getElementById('msg');
    if (type === 'success' || type === 'error') {
        el.innerHTML = `<div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    } else {
        el.innerHTML = `<div class="${type}-msg">${msg}</div>`;
    }
    setTimeout(() => { el.innerHTML = ''; }, 3500);
}
function fetchEmployees(q = '') {
    document.getElementById('employees-loading').style.display = '';
    const url = '<?php echo base_path("api/employees/list"); ?>' + (q ? ('?q=' + encodeURIComponent(q)) : '');
    console.log('Fetching from:', url);
    console.log('Full URL:', window.location.origin + url);
    fetch(url)
        .then(r => {
            console.log('Response status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('Response data:', data);
            document.getElementById('employees-loading').style.display = 'none';
            const tbody = document.getElementById('employees');
            tbody.innerHTML = '';
            if (data.success && Array.isArray(data.data) && data.data.length) {
                // Clear and repopulate employees data
                employeesData = {};
                data.data.forEach(emp => {
                    employeesData[emp.id] = emp;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${emp.first_name} ${emp.last_name}</td><td>${emp.email}</td><td>${emp.role}</td><td>${emp.status}</td><td><span id='teams-${emp.id}'></span></td><td>
                        ${(emp.role === 'agent' || emp.role === 'manager') ? `<button class='btn btn-sm btn-outline-secondary' title='Manage Permissions' onclick='managePermissions(${emp.id})'><i class='bi bi-shield-lock'></i> Permissions</button>` : '-'}
                    </td><td class='actions'>
                        ${(emp.role === 'agent' || emp.role === 'manager') ? `<button class='btn btn-sm btn-outline-success' title='Login as ${emp.first_name}' onclick='loginAsEmployee(${emp.id})'><i class='bi bi-box-arrow-in-right'></i></button>` : ''}
                        <button class='btn btn-sm btn-outline-primary' title='Edit' onclick='editEmployee(${emp.id})'><i class='bi bi-pencil'></i></button>
                        <button class='btn btn-sm btn-outline-danger' title='Delete' onclick='deleteEmployee(${emp.id})'><i class='bi bi-trash'></i></button>
                    </td>`;
                    // Store employee data for edit function
                    tr.dataset.employee = JSON.stringify(emp);
                    tbody.appendChild(tr);
                    // Delay fetching teams to avoid too many concurrent requests
                    setTimeout(() => fetchEmployeeTeams(emp.id), 100);
                });
                document.getElementById('employees-empty').style.display = 'none';
            } else {
                document.getElementById('employees-empty').style.display = '';
            }
        })
        .catch((err) => {
            document.getElementById('employees-loading').style.display = 'none';
            console.error('Error fetching employees:', err);
            showMsg('Failed to load employees: ' + err.message, 'error');
        });
}
function fetchEmployeeTeams(userId) {
    fetch(`<?php echo base_path('api/employees'); ?>/${userId}/teams`)
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('teams-' + userId);
            if (el && data.success && Array.isArray(data.data)) {
                el.innerHTML = data.data.map(t => t.name).join(', ');
            } else if (el) {
                el.innerHTML = '';
            }
        })
        .catch(err => {
            console.error('Error fetching teams for user ' + userId + ':', err);
            const el = document.getElementById('teams-' + userId);
            if (el) el.innerHTML = '';
        });
}
// Store employees data for editing
let employeesData = {};

function editEmployee(id) {
    const emp = employeesData[id];
    if (!emp) {
        showMsg('Employee data not found. Please refresh the page.', 'error');
        return;
    }
    
    // Create a modal for editing
    const modalHtml = `
        <div class="modal fade" id="editEmployeeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-employee-form">
                            <input type="hidden" id="edit-id" value="${emp.id}">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit-first-name" value="${emp.first_name}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit-last-name" value="${emp.last_name}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit-email" value="${emp.email}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" id="edit-role">
                                    <option value="agent" ${emp.role === 'agent' ? 'selected' : ''}>Agent (Employee)</option>
                                    <option value="manager" ${emp.role === 'manager' ? 'selected' : ''}>Manager (Employee)</option>
                                    <option value="user" ${emp.role === 'user' ? 'selected' : ''}>User</option>
                                    <option value="admin" ${emp.role === 'admin' ? 'selected' : ''}>Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="edit-status">
                                    <option value="active" ${emp.status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${emp.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveEmployeeEdit()">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('editEmployeeModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    modal.show();
}

function saveEmployeeEdit() {
    const id = parseInt(document.getElementById('edit-id').value);
    const first_name = document.getElementById('edit-first-name').value;
    const last_name = document.getElementById('edit-last-name').value;
    const email = document.getElementById('edit-email').value;
    const role = document.getElementById('edit-role').value;
    const status = document.getElementById('edit-status').value;
    
    const editUrl = '<?php echo base_path('api/employees/edit'); ?>';
    console.log('Edit URL:', editUrl);
    console.log('Edit data:', { id, first_name, last_name, email, role, status });
    
    fetch(editUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, first_name, last_name, email, role, status })
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Network response was not ok: ' + r.status);
        }
        return r.text(); // Get response as text first
    })
    .then(text => {
        try {
            return JSON.parse(text); // Try to parse as JSON
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (data.success) {
            showMsg('Employee updated successfully!', 'success');
            // Hide modal
            bootstrap.Modal.getInstance(document.getElementById('editEmployeeModal')).hide();
            // Refresh employee list
            fetchEmployees();
        } else {
            showMsg(data.message || 'Failed to update employee.', 'error');
        }
    })
    .catch(err => {
        console.error('Error details:', err);
        showMsg('Error updating employee: ' + err.message, 'error');
    });
}
function deleteEmployee(id) {
    const emp = employeesData[id];
    if (!emp) {
        showMsg('Employee data not found. Please refresh the page.', 'error');
        return;
    }
    
    // Create confirmation modal
    const modalHtml = `
        <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this employee?</p>
                        <p><strong>${emp.first_name} ${emp.last_name}</strong> (${emp.email})</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteEmployee(${id})">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('deleteEmployeeModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
    modal.show();
}

function confirmDeleteEmployee(id) {
    const deleteUrl = '<?php echo base_path('api/employees/delete'); ?>';
    console.log('Delete URL:', deleteUrl);
    console.log('Delete data:', { id: parseInt(id) });
    
    fetch(deleteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id) })
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Network response was not ok: ' + r.status);
        }
        return r.text(); // Get response as text first
    })
    .then(text => {
        try {
            return JSON.parse(text); // Try to parse as JSON
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (data.success) {
            showMsg('Employee deleted successfully!', 'success');
            // Hide modal
            bootstrap.Modal.getInstance(document.getElementById('deleteEmployeeModal')).hide();
            // Refresh employee list
            fetchEmployees();
        } else {
            showMsg(data.message || 'Failed to delete employee.', 'error');
        }
    })
    .catch(err => {
        console.error('Error details:', err);
        showMsg('Error deleting employee: ' + err.message, 'error');
    });
}
// Removed old form handler since we're using modal now
document.getElementById('search').oninput = function(e) {
    fetchEmployees(e.target.value);
};
// Login as Employee function
function loginAsEmployee(id) {
    const emp = employeesData[id];
    if (!emp) {
        showMsg('Employee data not found. Please refresh the page.', 'error');
        return;
    }
    
    // Create confirmation modal
    const modalHtml = `
        <div class="modal fade" id="loginAsEmployeeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Login as Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to login as:</p>
                        <p><strong>${emp.first_name} ${emp.last_name}</strong></p>
                        <p>Email: ${emp.email}</p>
                        <p>Role: ${emp.role}</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> This will log you out of your current admin session.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="confirmLoginAsEmployee(${id})">Login as ${emp.first_name}</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('loginAsEmployeeModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('loginAsEmployeeModal'));
    modal.show();
}

function confirmLoginAsEmployee(id) {
    const emp = employeesData[id];
    if (!emp) {
        showMsg('Employee data not found.', 'error');
        return;
    }
    
    // Create a form to submit login
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo base_path("api/auth/admin-login-as-employee"); ?>';
    form.style.display = 'none';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'employee_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    
    // Submit via fetch to handle response
    fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ employee_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Redirect to employee dashboard
            window.location.href = '<?php echo base_path("employee/dashboard"); ?>';
        } else {
            showMsg(data.message || 'Failed to login as employee.', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showMsg('Error logging in as employee.', 'error');
    });
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    fetchEmployees();
});
// Permissions management
function managePermissions(userId) {
    const emp = employeesData[userId];
    if (!emp) return;
    
    // Fetch current permissions
    fetch(`<?php echo base_path('api/employees'); ?>/${userId}/permissions`)
        .then(r => r.json())
        .then(data => {
            const permissions = data.data || {};
            
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'permissionsModal';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Manage Permissions - ${emp.first_name} ${emp.last_name}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">Configure what this employee can do in the system.</p>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_upload" ${permissions.can_upload_contacts ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_upload">
                                    <strong>Upload Contacts</strong><br>
                                    <small class="text-muted">Can upload CSV/Excel files to add contacts</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_create" ${permissions.can_create_campaigns ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_create">
                                    <strong>Create Campaigns</strong><br>
                                    <small class="text-muted">Can create new email campaigns</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_send" ${permissions.can_send_campaigns ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_send">
                                    <strong>Send Campaigns</strong><br>
                                    <small class="text-muted">Can send/activate email campaigns</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_edit" ${permissions.can_edit_campaigns ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_edit">
                                    <strong>Edit Campaigns</strong><br>
                                    <small class="text-muted">Can modify existing campaigns</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_delete" ${permissions.can_delete_campaigns ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_delete">
                                    <strong>Delete Campaigns</strong><br>
                                    <small class="text-muted">Can permanently delete campaigns</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_export" ${permissions.can_export_contacts ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_export">
                                    <strong>Export Contacts</strong><br>
                                    <small class="text-muted">Can download contact lists</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="perm_view_all" ${permissions.can_view_all_campaigns ? 'checked' : ''}>
                                <label class="form-check-label" for="perm_view_all">
                                    <strong>View All Campaigns</strong><br>
                                    <small class="text-muted">Can see campaigns created by other employees</small>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="savePermissions(${userId})">Save Permissions</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        })
        .catch(() => {
            showMsg('Failed to load permissions.', 'error');
        });
}

function savePermissions(userId) {
    const permissions = {
        can_upload_contacts: document.getElementById('perm_upload').checked,
        can_create_campaigns: document.getElementById('perm_create').checked,
        can_send_campaigns: document.getElementById('perm_send').checked,
        can_edit_campaigns: document.getElementById('perm_edit').checked,
        can_delete_campaigns: document.getElementById('perm_delete').checked,
        can_export_contacts: document.getElementById('perm_export').checked,
        can_view_all_campaigns: document.getElementById('perm_view_all').checked
    };
    
    fetch(`<?php echo base_path('api/employees'); ?>/${userId}/permissions`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(permissions)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMsg('Permissions updated successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('permissionsModal')).hide();
        } else {
            showMsg(data.message || 'Failed to update permissions.', 'error');
        }
    })
    .catch(() => {
        showMsg('Failed to update permissions.', 'error');
    });
}

// Add Employee Modal
function showAddEmployeeModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'addEmployeeModal';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="add-employee-modal-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_last_name" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="add_password" name="password" required minlength="6">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="agent">Agent (Employee)</option>
                                <option value="manager">Manager (Employee)</option>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    
    // Handle form submission
    document.getElementById('add-employee-modal-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
        
        fetch('<?php echo base_path('api/employees/create'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                first_name: form.first_name.value,
                last_name: form.last_name.value,
                email: form.email.value,
                password: form.password.value,
                role: form.role.value,
                status: form.status.value
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Employee added successfully!', 'success');
                bsModal.hide();
                fetchEmployees();
            } else {
                showMsg(data.message || 'Failed to add employee', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(() => {
            showMsg('Failed to add employee', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    };
    
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
    
    bsModal.show();
}
</script>

<?php include __DIR__ . '/../components/footer.php'; ?> 