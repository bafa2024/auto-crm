<?php
// database_tools.php - Database management tools for AutoCRM
// Access this file in your browser to manage your local MySQL database

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCRM Database Tools</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            text-align: center;
        }
        .tool-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .tool-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .tool-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .tool-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .button {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: background 0.2s;
        }
        .button:hover {
            background: #0056b3;
        }
        .button.success {
            background: #28a745;
        }
        .button.success:hover {
            background: #218838;
        }
        .button.danger {
            background: #dc3545;
        }
        .button.danger:hover {
            background: #c82333;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: bold;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .info-box ul {
            margin-bottom: 0;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AutoCRM Database Tools</h1>
        
        <div class="info-box">
            <h3>Quick Setup Guide</h3>
            <ol>
                <li><strong>Start MySQL Service:</strong> Open XAMPP Control Panel and start MySQL</li>
                <li><strong>Setup Database:</strong> Click "Setup MySQL Database" below</li>
                <li><strong>Test Connection:</strong> Click "Test Database Connection" to verify</li>
                <li><strong>Access Application:</strong> Your AutoCRM is ready to use!</li>
            </ol>
        </div>';

// Quick status check
try {
    require_once 'config/database.php';
    $database = new Database();
    $environment = $database->getEnvironment();
    $dbType = $database->getDatabaseType();
    
    if ($database->testConnection()) {
        echo '<div class="status success">✓ Database connection successful</div>';
        echo '<div class="status success">Environment: ' . $environment . ' | Database Type: ' . $dbType . '</div>';
    } else {
        echo '<div class="status error">❌ Database connection failed</div>';
        echo '<div class="status warning">Please run the setup tool to configure your database</div>';
    }
} catch (Exception $e) {
    echo '<div class="status error">❌ Configuration error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '
        <div class="tool-card">
            <div class="tool-title">🔧 Setup MySQL Database</div>
            <div class="tool-description">
                Creates the local MySQL database and imports the complete schema with sample data. 
                This tool will set up all necessary tables and populate them with test data for development.
            </div>
            <a href="setup_local_mysql_web.php" class="button">Setup Database</a>
        </div>
        
        <div class="tool-card">
            <div class="tool-title">🧪 Test Database Connection</div>
            <div class="tool-description">
                Tests the current database connection and shows detailed information about your database, 
                including table counts and configuration details.
            </div>
            <a href="test_local_mysql_web.php" class="button success">Test Connection</a>
        </div>
        
        <div class="info-box">
            <h3>Database Configuration</h3>
            <ul>
                <li><strong>Host:</strong> localhost</li>
                <li><strong>Username:</strong> root</li>
                <li><strong>Password:</strong> (empty)</li>
                <li><strong>Database:</strong> autocrm</li>
                <li><strong>Port:</strong> 3306</li>
            </ul>
        </div>
        
        <div class="info-box">
            <h3>Default Login Credentials</h3>
            <ul>
                <li><strong>Email:</strong> admin@autocrm.com</li>
                <li><strong>Password:</strong> (check the database setup for the hashed password)</li>
            </ul>
        </div>
        
        <div class="info-box">
            <h3>Troubleshooting</h3>
            <ul>
                <li>Make sure MySQL service is running in XAMPP</li>
                <li>Verify port 3306 is not blocked</li>
                <li>Check that root user can connect without password</li>
                <li>Ensure you have permission to create databases</li>
            </ul>
        </div>
        
        <p style="text-align: center; margin-top: 30px; color: #666;">
            <strong>Need help?</strong> Check the <a href="LOCAL_MYSQL_SETUP.md" target="_blank">setup documentation</a> for detailed instructions.
        </p>
    </div>
</body>
</html>';
?> 