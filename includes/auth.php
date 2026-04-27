<?php
// =============================================
// AUTH HELPER
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $depth = substr_count($_SERVER['PHP_SELF'], '/') - 2;
        $path = str_repeat('../', max(0, $depth));
        header('Location: ' . $path . 'index.php');
        exit();
    }
}

function getCurrentUser() {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'User',
        'role' => $_SESSION['user_role'] ?? 'staff',
    ];
}
?>
