<?php
// =============================================
// DATABASE CONNECTION FILE
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change if your MySQL user is different
define('DB_PASS', '');           // Change if you have a password set
define('DB_NAME', 'payroll_system');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:40px;background:#fff0f0;color:#c0392b;border:1px solid #e74c3c;margin:20px;border-radius:8px;'>
        <h2>⚠️ Database Connection Failed</h2>
        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
        <p>Please check your database credentials in <code>includes/db.php</code></p>
        <p>Make sure the <code>payroll_system</code> database exists and MySQL is running.</p>
    </div>");
}

$conn->set_charset("utf8mb4");
?>
