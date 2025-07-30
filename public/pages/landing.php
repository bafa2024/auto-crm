<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Supercharge Your Sales with Intelligent Auto Dialing</h1>
                <p class="lead mb-4">Increase your team's productivity by 300% with our AI-powered auto dialer. Connect with more prospects, close more deals, and grow your business faster.</p>
                <div class="d-flex gap-3">
                    <a class="btn btn-light btn-lg" href="<?php echo base_path('autocrm/login'); ?>">
                        <i class="bi bi-shield-check me-2"></i>Admin Login
                    </a>
                    <a class="btn btn-primary btn-lg" href="<?php echo base_path('autocrm/signup'); ?>">
                        <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
                    </a>
                </div>
                <div class="mt-4">
                    <small class="opacity-75">No credit card required • 14-day free trial • Cancel anytime</small>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="https://images.unsplash.com/photo-1560438718-eb61ede255eb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" class="img-fluid rounded shadow" alt="AutoDial Pro - Professional Call Center Solution">
            </div>
        </div>
    </div>
</div>
<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <header class="text-center mb-5">
            <h2 class="display-5 fw-bold">Powerful Features for Modern Sales Teams</h2>
            <p class="lead text-secondary">Everything you need to scale your outbound sales operations</p>
        </header>
        <div class="row g-4">
            <article class="col-md-4 feature-card text-center">
                <span class="feature-icon d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-robot"></i>
                </span>
                <h4>AI-Powered Detection</h4>
                <p class="text-secondary">Advanced answering machine detection with 98% accuracy using machine learning algorithms.</p>
            </article>
            <article class="col-md-4 feature-card text-center">
                <span class="feature-icon d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-lightning-charge"></i>
                </span>
                <h4>Multiple Dialing Modes</h4>
                <p class="text-secondary">Predictive, Progressive, Preview, and Power dialing modes to match your campaign needs.</p>
            </article>
            <article class="col-md-4 feature-card text-center">
                <span class="feature-icon d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-diagram-3"></i>
                </span>
                <h4>CRM Integration</h4>
                <p class="text-secondary">Seamless integration with Salesforce, HubSpot, Pipedrive, and 50+ other CRM platforms.</p>
            </article>
        </div>
    </div>
</section> 