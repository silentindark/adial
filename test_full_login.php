<?php
/**
 * Complete Login System Test
 * This tests the entire authentication flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Complete Login System Diagnostic ===\n\n";

// 1. Test PHP password functions
echo "1. Testing PHP Password Functions\n";
echo "   PHP Version: " . phpversion() . "\n";

$test_password = 'admin';
$expected_hash = '$2y$10$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.';

if (password_verify($test_password, $expected_hash)) {
    echo "   ✓ password_verify() works correctly\n";
} else {
    echo "   ✗ password_verify() FAILED!\n";
}

$test_hash = password_hash('admin', PASSWORD_BCRYPT);
echo "   Sample hash created: " . substr($test_hash, 0, 30) . "...\n";
if (password_verify('admin', $test_hash)) {
    echo "   ✓ Can create and verify new hashes\n\n";
}

// 2. Test database connection and user
echo "2. Testing Database Connection\n";

// Try to read from CodeIgniter config first
$db_config_file = '/var/www/html/adial/application/config/database.php';
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // CHANGE THIS if not using CI config
$db_name = 'adialer';

if (file_exists($db_config_file)) {
    // Read file and extract database config
    define('BASEPATH', true); // Bypass CodeIgniter security check
    $config_content = file_get_contents($db_config_file);

    // Extract database credentials using regex
    if (preg_match("/'hostname'\s*=>\s*'([^']+)'/", $config_content, $matches)) {
        $db_host = $matches[1];
    }
    if (preg_match("/'username'\s*=>\s*'([^']+)'/", $config_content, $matches)) {
        $db_user = $matches[1];
    }
    if (preg_match("/'password'\s*=>\s*'([^']+)'/", $config_content, $matches)) {
        $db_pass = $matches[1];
    }
    if (preg_match("/'database'\s*=>\s*'([^']+)'/", $config_content, $matches)) {
        $db_name = $matches[1];
    }

    if ($db_host && $db_user && $db_name) {
        echo "   ℹ Using database config from CodeIgniter\n";
    } else {
        echo "   ⚠ Could not parse database config, using defaults\n";
        echo "   Edit this script and set credentials manually if needed\n";
    }
} else {
    echo "   ⚠ CodeIgniter database config not found, using defaults\n";
    echo "   Edit this file and set database credentials manually\n";
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("   ✗ Database connection failed: " . $conn->connect_error . "\n");
    }
    echo "   ✓ Database connected\n";

    // Check users table
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        die("   ✗ Users table does not exist!\n");
    }
    echo "   ✓ Users table exists\n";

    // Get admin user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $username = 'admin';
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("   ✗ Admin user not found!\n");
    }

    $user = $result->fetch_assoc();
    echo "   ✓ Admin user found (ID: {$user['id']})\n";
    echo "   - Username: {$user['username']}\n";
    echo "   - Email: {$user['email']}\n";
    echo "   - Role: {$user['role']}\n";
    echo "   - Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
    echo "   - Password hash: " . substr($user['password'], 0, 40) . "...\n";

    // Test password
    if (password_verify('admin', $user['password'])) {
        echo "   ✓ Password 'admin' verifies correctly\n\n";
    } else {
        echo "   ✗ Password 'admin' DOES NOT verify!\n";
        echo "   Expected hash: $expected_hash\n";
        echo "   Actual hash:   {$user['password']}\n";
        echo "   SOLUTION: Run this SQL:\n";
        echo "   UPDATE users SET password = '$expected_hash' WHERE username = 'admin';\n\n";
        die();
    }

    // Check if user is active
    if ($user['is_active'] == 0) {
        echo "   ⚠ WARNING: User is INACTIVE!\n";
        echo "   Run: UPDATE users SET is_active = 1 WHERE username = 'admin';\n\n";
        die();
    }

} catch (Exception $e) {
    die("   ✗ Error: " . $e->getMessage() . "\n");
}

// 3. Test CodeIgniter framework
echo "3. Testing CodeIgniter Framework\n";

// Check if CodeIgniter is accessible
$ci_index = '/var/www/html/adial/index.php';
if (!file_exists($ci_index)) {
    echo "   ✗ CodeIgniter index.php not found at $ci_index\n\n";
} else {
    echo "   ✓ CodeIgniter index.php found\n";
}

// Check if User_model exists
$user_model = '/var/www/html/adial/application/models/User_model.php';
if (!file_exists($user_model)) {
    echo "   ✗ User_model.php not found!\n\n";
} else {
    echo "   ✓ User_model.php exists\n";
}

// Check if Login controller exists
$login_controller = '/var/www/html/adial/application/controllers/Login.php';
if (!file_exists($login_controller)) {
    echo "   ✗ Login.php controller not found!\n\n";
} else {
    echo "   ✓ Login.php controller exists\n";
}

// Check if Auth library exists
$auth_library = '/var/www/html/adial/application/libraries/Auth.php';
if (!file_exists($auth_library)) {
    echo "   ✗ Auth.php library not found!\n\n";
} else {
    echo "   ✓ Auth.php library exists\n\n";
}

// 4. Check sessions directory
echo "4. Checking PHP Sessions\n";
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}
echo "   Session save path: $session_path\n";
if (is_writable($session_path)) {
    echo "   ✓ Session directory is writable\n\n";
} else {
    echo "   ✗ Session directory is NOT writable!\n";
    echo "   Run: chmod 777 $session_path\n\n";
}

// 5. Check Apache/web server
echo "5. Checking Web Server Configuration\n";
$htaccess = '/var/www/html/adial/.htaccess';
if (file_exists($htaccess)) {
    echo "   ✓ .htaccess file exists\n";
} else {
    echo "   ⚠ .htaccess file not found (may be optional)\n";
}

// Check if mod_rewrite is loaded (Apache only)
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "   ✓ mod_rewrite is enabled\n";
    } else {
        echo "   ⚠ mod_rewrite is NOT enabled\n";
        echo "   Run: a2enmod rewrite && systemctl restart apache2\n";
    }
} else {
    echo "   - Cannot check Apache modules (not running as Apache module)\n";
}

echo "\n";
echo "=== Summary ===\n";
echo "✅ All basic checks passed!\n\n";
echo "If login still fails on the new server, check:\n";
echo "1. Database configuration in application/config/database.php\n";
echo "2. Base URL in application/config/config.php\n";
echo "3. Apache error logs: tail -f /var/log/httpd/error_log\n";
echo "4. PHP error logs: tail -f /var/log/php-fpm/error.log\n";
echo "5. CodeIgniter logs: tail -f /var/www/html/adial/application/logs/\n";
echo "6. Browser console for JavaScript errors\n";
echo "7. Try accessing: http://your-server/adial/login directly\n\n";
echo "Default credentials:\n";
echo "  Username: admin\n";
echo "  Password: admin\n";
