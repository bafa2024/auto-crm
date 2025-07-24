<?php
// views/dashboard/employee_management.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Management</title>
    <link rel="stylesheet" href="/public/css/styles.css">
    <style>
        .panel { border: 1px solid #ccc; border-radius: 6px; padding: 1em; margin-bottom: 2em; background: #fff; }
        .panel-title { font-weight: bold; margin-bottom: 0.5em; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
        .table th, .table td { border: 1px solid #eee; padding: 0.5em; text-align: left; }
        .empty-msg { color: #888; font-style: italic; }
        .loading { color: #888; font-style: italic; }
        .success-msg { color: green; margin-bottom: 1em; }
        .error-msg { color: red; margin-bottom: 1em; }
        .actions button { margin-right: 0.5em; }
        @media (max-width: 700px) { .table, .panel { font-size: 0.95em; } }
    </style>
</head>
<body>
    <div class="container">
        <h2>Employee Management</h2>
        <div id="msg"></div>
        <div class="panel">
            <div class="panel-title">Add New Employee</div>
            <form id="add-employee-form" autocomplete="off">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button type="submit">Add Employee</button>
            </form>
        </div>
        <div class="panel">
            <div class="panel-title">Employees</div>
            <input type="text" id="search" placeholder="Search by name, email, role, status" style="margin-bottom:1em;">
            <div id="employees-loading" class="loading" style="display:none;">Loading employees...</div>
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Teams</th><th>Actions</th></tr>
                </thead>
                <tbody id="employees"></tbody>
            </table>
            <div id="employees-empty" class="empty-msg" style="display:none;">No employees found.</div>
        </div>
    </div>
    <script>
    // TODO: Implement backend endpoints:
    // - GET /api/employees/list?q=... (search/filter)
    // - POST /api/employees/create
    // - POST /api/employees/edit
    // - POST /api/employees/delete
    // - GET /api/employees/{id}/teams
    // - POST /api/employees/{id}/add-to-team
    // - POST /api/employees/{id}/remove-from-team

    function showMsg(msg, type) {
        const el = document.getElementById('msg');
        el.innerHTML = `<div class="${type}-msg">${msg}</div>`;
        setTimeout(() => { el.innerHTML = ''; }, 3000);
    }
    function fetchEmployees(q = '') {
        document.getElementById('employees-loading').style.display = '';
        fetch('/api/employees/list' + (q ? ('?q=' + encodeURIComponent(q)) : ''))
            .then(r => r.json())
            .then(data => {
                document.getElementById('employees-loading').style.display = 'none';
                const tbody = document.getElementById('employees');
                tbody.innerHTML = '';
                if (data.success && Array.isArray(data.data) && data.data.length) {
                    data.data.forEach(emp => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${emp.first_name} ${emp.last_name}</td><td>${emp.email}</td><td>${emp.role}</td><td>${emp.status}</td><td><span id='teams-${emp.id}'></span></td><td class='actions'>
                            <button onclick='showEditEmployeeForm(${JSON.stringify(emp)})'>Edit</button>
                            <button onclick='deleteEmployee(${emp.id})'>Delete</button>
                        </td>`;
                        tbody.appendChild(tr);
                        fetchEmployeeTeams(emp.id);
                    });
                    document.getElementById('employees-empty').style.display = 'none';
                } else {
                    document.getElementById('employees-empty').style.display = '';
                }
            })
            .catch(() => {
                document.getElementById('employees-loading').style.display = 'none';
                showMsg('Failed to load employees.', 'error');
            });
    }
    function fetchEmployeeTeams(userId) {
        fetch(`/api/employees/${userId}/teams`)
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('teams-' + userId);
                if (data.success && Array.isArray(data.data)) {
                    el.innerHTML = data.data.map(t => t.name).join(', ');
                } else {
                    el.innerHTML = '';
                }
            });
    }
    function showEditEmployeeForm(emp) {
        const name = prompt('Edit name:', emp.first_name + ' ' + emp.last_name);
        if (name === null) return;
        const [first_name, ...lastArr] = name.split(' ');
        const last_name = lastArr.join(' ');
        const email = prompt('Edit email:', emp.email);
        if (email === null) return;
        const role = prompt('Edit role (user/admin):', emp.role);
        if (role === null) return;
        const status = prompt('Edit status (active/inactive):', emp.status);
        if (status === null) return;
        fetch('/api/employees/edit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: emp.id, first_name, last_name, email, role, status })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Employee updated!', 'success');
                fetchEmployees();
            } else {
                showMsg(data.message || 'Failed to update employee.', 'error');
            }
        });
    }
    function deleteEmployee(id) {
        if (!confirm('Are you sure you want to delete this employee?')) return;
        fetch('/api/employees/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Employee deleted!', 'success');
                fetchEmployees();
            } else {
                showMsg(data.message || 'Failed to delete employee.', 'error');
            }
        });
    }
    document.getElementById('add-employee-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/employees/create', {
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
                showMsg('Employee added!', 'success');
                form.reset();
                fetchEmployees();
            } else {
                showMsg(data.message || 'Failed to add employee.', 'error');
            }
        });
    };
    document.getElementById('search').oninput = function(e) {
        fetchEmployees(e.target.value);
    };
    // Initial load
    fetchEmployees();
    </script>
</body>
</html> 