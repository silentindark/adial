<?php
/**
 * Login Diagnostic Tool
 * Run this to check if admin user exists and password is correct
 */

// Database configuration - EDIT THESE
$db_host = 'localhost';
$db_user = 'root';  // Your database username
$db_pass = '';      // Your database password
$db_name = 'adialer';

echo "=== ARI Dialer Login Diagnostic ===\n\n";

// Connect to database
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die("❌ Database connection failed: " . $conn->connect_error . "\n");
    }

    echo "✓ Database connection successful\n\n";

    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        die("❌ Users table does not exist! Did you import database_schema.sql?\n");
    }
    echo "✓ Users table exists\n\n";

    // Check if admin user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $username = 'admin';
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("❌ Admin user does not exist in database!\n   Run this SQL to create it:\n   INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `role`, `is_active`, `created_at`) VALUES\n   ('admin', '\$2y\$10\$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.', 'admin@localhost', 'Administrator', 'admin', 1, NOW());\n");
    }

    $user = $result->fetch_assoc();
    echo "✓ Admin user exists\n";
    echo "  - ID: " . $user['id'] . "\n";
    echo "  - Username: " . $user['username'] . "\n";
    echo "  - Email: " . $user['email'] . "\n";
    echo "  - Full Name: " . $user['full_name'] . "\n";
    echo "  - Role: " . $user['role'] . "\n";
    echo "  - Is Active: " . $user['is_active'] . "\n";
    echo "  - Password Hash: " . substr($user['password'], 0, 30) . "...\n\n";

    // Check if password is correct
    $password = 'admin';
    if (password_verify($password, $user['password'])) {
        echo "✓ Password 'admin' verifies correctly!\n";
        echo "  PHP Version: " . phpversion() . "\n";
        echo "  Password Algo: " . password_get_info($user['password'])['algoName'] . "\n\n";

        if ($user['is_active'] == 0) {
            echo "❌ WARNING: User account is INACTIVE!\n";
            echo "   Run this SQL to activate:\n";
            echo "   UPDATE users SET is_active = 1 WHERE username = 'admin';\n\n";
        } else {
            echo "✅ Everything looks good! Login should work with:\n";
            echo "   Username: admin\n";
            echo "   Password: admin\n\n";
            echo "If login still fails, check:\n";
            echo "1. PHP session configuration\n";
            echo "2. CodeIgniter database config in application/config/database.php\n";
            echo "3. Check browser console for errors\n";
            echo "4. Check Apache/PHP error logs\n";
        }
    } else {
        echo "❌ Password 'admin' does NOT verify!\n";
        echo "   Current hash: " . $user['password'] . "\n";
        echo "   Expected hash: \$2y\$10\$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.\n\n";
        echo "Run this SQL to fix the password:\n";
        echo "UPDATE users SET password = '\$2y\$10\$nG4K5S6hSflCLUCsgn62ze7rohekGbOgEMgvFpqhPHPHMzzoFdCA.' WHERE username = 'admin';\n";
    }

    $conn->close();

} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}
