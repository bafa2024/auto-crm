<?php
// views/dashboard/team_management.php
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
    <title>Team Management</title>
    <link rel="stylesheet" href="/public/css/styles.css">
    <style>
        .team-list, .member-list, .privilege-list { margin-bottom: 2em; }
        .panel { border: 1px solid #ccc; border-radius: 6px; padding: 1em; margin-bottom: 2em; background: #fff; }
        .panel-title { font-weight: bold; margin-bottom: 0.5em; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
        .table th, .table td { border: 1px solid #eee; padding: 0.5em; text-align: left; }
        .empty-msg { color: #888; font-style: italic; }
        .loading { color: #888; font-style: italic; }
        .success-msg { color: green; margin-bottom: 1em; }
        .error-msg { color: red; margin-bottom: 1em; }
        .toggle-switch { cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Team Management</h2>
        <div id="msg"></div>
        <div class="panel team-list">
            <div class="panel-title">Teams</div>
            <div id="teams-loading" class="loading" style="display:none;">Loading teams...</div>
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Description</th><th>Actions</th></tr>
                </thead>
                <tbody id="teams"></tbody>
            </table>
            <div id="teams-empty" class="empty-msg" style="display:none;">No teams found.</div>
            <form id="create-team-form">
                <input type="text" name="name" placeholder="Team Name" required>
                <input type="text" name="description" placeholder="Description">
                <button type="submit">Create Team</button>
            </form>
        </div>
        <div class="panel member-list" style="display:none;">
            <div class="panel-title">Team Members (<span id="selected-team-name"></span>)</div>
            <div id="members-loading" class="loading" style="display:none;">Loading members...</div>
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Email</th><th>Role</th><th>Actions</th></tr>
                </thead>
                <tbody id="members"></tbody>
            </table>
            <div id="members-empty" class="empty-msg" style="display:none;">No members in this team.</div>
            <form id="add-member-form" autocomplete="off">
                <input type="text" id="user-search" placeholder="Search user by name or email" required>
                <input type="hidden" name="user_id" id="user-id-hidden">
                <div id="user-suggestions" style="position:relative;z-index:10;background:#fff;border:1px solid #ccc;display:none;"></div>
                <select name="role">
                    <option value="worker">Worker</option>
                    <option value="owner">Owner</option>
                </select>
                <button type="submit">Add Member</button>
            </form>
        </div>
        <div class="panel privilege-list" style="display:none;">
            <div class="panel-title">Privileges for User <span id="selected-user-id"></span> in Team <span id="selected-team-id"></span></div>
            <div id="privileges-loading" class="loading" style="display:none;">Loading privileges...</div>
            <table class="table">
                <thead>
                    <tr><th>Privilege</th><th>Allowed</th></tr>
                </thead>
                <tbody id="privileges"></tbody>
            </table>
            <form id="set-privilege-form">
                <input type="text" name="privilege" placeholder="Custom Privilege (e.g. manage_campaigns)" required>
                <select name="allowed">
                    <option value="1">Allow</option>
                    <option value="0">Deny</option>
                </select>
                <button type="submit">Set Privilege</button>
            </form>
        </div>
    </div>
    <script>
    let selectedTeamId = null;
    let selectedTeamName = '';
    let selectedUserId = null;
    const commonPrivileges = [
        'manage_campaigns', 'view_contacts', 'edit_contacts', 'delete_contacts', 'view_reports', 'manage_team'
    ];

    function showMsg(msg, type) {
        const el = document.getElementById('msg');
        el.innerHTML = `<div class="${type}-msg">${msg}</div>`;
        setTimeout(() => { el.innerHTML = ''; }, 3000);
    }

    function fetchTeams() {
        document.getElementById('teams-loading').style.display = '';
        fetch('/api/teams/list')
            .then(r => r.json())
            .then(data => {
                document.getElementById('teams-loading').style.display = 'none';
                const tbody = document.getElementById('teams');
                tbody.innerHTML = '';
                if (data.success && Array.isArray(data.data) && data.data.length) {
                    data.data.forEach(team => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${team.name}</td><td>${team.description || ''}</td><td><button onclick="selectTeam(${team.id}, '${team.name.replace(/'/g, "\\'")}')">Select</button></td>`;
                        tbody.appendChild(tr);
                    });
                    document.getElementById('teams-empty').style.display = 'none';
                } else {
                    document.getElementById('teams-empty').style.display = '';
                }
            })
            .catch(() => {
                document.getElementById('teams-loading').style.display = 'none';
                showMsg('Failed to load teams.', 'error');
            });
    }
    function selectTeam(teamId, teamName) {
        selectedTeamId = teamId;
        selectedTeamName = teamName;
        document.querySelector('.member-list').style.display = '';
        document.getElementById('selected-team-name').textContent = teamName;
        fetchMembers();
    }
    function fetchMembers() {
        document.getElementById('members-loading').style.display = '';
        fetch('/api/teams/' + selectedTeamId + '/members')
            .then(r => r.json())
            .then(data => {
                document.getElementById('members-loading').style.display = 'none';
                const tbody = document.getElementById('members');
                tbody.innerHTML = '';
                if (data.success && Array.isArray(data.data) && data.data.length) {
                    data.data.forEach(member => {
                        const tr = document.createElement('tr');
                        const name = (member.first_name || member.last_name) ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : member.user_id;
                        tr.innerHTML = `<td>${name}</td><td>${member.email || ''}</td><td>${member.role}</td><td><button onclick="selectMember(${member.user_id})">Privileges</button> <button onclick="removeMember(${member.user_id})">Remove</button></td>`;
                        tbody.appendChild(tr);
                    });
                    document.getElementById('members-empty').style.display = 'none';
                } else {
                    document.getElementById('members-empty').style.display = '';
                }
            })
            .catch(() => {
                document.getElementById('members-loading').style.display = 'none';
                showMsg('Failed to load members.', 'error');
            });
    }
    function selectMember(userId) {
        selectedUserId = userId;
        document.querySelector('.privilege-list').style.display = '';
        document.getElementById('selected-user-id').textContent = userId;
        document.getElementById('selected-team-id').textContent = selectedTeamId;
        fetchPrivileges();
    }
    function fetchPrivileges() {
        document.getElementById('privileges-loading').style.display = '';
        fetch('/api/teams/' + selectedTeamId + '/privileges/' + selectedUserId)
            .then(r => r.json())
            .then(data => {
                document.getElementById('privileges-loading').style.display = 'none';
                const tbody = document.getElementById('privileges');
                tbody.innerHTML = '';
                let privMap = {};
                if (data.success && Array.isArray(data.data)) {
                    data.data.forEach(priv => {
                        privMap[priv.privilege] = priv.allowed;
                    });
                }
                // Show common privileges as toggles
                commonPrivileges.forEach(priv => {
                    const tr = document.createElement('tr');
                    const allowed = privMap[priv] ? true : false;
                    tr.innerHTML = `<td>${priv}</td><td><input type="checkbox" class="toggle-switch" ${allowed ? 'checked' : ''} onchange="setPrivilege('${priv}', this.checked ? 1 : 0)"></td>`;
                    tbody.appendChild(tr);
                });
                // Show custom privileges
                for (const priv in privMap) {
                    if (!commonPrivileges.includes(priv)) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${priv}</td><td>${privMap[priv] ? 'Allowed' : 'Denied'}</td>`;
                        tbody.appendChild(tr);
                    }
                }
            })
            .catch(() => {
                document.getElementById('privileges-loading').style.display = 'none';
                showMsg('Failed to load privileges.', 'error');
            });
    }
    function setPrivilege(privilege, allowed) {
        fetch('/api/teams/set-privilege', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: selectedUserId, privilege, allowed })
        }).then(() => fetchPrivileges());
    }
    function removeMember(userId) {
        fetch('/api/teams/remove-member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: userId })
        }).then(() => { showMsg('Member removed.', 'success'); fetchMembers(); });
    }
    document.getElementById('create-team-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/teams/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: form.name.value, description: form.description.value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Team created!', 'success');
                form.reset();
                fetchTeams();
            } else {
                showMsg(data.message || 'Failed to create team.', 'error');
            }
        })
        .catch(() => showMsg('Failed to create team.', 'error'));
    };
    // Autocomplete logic for user search
    const userSearch = document.getElementById('user-search');
    const userIdHidden = document.getElementById('user-id-hidden');
    const userSuggestions = document.getElementById('user-suggestions');
    let userSearchTimeout = null;
    userSearch.addEventListener('input', function() {
        const q = userSearch.value.trim();
        userIdHidden.value = '';
        if (q.length < 2) {
            userSuggestions.style.display = 'none';
            return;
        }
        clearTimeout(userSearchTimeout);
        userSearchTimeout = setTimeout(() => {
            fetch('/api/teams/search-users?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    userSuggestions.innerHTML = '';
                    if (data.success && Array.isArray(data.data) && data.data.length) {
                        data.data.forEach(user => {
                            const div = document.createElement('div');
                            div.style.padding = '4px 8px';
                            div.style.cursor = 'pointer';
                            div.textContent = `${user.first_name || ''} ${user.last_name || ''} (${user.email})`.trim();
                            div.onclick = () => {
                                userSearch.value = `${user.first_name || ''} ${user.last_name || ''}`.trim() + ' (' + user.email + ')';
                                userIdHidden.value = user.id;
                                userSuggestions.style.display = 'none';
                            };
                            userSuggestions.appendChild(div);
                        });
                        userSuggestions.style.display = '';
                    } else {
                        userSuggestions.style.display = 'none';
                    }
                });
        }, 200);
    });
    document.addEventListener('click', function(e) {
        if (!userSuggestions.contains(e.target) && e.target !== userSearch) {
            userSuggestions.style.display = 'none';
        }
    });
    // Update add-member-form submit to use selected user_id
    document.getElementById('add-member-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        if (!userIdHidden.value) {
            showMsg('Please select a user from the suggestions.', 'error');
            return;
        }
        fetch('/api/teams/add-member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: userIdHidden.value, role: form.role.value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Member added!', 'success');
                form.reset();
                userIdHidden.value = '';
                fetchMembers();
            } else {
                showMsg(data.message || 'Failed to add member.', 'error');
            }
        })
        .catch(() => showMsg('Failed to add member.', 'error'));
    };
    document.getElementById('set-privilege-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/teams/set-privilege', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: selectedUserId, privilege: form.privilege.value, allowed: form.allowed.value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg('Privilege set!', 'success');
                form.reset();
                fetchPrivileges();
            } else {
                showMsg(data.message || 'Failed to set privilege.', 'error');
            }
        })
        .catch(() => showMsg('Failed to set privilege.', 'error'));
    };
    // Initial load
    fetchTeams();
    </script>
</body>
</html> 