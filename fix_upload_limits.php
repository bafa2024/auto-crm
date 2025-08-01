<?php
echo "<h2>PHP Upload Limits Configuration</h2>";

// Current settings
echo "<h3>Current PHP Settings</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting</th><th>Current Value</th><th>Recommended Value</th></tr>";

$settings = [
    'upload_max_filesize' => '10M',
    'post_max_size' => '12M',
    'max_execution_time' => '300',
    'max_input_time' => '300',
    'memory_limit' => '256M'
];

foreach ($settings as $key => $recommended) {
    $current = ini_get($key);
    echo "<tr>";
    echo "<td>$key</td>";
    echo "<td>$current</td>";
    echo "<td>$recommended</td>";
    echo "</tr>";
}
echo "</table>";

// php.ini location
echo "<h3>PHP Configuration File Location</h3>";
$iniPath = php_ini_loaded_file();
if ($iniPath) {
    echo "<p>php.ini location: <strong>$iniPath</strong></p>";
} else {
    echo "<p>Could not determine php.ini location</p>";
}

// Instructions
echo "<h3>How to Fix Upload Limits</h3>";
echo "<h4>Method 1: Edit php.ini (Recommended)</h4>";
echo "<ol>";
echo "<li>Open your php.ini file at: <code>$iniPath</code></li>";
echo "<li>Find and update these values:";
echo "<pre style='background: #f4f4f4; padding: 10px;'>";
echo "upload_max_filesize = 10M\n";
echo "post_max_size = 12M\n";
echo "max_execution_time = 300\n";
echo "max_input_time = 300\n";
echo "memory_limit = 256M";
echo "</pre>";
echo "</li>";
echo "<li>Save the file</li>";
echo "<li>Restart Apache in XAMPP Control Panel</li>";
echo "</ol>";

echo "<h4>Method 2: Create .user.ini (Alternative)</h4>";
echo "<p>Create a file named <code>.user.ini</code> in your project root with:</p>";
echo "<pre style='background: #f4f4f4; padding: 10px;'>";
echo "upload_max_filesize = 10M\n";
echo "post_max_size = 12M\n";
echo "max_execution_time = 300\n";
echo "max_input_time = 300\n";
echo "memory_limit = 256M";
echo "</pre>";

// Try to create .user.ini
if (isset($_POST['create_user_ini'])) {
    $content = "upload_max_filesize = 10M\n";
    $content .= "post_max_size = 12M\n";
    $content .= "max_execution_time = 300\n";
    $content .= "max_input_time = 300\n";
    $content .= "memory_limit = 256M\n";
    
    if (file_put_contents(__DIR__ . '/.user.ini', $content)) {
        echo "<p style='color: green;'>✓ .user.ini file created successfully!</p>";
        echo "<p>Note: Changes may take a few minutes to take effect.</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create .user.ini file</p>";
    }
}

?>
<form method="POST">
    <button type="submit" name="create_user_ini" value="1" style="padding: 10px 20px;">
        Create .user.ini File
    </button>
</form>

<h4>Method 3: Runtime Override (Limited)</h4>
<p>Some settings can be changed at runtime:</p>
<pre style='background: #f4f4f4; padding: 10px;'>
&lt;?php
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
// Note: upload_max_filesize and post_max_size cannot be changed at runtime
?&gt;
</pre>

<h3>Test Upload Limits</h3>
<?php
// Calculate maximum upload size
$maxUpload = return_bytes(ini_get('upload_max_filesize'));
$maxPost = return_bytes(ini_get('post_max_size'));
$maxMemory = return_bytes(ini_get('memory_limit'));
$maxSize = min($maxUpload, $maxPost);

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

echo "<p>Maximum file upload size: <strong>" . format_bytes($maxSize) . "</strong></p>";

if ($maxSize < 10485760) { // Less than 10MB
    echo "<p style='color: orange;'>⚠ Upload limit is less than 10MB. Large files may fail to upload.</p>";
} else {
    echo "<p style='color: green;'>✓ Upload limit is sufficient for most files.</p>";
}
?>

<hr>
<a href="test_upload.php">Back to Upload Test</a> | 
<a href="contacts.php">Back to Contacts</a>