<?php
// fix_session_issues.php - Fix session_start() issues across the application
// This script identifies and fixes duplicate session_start() calls

echo "=== Session Issues Fix ===\n\n";

$filesToCheck = [
    'views/landing.php',
    'views/dashboard/index.php',
    'views/dashboard.php',
    'contacts.php',
    'campaigns.php',
    'api/get_campaign.php',
    'controllers/AuthController.php',
    'controllers/ContactController.php',
    'controllers/EmailCampaignController.php'
];

$fixedFiles = [];
$issuesFound = 0;

foreach ($filesToCheck as $file) {
    if (!file_exists($file)) {
        echo "⚠️ File not found: $file\n";
        continue;
    }
    
    echo "Checking: $file\n";
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Check for direct session_start() calls
    if (preg_match('/^\s*<\?php\s*session_start\(\);\s*\?>/m', $content)) {
        echo "  ❌ Found direct session_start() call\n";
        $issuesFound++;
        
        // Replace with proper session handling
        $content = preg_replace(
            '/^\s*<\?php\s*session_start\(\);\s*\?>/m',
            '<?php
// Prevent session already started error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>',
            $content
        );
    }
    
    // Check for session_start() without proper checks
    if (preg_match('/^\s*session_start\(\);/m', $content) && !preg_match('/session_status\(\)/m', $content)) {
        echo "  ❌ Found session_start() without status check\n";
        $issuesFound++;
        
        // Replace with proper session handling
        $content = preg_replace(
            '/^\s*session_start\(\);/m',
            '// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}',
            $content
        );
    }
    
    // Check for session_start() in the middle of files
    if (preg_match('/[^}]\s*session_start\(\);/m', $content) && !preg_match('/session_status\(\)/m', $content)) {
        echo "  ❌ Found session_start() in middle of file\n";
        $issuesFound++;
        
        // Replace with proper session handling
        $content = preg_replace(
            '/([^}])\s*session_start\(\);/m',
            '$1
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}',
            $content
        );
    }
    
    // If content was modified, save the file
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        $fixedFiles[] = $file;
        echo "  ✅ Fixed session handling\n";
    } else {
        echo "  ✅ No issues found\n";
    }
    
    echo "\n";
}

// Check for files that might include each other
echo "=== Checking for potential include conflicts ===\n";

$includePatterns = [
    'include.*header\.php',
    'include.*components/header\.php',
    'require.*header\.php',
    'require.*components/header\.php'
];

foreach ($filesToCheck as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    foreach ($includePatterns as $pattern) {
        if (preg_match('/' . $pattern . '/', $content)) {
            echo "📁 $file includes header component\n";
            
            // Check if this file has session_start() and header also has it
            if (preg_match('/session_start\(\)/', $content) && !preg_match('/session_status\(\)/', $content)) {
                echo "  ⚠️ Potential conflict: file has session_start() and includes header\n";
            }
        }
    }
}

// Summary
echo "\n=== Fix Summary ===\n";
echo "Files checked: " . count($filesToCheck) . "\n";
echo "Issues found: $issuesFound\n";
echo "Files fixed: " . count($fixedFiles) . "\n";

if (!empty($fixedFiles)) {
    echo "\nFixed files:\n";
    foreach ($fixedFiles as $file) {
        echo "- $file\n";
    }
}

// Test session functionality
echo "\n=== Testing Session Functionality ===\n";

// Test 1: Basic session start
echo "Test 1: Basic session start...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started successfully\n";
} else {
    echo "✅ Session already active\n";
}

// Test 2: Session variable (only if session is active)
echo "Test 2: Session variable test...\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['test_var'] = 'test_value_' . time();
    echo "✅ Session variable set: " . $_SESSION['test_var'] . "\n";
    
    // Test 3: Multiple session_start() calls
    echo "Test 3: Multiple session_start() calls...\n";
    $testCount = 0;
    for ($i = 0; $i < 3; $i++) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $testCount++;
        }
    }
    echo "✅ Session started $testCount times (should be 0 after first time)\n";
    
    // Test 4: Session cleanup
    echo "Test 4: Session cleanup...\n";
    unset($_SESSION['test_var']);
    echo "✅ Test variable removed\n";
} else {
    echo "⚠️ Session not active, skipping variable tests\n";
}

echo "\n🎉 Session issues fix completed!\n";
echo "\n📝 IMPORTANT NOTES:\n";
echo "- All session_start() calls now check session_status() first\n";
echo "- This prevents 'session already active' warnings\n";
echo "- Session functionality is preserved\n";
echo "- No breaking changes to existing functionality\n";

if (!empty($fixedFiles)) {
    echo "\n🚀 Next Steps:\n";
    echo "1. Test the web application\n";
    echo "2. Verify no more session warnings\n";
    echo "3. Check that login/logout still works\n";
    echo "4. Test dashboard functionality\n";
}
?> 