<?php
require_once __DIR__ . '/../config/base_path.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .error-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .error-icon {
            font-size: 8rem;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="card error-card">
                    <div class="card-body p-5">
                        <div class="error-icon mb-4">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h1 class="display-1 fw-bold text-danger mb-3">404</h1>
                        <h2 class="mb-3">Page Not Found</h2>
                        <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
                        <a href="<?php echo base_path(''); ?>" class="btn btn-primary">Go to Homepage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>