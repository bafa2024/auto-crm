<?php
echo "<h2>Composer Installation Helper</h2>";

// Check if composer is installed
echo "<h3>1. Checking Composer Installation</h3>";
$composerCheck = shell_exec('composer --version 2>&1');
if (strpos($composerCheck, 'Composer version') !== false) {
    echo "✓ Composer is installed: " . $composerCheck . "<br>";
    $composerInstalled = true;
} else {
    echo "✗ Composer is not installed or not in PATH<br>";
    echo "Please install Composer from: <a href='https://getcomposer.org/download/' target='_blank'>https://getcomposer.org/download/</a><br>";
    $composerInstalled = false;
}

// Check if vendor directory exists
echo "<h3>2. Checking Vendor Directory</h3>";
$vendorPath = __DIR__ . '/vendor';
if (is_dir($vendorPath)) {
    echo "✓ Vendor directory exists<br>";
    
    // Check if autoload.php exists
    if (file_exists($vendorPath . '/autoload.php')) {
        echo "✓ autoload.php exists<br>";
        
        // Check if PhpSpreadsheet is installed
        if (is_dir($vendorPath . '/phpoffice/phpspreadsheet')) {
            echo "✓ PhpSpreadsheet is installed<br>";
        } else {
            echo "✗ PhpSpreadsheet is not installed<br>";
        }
    } else {
        echo "✗ autoload.php not found<br>";
    }
} else {
    echo "✗ Vendor directory does not exist<br>";
}

// Installation instructions
echo "<h3>3. Installation Instructions</h3>";

if ($composerInstalled) {
    echo "<p>Run the following command in your terminal/command prompt from the project directory:</p>";
    echo "<pre style='background: #f4f4f4; padding: 10px;'>";
    echo "cd " . __DIR__ . "\n";
    echo "composer install";
    echo "</pre>";
    
    echo "<h4>Alternative: Run Composer Here</h4>";
    if (isset($_POST['run_composer'])) {
        echo "<p>Running composer install...</p>";
        echo "<pre style='background: #f4f4f4; padding: 10px; max-height: 400px; overflow-y: auto;'>";
        
        // Change to project directory
        chdir(__DIR__);
        
        // Run composer install
        $output = [];
        $return_var = 0;
        exec('composer install 2>&1', $output, $return_var);
        
        foreach ($output as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        echo "</pre>";
        
        if ($return_var === 0) {
            echo "<p style='color: green;'>✓ Composer install completed successfully!</p>";
        } else {
            echo "<p style='color: red;'>✗ Composer install failed. Return code: $return_var</p>";
        }
    } else {
        ?>
        <form method="POST">
            <button type="submit" name="run_composer" value="1" class="btn btn-primary" style="padding: 10px 20px;">
                Run Composer Install
            </button>
        </form>
        <p><small>Note: This may take a few minutes to complete.</small></p>
        <?php
    }
} else {
    echo "<p>Please install Composer first:</p>";
    echo "<ol>";
    echo "<li>Download Composer from <a href='https://getcomposer.org/download/' target='_blank'>https://getcomposer.org/download/</a></li>";
    echo "<li>Install it system-wide or in your project directory</li>";
    echo "<li>Refresh this page and try again</li>";
    echo "</ol>";
}

// Manual installation option
echo "<h3>4. Manual Installation (Without Composer)</h3>";
echo "<p>If you can't use Composer, you can manually download PhpSpreadsheet:</p>";
echo "<ol>";
echo "<li>Download from: <a href='https://github.com/PHPOffice/PhpSpreadsheet/releases' target='_blank'>https://github.com/PHPOffice/PhpSpreadsheet/releases</a></li>";
echo "<li>Extract to: " . __DIR__ . "/vendor/phpoffice/phpspreadsheet/</li>";
echo "<li>Also download dependencies and place them in the vendor directory</li>";
echo "</ol>";

echo "<hr>";
echo "<a href='test_upload.php'>Back to Upload Test</a> | ";
echo "<a href='contacts.php'>Back to Contacts</a>";
?>