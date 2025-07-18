<?php
// Ensure user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"])) {
    header("Location: /login");
    exit;
}
?>
<?php include __DIR__ . "/components/header.php"; ?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    overflow-y: auto;
    z-index: 100;
    background: white;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.sidebar-link {
    color: #333;
    text-decoration: none;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    border-radius: 8px;
    transition: all 0.3s;
    margin-bottom: 5px;
}
.sidebar-link:hover {
    background-color: #f8f9fa;
    color: #333;
}
.sidebar-link.active {
    background-color: #e3f2fd;
    color: #1976d2;
}
.sidebar-link i {
    margin-right: 10px;
    width: 20px;
}
.stat-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<?php include __DIR__ . "/components/sidebar.php"; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
            <button class="btn btn-light d-md-none mobile-toggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h5 class="mb-0">Welcome back, <?php echo $_SESSION["user_email"] ?? "User"; ?></h5>
                <small class="text-secondary"><?php echo date('l, F j, Y'); ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light position-relative">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        3
                    </span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i><?php echo $_SESSION["user_email"] ?? "User"; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/dashboard/profile">Profile</a></li>
                        <li><a class="dropdown-item" href="/dashboard/settings">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php include __DIR__ . "/components/dashboard-overview.php"; ?>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

function showSection(section) {
    alert("Section: " + section + " - Coming soon!");
}
</script>

<?php include __DIR__ . "/components/footer.php"; ?> 