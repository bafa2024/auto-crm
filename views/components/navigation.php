<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/#features">Features</a>
                </li>
            </ul>
            <div class="ms-3">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <a class="btn btn-outline-primary me-2" href="/dashboard">Dashboard</a>
                    <a class="btn btn-primary" href="/logout">Logout</a>
                <?php else: ?>
                    <a class="btn btn-outline-primary" href="/login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>