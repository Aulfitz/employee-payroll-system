<?php
// =============================================
// DATABASE CONNECTION
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'startwats0120');           // Change if you have a MySQL password
define('DB_NAME', 'payroll_system');
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
// Helper: return JSON response
function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
}
?>
