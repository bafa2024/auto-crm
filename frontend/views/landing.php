<?php 
require_once __DIR__ . "/../config/base_path.php";
include __DIR__ . "/components/header-landing.php"; 
include __DIR__ . "/components/navigation.php"; 
?>

<!-- Hero Section -->
<section class="hero-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Supercharge Your Sales with Intelligent Auto Dialing</h1>
                <p class="lead mb-4">Increase your team's productivity by 300% with our AI-powered auto dialer. Connect with more prospects, close more deals, and grow your business faster.</p>
                <div class="d-flex gap-3">
                    <a class="btn btn-light btn-lg" href="<?php echo base_path('login'); ?>">
                        <i class="bi bi-shield-check me-2"></i>Admin Login
                    </a>
                    <a class="btn btn-outline-light btn-lg" href="<?php echo base_path('employee/login'); ?>">
                        <i class="bi bi-person-badge me-2"></i>Employee Login
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="https://images.unsplash.com/photo-1560438718-eb61ede255eb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" class="img-fluid rounded shadow" alt="AutoDial Pro - Professional Call Center Solution">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Powerful Features for Modern Sales Teams</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-robot display-4 text-primary"></i>
                    </div>
                    <h4>AI-Powered Detection</h4>
                    <p class="text-muted">Advanced answering machine detection with 98% accuracy using machine learning algorithms.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-lightning-charge display-4 text-primary"></i>
                    </div>
                    <h4>Multiple Dialing Modes</h4>
                    <p class="text-muted">Predictive, Progressive, Preview, and Power dialing modes to match your campaign needs.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-diagram-3 display-4 text-primary"></i>
                    </div>
                    <h4>CRM Integration</h4>
                    <p class="text-muted">Seamless integration with Salesforce, HubSpot, Pipedrive, and 50+ other CRM platforms.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Access Types Section -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Choose Your Access Type</h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-primary">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-shield-check display-4 text-primary"></i>
                        </div>
                        <h4>Administrator Access</h4>
                        <p class="text-muted mb-4">Full system access for managers and administrators. Create campaigns, manage employees, view all contacts, and access comprehensive reporting.</p>
                        <a class="btn btn-primary" href="<?php echo base_path('login'); ?>">
                            <i class="bi bi-shield-check me-2"></i>Admin Login
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-secondary">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="bi bi-person-badge display-4 text-secondary"></i>
                        </div>
                        <h4>Employee Access</h4>
                        <p class="text-muted mb-4">Dedicated portal for sales agents and team members. View assigned contacts, update status, manage your profile, and track your performance.</p>
                        <a class="btn btn-outline-secondary" href="<?php echo base_path('employee/login'); ?>">
                            <i class="bi bi-person-badge me-2"></i>Employee Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . "/components/footer.php"; ?>