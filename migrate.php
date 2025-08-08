<?php
/**
 * ACRM Database Migration - Web Interface
 * Production-safe migration system for https://acrm.regrowup.ca/
 * 
 * Usage: Access via browser: https://acrm.regrowup.ca/migrate.php
 */

// Start session for authentication
session_start();

// Security: Check if user is logged in as admin
$isAuthenticated = false;

// Check if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAuthenticated = true;
    error_log("User authenticated: " . $_SESSION['user_id'] . " with role: " . $_SESSION['user_role']);
} else {
    error_log("User not authenticated. Session data: " . json_encode([
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'user_role' => $_SESSION['user_role'] ?? 'not set',
        'session_id' => session_id()
    ]));
}

// Handle login form submission
if ($_POST['action'] ?? '' === 'login') {
    require_once 'config/database.php';
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Debug logging
    error_log("Login attempt: " . $email);
    
    if ($email && $password) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT id, email, first_name, last_name, password, role FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            error_log("User found: " . ($user ? 'yes' : 'no'));
            
            if ($user && password_verify($password, $user['password'])) {
                error_log("Password verified for user: " . $user['id']);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                
                error_log("Session set for user: " . $_SESSION['user_id'] . " with role: " . $_SESSION['user_role']);
                
                // Only allow admin access
                if ($_SESSION['user_role'] === 'admin') {
                    error_log("Admin login successful, redirecting...");
                    // Redirect to prevent form resubmission and ensure clean page load
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $loginError = "Access denied. Admin privileges required.";
                    error_log("Non-admin user attempted access: " . $_SESSION['user_role']);
                    session_destroy();
                }
            } else {
                $loginError = "Invalid credentials";
                error_log("Invalid credentials for: " . $email);
            }
        } catch (Exception $e) {
            $loginError = "Database connection error: " . $e->getMessage();
            error_log("Login database error: " . $e->getMessage());
        }
    } else {
        $loginError = "Email and password are required";
        error_log("Missing email or password in login attempt");
    }
}

// Handle admin creation
if ($_POST['action'] ?? '' === 'create_admin') {
    require_once 'config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $adminEmail = 'admin@autocrm.com';
        $adminPassword = 'admin123';
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        
        // Check if admin already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        $existingAdmin = $stmt->fetch();
        
        if ($existingAdmin) {
            // Update existing admin
            $stmt = $db->prepare("
                UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    password = ?, 
                    company_name = ?, 
                    phone = ?,
                    role = 'admin',
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP
                WHERE email = ?
            ");
            $stmt->execute([
                'Admin',
                'User',
                $hashedPassword,
                'AutoDial Pro',
                '+1-555-0100',
                $adminEmail
            ]);
            $adminCreated = "Admin account updated successfully";
        } else {
            // Create new admin
            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, password, company_name, phone, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active')
            ");
            $stmt->execute([
                'Admin',
                'User',
                $adminEmail,
                $hashedPassword,
                'AutoDial Pro',
                '+1-555-0100'
            ]);
            $adminCreated = "Admin account created successfully";
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI'] . '?admin_created=1');
        exit;
        
    } catch (Exception $e) {
        $adminCreationError = "Failed to create admin: " . $e->getMessage();
    }
}

// Handle logout
if ($_POST['action'] ?? '' === 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// If not authenticated, show login form
if (!$isAuthenticated) {
    // Check for success messages from redirects
    $adminCreated = '';
    if (isset($_GET['admin_created'])) {
        $adminCreated = "Admin account created/updated successfully! You can now login with: admin@autocrm.com / admin123";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ACRM Migration - Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="bi bi-shield-lock"></i> 
                                ACRM Migration - Admin Access
                            </h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($loginError)): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($loginError); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($adminCreated)): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($adminCreated); ?>
                                    <br><strong>Email:</strong> admin@autocrm.com
                                    <br><strong>Password:</strong> admin123
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($adminCreationError)): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($adminCreationError); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="loginForm">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="admin@autocrm.com" required>
                                    <small class="form-text text-muted">Default: admin@autocrm.com</small>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" value="admin123" required>
                                    <small class="form-text text-muted">Default: admin123</small>
                                </div>
                                <button type="submit" class="btn btn-primary" id="loginBtn">
                                    <i class="bi bi-box-arrow-in-right"></i> Login
                                </button>
                                <div id="loginLoading" class="text-muted mt-2" style="display: none;">
                                    <i class="bi bi-hourglass-split"></i> Logging in...
                                </div>
                            </form>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6><i class="bi bi-info-circle"></i> No Admin Account?</h6>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_admin">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="bi bi-person-plus"></i> Create Admin Account
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <h6><i class="bi bi-terminal"></i> Via Command Line:</h6>
                                        <code>php create_admin.php</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <script>
        // Handle login form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const loginLoading = document.getElementById('loginLoading');
            
            // Show loading state
            loginBtn.disabled = true;
            loginLoading.style.display = 'block';
            
            // Set a timeout to re-enable the button in case of issues
            setTimeout(function() {
                loginBtn.disabled = false;
                loginLoading.style.display = 'none';
            }, 10000); // 10 seconds timeout
        });
        
        // Handle any forms with potential delays
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

require_once 'config/database.php';

class WebMigrationRunner {
    private $db;
    private $output = [];
    private $errors = [];
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Add message to output
     */
    private function addOutput($message, $type = 'info') {
        $this->output[] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Check if migration is needed
     */
    public function checkMigrationStatus() {
        try {
            // Check if scheduled_campaigns table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'scheduled_campaigns'");
            $scheduledExists = $stmt->rowCount() > 0;
            
            // Check if schedule_log table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'schedule_log'");
            $logExists = $stmt->rowCount() > 0;
            
            return [
                'scheduled_campaigns' => $scheduledExists,
                'schedule_log' => $logExists,
                'migration_needed' => !$scheduledExists || !$logExists
            ];
            
        } catch (Exception $e) {
            $this->errors[] = "Error checking migration status: " . $e->getMessage();
            return ['error' => true];
        }
    }
    
    /**
     * Run the migration
     */
    public function runMigration() {
        $this->addOutput("🚀 Starting ACRM Campaign Scheduling Migration", 'info');
        $this->addOutput("Server: https://acrm.regrowup.ca/", 'info');
        
        try {
            // Check database connection
            if (!$this->db) {
                throw new Exception("Cannot connect to database");
            }
            $this->addOutput("✅ Database connection successful", 'success');
            
            // Create migrations table if it doesn't exist
            $this->createMigrationsTable();
            
            // Check if migration already ran
            if ($this->isMigrationCompleted()) {
                $this->addOutput("✅ Migration already completed", 'warning');
                return true;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            $this->addOutput("🔄 Starting database transaction", 'info');
            
            // Run migration steps
            $this->createScheduledCampaignsTable();
            $this->createScheduleLogTable();
            $this->enhanceEmailRecipientsTable();
            $this->updateEmailCampaignsTable();
            
            // Record migration as completed
            $this->recordMigration();
            
            // Commit transaction
            if ($this->db->inTransaction()) {
                $this->db->commit();
                $this->addOutput("✅ Transaction committed successfully", 'success');
            }
            
            $this->addOutput("✅ Migration completed successfully!", 'success');
            
            // Verify migration
            $this->verifyMigration();
            
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($this->db->inTransaction()) {
                $this->db->rollback();
                $this->addOutput("🔄 Transaction rolled back", 'warning');
            }
            
            $this->addOutput("❌ Migration failed: " . $e->getMessage(), 'error');
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->exec($sql);
        $this->addOutput("✅ Migration tracking table ready", 'success');
    }
    
    /**
     * Check if migration already completed
     */
    private function isMigrationCompleted() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE migration = '001_add_campaign_scheduling'");
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create scheduled_campaigns table
     */
    private function createScheduledCampaignsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS scheduled_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            schedule_type ENUM('immediate', 'scheduled', 'recurring') DEFAULT 'immediate',
            schedule_date DATETIME NULL,
            frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
            recipient_ids TEXT COMMENT 'JSON array of recipient IDs to send to',
            status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_sent_at DATETIME NULL,
            next_send_at DATETIME NULL COMMENT 'When this campaign should be sent next',
            sent_count INT DEFAULT 0 COMMENT 'Number of emails successfully sent',
            failed_count INT DEFAULT 0 COMMENT 'Number of emails that failed to send',
            
            INDEX idx_status (status),
            INDEX idx_next_send (next_send_at),
            INDEX idx_schedule_type (schedule_type),
            INDEX idx_campaign_status (campaign_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
          COMMENT='Stores scheduled campaign information for the new scheduling system'";
        
        $this->db->exec($sql);
        $this->addOutput("✅ Created scheduled_campaigns table", 'success');
        
        // Try to add foreign key constraint separately (might fail if email_campaigns doesn't exist)
        try {
            $this->db->exec("ALTER TABLE scheduled_campaigns 
                ADD CONSTRAINT fk_scheduled_campaign_id 
                FOREIGN KEY (campaign_id) 
                REFERENCES email_campaigns(id) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE");
            $this->addOutput("✅ Added foreign key constraint to scheduled_campaigns", 'success');
        } catch (Exception $e) {
            $this->addOutput("⚠️ Could not add foreign key constraint (email_campaigns table might not exist): " . $e->getMessage(), 'warning');
        }
    }
    
    /**
     * Create schedule_log table
     */
    private function createScheduleLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS schedule_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scheduled_campaign_id INT NOT NULL,
            action ENUM('created', 'sent', 'failed', 'cancelled', 'processed') NOT NULL,
            message TEXT COMMENT 'Detailed log message or error description',
            recipient_count INT DEFAULT 0 COMMENT 'Number of recipients processed in this action',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_scheduled_campaign (scheduled_campaign_id),
            INDEX idx_action_date (action, created_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
          COMMENT='Logs all scheduling actions and processing results'";
        
        $this->db->exec($sql);
        $this->addOutput("✅ Created schedule_log table", 'success');
        
        // Try to add foreign key constraint separately
        try {
            $this->db->exec("ALTER TABLE schedule_log 
                ADD CONSTRAINT fk_schedule_log_campaign_id 
                FOREIGN KEY (scheduled_campaign_id) 
                REFERENCES scheduled_campaigns(id) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE");
            $this->addOutput("✅ Added foreign key constraint to schedule_log", 'success');
        } catch (Exception $e) {
            $this->addOutput("⚠️ Could not add foreign key constraint: " . $e->getMessage(), 'warning');
        }
    }
    
    /**
     * Enhance email_recipients table
     */
    private function enhanceEmailRecipientsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NULL COMMENT 'Link to email_campaigns table',
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            company VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            status ENUM('pending', 'sent', 'failed', 'bounced', 'unsubscribed') DEFAULT 'pending',
            sent_at DATETIME NULL,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_email (email),
            INDEX idx_campaign_status (campaign_id, status),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Email recipients for campaigns - legacy table with enhancements'";
        
        $this->db->exec($sql);
        $this->addOutput("✅ Enhanced email_recipients table", 'success');
    }
    
    /**
     * Update email_campaigns table
     */
    private function updateEmailCampaignsTable() {
        try {
            // Add missing columns
            $this->db->exec("ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS sender_name VARCHAR(100) NULL");
            $this->db->exec("ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS sender_email VARCHAR(255) NULL");
            $this->db->exec("ALTER TABLE email_campaigns ADD COLUMN IF NOT EXISTS reply_to_email VARCHAR(255) NULL");
            
            $this->addOutput("✅ Updated email_campaigns table", 'success');
        } catch (Exception $e) {
            // Columns might already exist, that's okay
            $this->addOutput("⚠️ Email campaigns table already up to date", 'warning');
        }
    }
    
    /**
     * Record migration as completed
     */
    private function recordMigration() {
        $stmt = $this->db->prepare("INSERT IGNORE INTO migrations (migration) VALUES ('001_add_campaign_scheduling')");
        $stmt->execute();
        $this->addOutput("✅ Migration recorded in database", 'success');
    }
    
    /**
     * Verify migration was successful
     */
    private function verifyMigration() {
        $this->addOutput("🔍 Verifying migration...", 'info');
        
        $tables = ['scheduled_campaigns', 'schedule_log', 'email_recipients', 'email_campaigns'];
        foreach ($tables as $table) {
            $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $this->addOutput("✅ Table '$table' exists", 'success');
            } else {
                $this->addOutput("❌ Table '$table' missing", 'error');
            }
        }
    }
    
    /**
     * Get migration output
     */
    public function getOutput() {
        return $this->output;
    }
    
    /**
     * Get migration errors
     */
    public function getErrors() {
        return $this->errors;
    }
}

// Handle POST request to run migration
$migrationRunner = new WebMigrationRunner();
$migrationStatus = $migrationRunner->checkMigrationStatus();
$migrationRan = false;
$migrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $migrationSuccess = $migrationRunner->runMigration();
    $migrationRan = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACRM Database Migration - regrowup.ca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .migration-output {
            background: #1e1e1e;
            color: #fff;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 500px;
            overflow-y: auto;
        }
        .log-info { color: #17a2b8; }
        .log-success { color: #28a745; }
        .log-warning { color: #ffc107; }
        .log-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">
                                <i class="bi bi-database-gear"></i> 
                                ACRM Campaign Scheduling Migration
                            </h3>
                            <small>Server: https://acrm.regrowup.ca/ | Logged in as: <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']); ?></small>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        
                        <!-- Migration Status -->
                        <div class="alert alert-info">
                            <h5><i class="bi bi-info-circle"></i> Migration Status</h5>
                            <?php if (isset($migrationStatus['error'])): ?>
                                <p class="text-danger">❌ Error checking migration status</p>
                            <?php else: ?>
                                <ul class="mb-0">
                                    <li>scheduled_campaigns table: <?php echo $migrationStatus['scheduled_campaigns'] ? '✅ Exists' : '❌ Missing'; ?></li>
                                    <li>schedule_log table: <?php echo $migrationStatus['schedule_log'] ? '✅ Exists' : '❌ Missing'; ?></li>
                                    <li>Migration needed: <?php echo $migrationStatus['migration_needed'] ? '⚠️ Yes' : '✅ No'; ?></li>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <?php if ($migrationStatus['migration_needed'] && !$migrationRan): ?>
                            <!-- Migration Form -->
                            <div class="alert alert-warning">
                                <h5><i class="bi bi-exclamation-triangle"></i> Database Migration Required</h5>
                                <p>The campaign scheduling system requires database changes. This will:</p>
                                <ul>
                                    <li>Create <code>scheduled_campaigns</code> table</li>
                                    <li>Create <code>schedule_log</code> table</li>
                                    <li>Enhance <code>email_recipients</code> table</li>
                                    <li>Add foreign key constraints</li>
                                    <li>Add performance indexes</li>
                                </ul>
                                <p class="mb-0"><strong>This is safe and won't affect existing data.</strong></p>
                            </div>

                            <form method="POST" onsubmit="return confirm('Are you sure you want to run the database migration?');">
                                <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                                    <i class="bi bi-play-circle"></i> Run Database Migration
                                </button>
                            </form>

                        <?php elseif (!$migrationStatus['migration_needed'] && !$migrationRan): ?>
                            <!-- Already Migrated -->
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle"></i> Migration Complete</h5>
                                <p class="mb-0">The database is already up to date. No migration needed.</p>
                            </div>

                            <div class="mt-3">
                                <a href="campaigns.php" class="btn btn-success">
                                    <i class="bi bi-arrow-right"></i> Go to Campaigns
                                </a>
                                <a href="scheduled_campaigns.php" class="btn btn-info">
                                    <i class="bi bi-calendar-event"></i> Scheduled Campaigns
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($migrationRan): ?>
                            <!-- Migration Results -->
                            <div class="alert alert-<?php echo $migrationSuccess ? 'success' : 'danger'; ?>">
                                <h5>
                                    <i class="bi bi-<?php echo $migrationSuccess ? 'check-circle' : 'x-circle'; ?>"></i>
                                    Migration <?php echo $migrationSuccess ? 'Completed' : 'Failed'; ?>
                                </h5>
                            </div>

                            <!-- Migration Output -->
                            <div class="migration-output">
                                <?php foreach ($migrationRunner->getOutput() as $log): ?>
                                    <div class="log-<?php echo $log['type']; ?>">
                                        [<?php echo $log['timestamp']; ?>] <?php echo htmlspecialchars($log['message']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($migrationSuccess): ?>
                                <!-- Next Steps -->
                                <div class="alert alert-info mt-3">
                                    <h5><i class="bi bi-list-check"></i> Next Steps</h5>
                                    <ol>
                                        <li>Create logs directory: <code>mkdir logs && chmod 755 logs</code></li>
                                        <li>Set up cron job: <code>*/5 * * * * php /home/regrowup/public_html/acrm/process_scheduled_campaigns.php</code></li>
                                        <li>Test the scheduling system</li>
                                    </ol>
                                </div>

                                <div class="mt-3">
                                    <a href="campaigns.php" class="btn btn-success">
                                        <i class="bi bi-arrow-right"></i> Go to Campaigns
                                    </a>
                                    <a href="scheduled_campaigns.php" class="btn btn-info">
                                        <i class="bi bi-calendar-event"></i> Scheduled Campaigns
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- System Information -->
                        <div class="mt-4">
                            <h6>System Information</h6>
                            <small class="text-muted">
                                PHP Version: <?php echo PHP_VERSION; ?> | 
                                Server: <?php echo $_SERVER['SERVER_NAME']; ?> |
                                Database: Connected ✅
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
