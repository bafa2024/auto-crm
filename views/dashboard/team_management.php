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
        .panel { border: 1px solid #ccc; border-radius: 6px; padding: 1em; margin-bottom: 2em; }
        .panel-title { font-weight: bold; margin-bottom: 0.5em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Team Management</h2>
        <div class="panel team-list">
            <div class="panel-title">Teams</div>
            <ul id="teams"></ul>
            <form id="create-team-form">
                <input type="text" name="name" placeholder="Team Name" required>
                <input type="text" name="description" placeholder="Description">
                <button type="submit">Create Team</button>
            </form>
        </div>
        <div class="panel member-list" style="display:none;">
            <div class="panel-title">Team Members (<span id="selected-team-name"></span>)</div>
            <ul id="members"></ul>
            <form id="add-member-form">
                <input type="number" name="user_id" placeholder="User ID" required>
                <select name="role">
                    <option value="worker">Worker</option>
                    <option value="owner">Owner</option>
                </select>
                <button type="submit">Add Member</button>
            </form>
        </div>
        <div class="panel privilege-list" style="display:none;">
            <div class="panel-title">Privileges for User <span id="selected-user-id"></span> in Team <span id="selected-team-id"></span></div>
            <ul id="privileges"></ul>
            <form id="set-privilege-form">
                <input type="text" name="privilege" placeholder="Privilege (e.g. manage_campaigns)" required>
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

    function fetchTeams() {
        fetch('/api/teams/list')
            .then(r => r.json())
            .then(data => {
                const ul = document.getElementById('teams');
                ul.innerHTML = '';
                if (data.success && Array.isArray(data.data)) {
                    data.data.forEach(team => {
                        const li = document.createElement('li');
                        li.textContent = team.name + ' (ID: ' + team.id + ')';
                        li.style.cursor = 'pointer';
                        li.onclick = () => selectTeam(team.id, team.name);
                        ul.appendChild(li);
                    });
                }
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
        fetch('/api/teams/' + selectedTeamId + '/members')
            .then(r => r.json())
            .then(data => {
                const ul = document.getElementById('members');
                ul.innerHTML = '';
                if (data.success && Array.isArray(data.data)) {
                    data.data.forEach(member => {
                        const li = document.createElement('li');
                        li.textContent = 'User ID: ' + member.user_id + ' (' + member.role + ')';
                        li.style.cursor = 'pointer';
                        li.onclick = () => selectMember(member.user_id);
                        const btn = document.createElement('button');
                        btn.textContent = 'Remove';
                        btn.onclick = (e) => { e.stopPropagation(); removeMember(member.user_id); };
                        li.appendChild(btn);
                        ul.appendChild(li);
                    });
                }
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
        fetch('/api/teams/' + selectedTeamId + '/privileges/' + selectedUserId)
            .then(r => r.json())
            .then(data => {
                const ul = document.getElementById('privileges');
                ul.innerHTML = '';
                if (data.success && Array.isArray(data.data)) {
                    data.data.forEach(priv => {
                        const li = document.createElement('li');
                        li.textContent = priv.privilege + ': ' + (priv.allowed ? 'Allowed' : 'Denied');
                        ul.appendChild(li);
                    });
                }
            });
    }
    function removeMember(userId) {
        fetch('/api/teams/remove-member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: userId })
        }).then(() => fetchMembers());
    }
    document.getElementById('create-team-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/teams/create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: form.name.value, description: form.description.value })
        }).then(() => { form.reset(); fetchTeams(); });
    };
    document.getElementById('add-member-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/teams/add-member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: form.user_id.value, role: form.role.value })
        }).then(() => { form.reset(); fetchMembers(); });
    };
    document.getElementById('set-privilege-form').onsubmit = function(e) {
        e.preventDefault();
        const form = e.target;
        fetch('/api/teams/set-privilege', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_id: selectedTeamId, user_id: selectedUserId, privilege: form.privilege.value, allowed: form.allowed.value })
        }).then(() => { form.reset(); fetchPrivileges(); });
    };
    // Initial load
    fetchTeams();
    </script>
</body>
</html> 