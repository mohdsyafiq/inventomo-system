<?php
/**
 * Fix Uploads Directory Permissions Script
 * Save this as fix_permissions.php in the same directory as user-profile.php
 * Run it once to set up the uploads folder with correct permissions
 */

echo "<h2>Uploads Directory Permission Fixer</h2>";
echo "<p>This script will create and set proper permissions for the uploads directory.</p>";

$upload_dir = 'uploads/';
$current_dir = __DIR__;
$full_path = $current_dir . '/' . $upload_dir;

echo "<p><strong>Current directory:</strong> " . $current_dir . "</p>";
echo "<p><strong>Uploads path:</strong> " . $full_path . "</p>";

// Step 1: Check if directory exists
echo "<h3>Step 1: Checking directory existence</h3>";
if (file_exists($upload_dir)) {
    echo "✅ Directory exists<br>";
} else {
    echo "❌ Directory does not exist. Creating...<br>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
        echo "<p style='color: red;'><strong>Manual solution:</strong> Create 'uploads' folder manually in your file system</p>";
    }
}

// Step 2: Set permissions
echo "<h3>Step 2: Setting permissions</h3>";
if (chmod($upload_dir, 0777)) {
    echo "✅ Permissions set to 777 using chmod()<br>";
} else {
    echo "❌ chmod() failed. Trying alternative methods...<br>";
    
    // Try using exec command
    if (function_exists('exec')) {
        exec("chmod 777 " . escapeshellarg($full_path), $output, $return_code);
        if ($return_code === 0) {
            echo "✅ Permissions set using exec command<br>";
        } else {
            echo "❌ exec command failed<br>";
            echo "<p style='color: red;'><strong>Manual solution needed:</strong></p>";
            echo "<ul>";
            echo "<li>Open Terminal/Command Prompt</li>";
            echo "<li>Navigate to: " . $current_dir . "</li>";
            echo "<li>Run: <code>chmod 777 uploads</code></li>";
            echo "<li>Or use your file manager to set folder permissions to 777</li>";
            echo "</ul>";
        }
    } else {
        echo "❌ exec() function not available<br>";
        echo "<p style='color: red;'><strong>Manual solution needed - see instructions below</strong></p>";
    }
}

// Step 3: Test writability
echo "<h3>Step 3: Testing writability</h3>";
if (is_writable($upload_dir)) {
    echo "✅ Directory is writable<br>";
    
    // Test by creating a test file
    $test_file = $upload_dir . 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'test')) {
        echo "✅ Test file created successfully<br>";
        unlink($test_file); // Clean up
        echo "✅ Test file deleted<br>";
    } else {
        echo "❌ Cannot create test file<br>";
    }
} else {
    echo "❌ Directory is not writable<br>";
}

// Step 4: Create .htaccess for security
echo "<h3>Step 4: Creating security .htaccess file</h3>";
$htaccess_content = "# Prevent direct access to uploaded files
Options -Indexes
<FilesMatch \"\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">
    Order allow,deny
    Deny from all
</FilesMatch>
";

if (file_put_contents($upload_dir . '.htaccess', $htaccess_content)) {
    echo "✅ .htaccess security file created<br>";
} else {
    echo "❌ Could not create .htaccess file<br>";
}

// Final status
echo "<h3>Final Status</h3>";
if (file_exists($upload_dir) && is_writable($upload_dir)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; color: #155724;'>";
    echo "<strong>✅ SUCCESS!</strong> Uploads directory is ready for use.";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<strong>❌ FAILED!</strong> Manual intervention required.";
    echo "</div>";
    
    echo "<h3>Manual Steps for macOS/XAMPP:</h3>";
    echo "<ol>";
    echo "<li>Open Terminal</li>";
    echo "<li>Navigate to your project: <code>cd " . $current_dir . "</code></li>";
    echo "<li>Create directory: <code>mkdir uploads</code></li>";
    echo "<li>Set permissions: <code>chmod 777 uploads</code></li>";
    echo "<li>Verify: <code>ls -la uploads</code></li>";
    echo "</ol>";
    
    echo "<h3>Alternative Method using Finder:</h3>";
    echo "<ol>";
    echo "<li>Open Finder and navigate to: " . $current_dir . "</li>";
    echo "<li>Create a new folder named 'uploads'</li>";
    echo "<li>Right-click the uploads folder → Get Info</li>";
    echo "<li>Unlock the permissions (click the lock icon)</li>";
    echo "<li>Set permissions to 'Read & Write' for everyone</li>";
    echo "</ol>";
}

// Display current permissions
echo "<h3>Current Directory Information</h3>";
if (file_exists($upload_dir)) {
    $perms = substr(sprintf('%o', fileperms($upload_dir)), -4);
    echo "<p><strong>Current permissions:</strong> " . $perms . "</p>";
    echo "<p><strong>Owner:</strong> " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($upload_dir))['name'] : 'Unknown') . "</p>";
    echo "<p><strong>Is readable:</strong> " . (is_readable($upload_dir) ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Is writable:</strong> " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
}

echo "<p><small>After fixing permissions, you can delete this file (fix_permissions.php) for security.</small></p>";
?>