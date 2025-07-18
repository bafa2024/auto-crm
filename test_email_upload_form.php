<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';
$database = (new Database())->getConnection();
require_once 'services/EmailUploadService.php';

$message = '';
$messageType = '';

// Handle file upload
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_file'])) {
    $uploadService = new EmailUploadService($database);
    
    $file = $_FILES['email_file'];
    $campaignId = $_POST['campaign_id'] ?? null;
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload failed: ' . $file['error'];
        $messageType = 'danger';
    } else {
        // Check file extension only
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $message = 'Invalid file type. Please upload CSV or Excel file.';
            $messageType = 'danger';
        } else {
            // Process the file
            $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
            
            if ($result['success']) {
                $message = "Upload successful! Imported: {$result['imported']} contacts";
                if ($result['failed'] > 0) {
                    $message .= ", Failed: {$result['failed']}";
                }
                if (!empty($result['errors'])) {
                    $message .= "<br>Errors:<br>" . implode("<br>", array_slice($result['errors'], 0, 5));
                    if (count($result['errors']) > 5) {
                        $message .= "<br>... and " . (count($result['errors']) - 5) . " more errors";
                    }
                }
                $messageType = 'success';
            } else {
                $message = 'Upload failed: ' . $result['message'];
                $messageType = 'danger';
            }
        }
    }
}

// Get existing campaigns for dropdown
$campaigns = [];
try {
    $stmt = $database->query("SELECT id, name FROM email_campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}

// Get recent uploads
$recentUploads = [];
try {
    $stmt = $database->query("
        SELECT 
            er.campaign_id,
            ec.name as campaign_name,
            COUNT(er.id) as recipient_count,
            MIN(er.created_at) as upload_date
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        GROUP BY er.campaign_id, ec.name
        ORDER BY upload_date DESC
        LIMIT 5
    ");
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Upload Test - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h1 class="mb-4">Email Contact Upload Test</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upload Email Contacts</h5>
                        <p class="text-muted">Upload a CSV or Excel file with columns: DOT, Company Name, Customer Name, Email. Extra columns will be ignored.</p>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="campaign_id" class="form-label">Campaign (Optional)</label>
                                <select class="form-select" id="campaign_id" name="campaign_id">
                                    <option value="">-- No Campaign --</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>">
                                            <?php echo htmlspecialchars($campaign['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select a campaign to associate these contacts with</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_file" class="form-label">Email List File</label>
                                <input type="file" class="form-control" id="email_file" name="email_file" accept=".csv,.xlsx,.xls" required>
                                <div class="form-text">Supported formats: CSV, Excel (.xlsx, .xls)</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload"></i> Upload Contacts
                            </button>
                            
                            <a href="download_template.php" class="btn btn-secondary">
                                <i class="bi bi-download"></i> Download Template
                            </a>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Template Format</h5>
                        <p>Your file should have the following columns (extra columns are ignored):</p>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>DOT</th>
                                    <th>Company Name</th>
                                    <th>Customer Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>170481</td>
                                    <td>BELL TRUCKING CO INC</td>
                                    <td>JUDY BELL</td>
                                    <td>DOODLEBUGBELL@YAHOO.COM</td>
                                </tr>
                                <tr>
                                    <td>226308</td>
                                    <td>ROBERT L COSBY TRUCKING LLC</td>
                                    <td>ROBERT L COSBY</td>
                                    <td>robertlcosby@gmail.com</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($recentUploads)): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Recent Uploads</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Recipients</th>
                                    <th>Upload Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentUploads)): ?>
                                    <?php foreach ($recentUploads as $upload): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($upload['campaign_name'] ?? 'No Campaign'); ?></td>
                                        <td><?php echo $upload['recipient_count']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($upload['upload_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No uploads found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>