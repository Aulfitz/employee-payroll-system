<?php
require_once 'includes/auth.php';
require_once 'includes/db_connection.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getConnection();
        $hash = hash('sha256', $password);
        $stmt = $conn->prepare("SELECT user_id, full_name, role FROM users WHERE username = ? AND password = ? AND is_active = 1");
        $stmt->bind_param("ss", $username, $hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $stmt->close();
            $conn->close();
            header('Location: pages/dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Payroll System — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:   #0f1f3d;
            --blue:   #1a3a6b;
            --accent: #e8a020;
            --light:  #f5f7fc;
            --white:  #ffffff;
            --text:   #1a1a2e;
            --muted:  #6b7280;
            --error:  #dc2626;
            --border: #dde3f0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Background pattern */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 70% 20%, rgba(26,58,107,0.8) 0%, transparent 60%),
                radial-gradient(ellipse 50% 80% at 10% 80%, rgba(232,160,32,0.15) 0%, transparent 50%);
        }

        body::after {
            content: '';
            position: absolute;
            top: -200px; right: -200px;
            width: 600px; height: 600px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.05);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate { to { transform: rotate(360deg); } }

        .login-card {
            position: relative;
            z-index: 10;
            background: var(--white);
            border-radius: 20px;
            padding: 48px 44px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.4);
            animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 44px; height: 44px;
            background: var(--navy);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        .brand-text h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--navy);
            line-height: 1;
        }

        .brand-text p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        h2 {
            font-size: 26px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            color: var(--text);
            background: var(--light);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26,58,107,0.1);
            background: var(--white);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--navy);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover  { background: var(--blue); }
        .btn-login:active { transform: scale(0.99); }

        .hint {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }

        .hint code {
            background: var(--light);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand">
        <div class="brand-icon">💼</div>
        <div class="brand-text">
            <h1>Employee Payroll System</h1>
            <p>University of Cabuyao</p>
        </div>
    </div>

    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to access the payroll system</p>

    <?php if ($error): ?>
    <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   placeholder="Enter your username" autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">🔐 Sign In</button>
    </form>

    <div class="hint">
        Default: <code>admin</code> / <code>admin123</code> &nbsp;|&nbsp; <code>hr</code> / <code>hr1234</code>
    </div>
</div>
</body>
</html>
