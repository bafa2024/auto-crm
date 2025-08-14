<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Contacts</h6>
                        <h3 class="mb-0">1,234</h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Calls Today</h6>
                        <h3 class="mb-0">156</h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Active Campaigns</h6>
                        <h3 class="mb-0">8</h3>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-envelope"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Conversion Rate</h6>
                        <h3 class="mb-0">23.5%</h3>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Contact</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>10:32 AM</td>
                                <td>John Doe</td>
                                <td>Outbound Call</td>
                                <td><span class="badge bg-success">Connected</span></td>
                            </tr>
                            <tr>
                                <td>10:28 AM</td>
                                <td>Jane Smith</td>
                                <td>Email Sent</td>
                                <td><span class="badge bg-primary">Delivered</span></td>
                            </tr>
                            <tr>
                                <td>10:15 AM</td>
                                <td>Mike Johnson</td>
                                <td>Outbound Call</td>
                                <td><span class="badge bg-warning">Voicemail</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo base_path('instant_email.php'); ?>" class="btn btn-primary">
                        <i class="bi bi-envelope-plus me-2"></i>Send Instant Email
                    </a>
                    <a href="<?php echo base_path('campaigns.php'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-megaphone me-2"></i>Create Campaign
                    </a>
                    <a href="<?php echo base_path('contacts.php'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-upload me-2"></i>Import Contacts
                    </a>
                    <a href="<?php echo base_path('contacts/add'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus me-2"></i>Add Contact
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>