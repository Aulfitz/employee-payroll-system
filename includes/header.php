<?php
// Call requireLogin() before including this
$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayrollPro — <?= ucfirst($currentPage) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:    #0f1f3d;
            --blue:    #1a3a6b;
            --accent:  #e8a020;
            --light:   #f5f7fc;
            --white:   #ffffff;
            --text:    #1a1a2e;
            --muted:   #6b7280;
            --border:  #dde3f0;
            --success: #16a34a;
            --danger:  #dc2626;
            --warning: #d97706;
            --sidebar-w: 240px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--light);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--navy);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-brand h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--white);
        }

        .sidebar-brand p {
            font-size: 11px;
            color: rgba(255,255,255,0.45);
            margin-top: 3px;
        }

        .nav-section {
            padding: 16px 12px 8px;
        }

        .nav-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all 0.15s;
            margin-bottom: 2px;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.08);
            color: var(--white);
        }

        .nav-link.active {
            background: rgba(232,160,32,0.15);
            color: var(--accent);
            font-weight: 500;
        }

        .nav-link .icon { font-size: 16px; width: 20px; text-align: center; }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
        }

        .user-avatar {
            width: 32px; height: 32px;
            background: var(--accent);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 600; color: var(--navy);
            flex-shrink: 0;
        }

        .user-details { flex: 1; min-width: 0; }
        .user-name  { font-size: 13px; font-weight: 500; color: var(--white); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role  { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: capitalize; }

        .btn-logout {
            width: 100%;
            margin-top: 8px;
            padding: 9px;
            background: rgba(220,38,38,0.15);
            border: 1px solid rgba(220,38,38,0.3);
            border-radius: 8px;
            color: #fca5a5;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-logout:hover {
            background: rgba(220,38,38,0.3);
            color: #fff;
        }

        /* ── MAIN CONTENT ── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }

        .topbar h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }

        .topbar-date {
            font-size: 13px;
            color: var(--muted);
        }

        .content {
            padding: 28px;
            flex: 1;
        }

        /* ── CARDS ── */
        .card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
        }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
            text-decoration: none;
        }

        .btn-primary { background: var(--navy); color: var(--white); }
        .btn-primary:hover { background: var(--blue); }

        .btn-success { background: #dcfce7; color: var(--success); }
        .btn-success:hover { background: #bbf7d0; }

        .btn-danger { background: #fee2e2; color: var(--danger); }
        .btn-danger:hover { background: #fecaca; }

        .btn-warning { background: #fef3c7; color: var(--warning); }
        .btn-warning:hover { background: #fde68a; }

        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: var(--light);
            padding: 10px 14px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafbff; }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success  { background: #dcfce7; color: var(--success); }
        .badge-danger   { background: #fee2e2; color: var(--danger); }
        .badge-warning  { background: #fef3c7; color: var(--warning); }
        .badge-info     { background: #dbeafe; color: #1d4ed8; }
        .badge-muted    { background: var(--border); color: var(--muted); }

        /* ── FORM ELEMENTS ── */
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }

        .form-group { flex: 1; min-width: 200px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            outline: none;
            transition: border-color 0.15s;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26,58,107,0.08);
        }

        select.form-control { cursor: pointer; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: var(--white);
            border-radius: 16px;
            padding: 28px;
            width: 100%; max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.25s cubic-bezier(0.16,1,0.3,1);
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .modal-title { font-size: 18px; font-weight: 600; }

        .modal-close {
            background: none; border: none;
            font-size: 22px; cursor: pointer;
            color: var(--muted); line-height: 1;
        }

        .modal-close:hover { color: var(--text); }

        /* ── ALERTS ── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success { background: #dcfce7; color: var(--success); border: 1px solid #bbf7d0; }
        .alert-danger  { background: #fee2e2; color: var(--danger);  border: 1px solid #fecaca; }

        /* ── SEARCH ── */
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-input {
            padding: 9px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            width: 240px;
            transition: border-color 0.15s;
        }

        .search-input:focus {
            border-color: var(--blue);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <h1>💼 PayrollPro</h1>
        <p>Information Management System</p>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Main</div>
        <a href="dashboard.php"  class="nav-link <?= $currentPage==='dashboard'  ? 'active' : '' ?>">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="employees.php"  class="nav-link <?= $currentPage==='employees'  ? 'active' : '' ?>">
            <span class="icon">👥</span> Employees
        </a>
        <a href="payroll.php"    class="nav-link <?= $currentPage==='payroll'    ? 'active' : '' ?>">
            <span class="icon">💰</span> Payroll
        </a>
        <a href="attendance.php" class="nav-link <?= $currentPage==='attendance' ? 'active' : '' ?>">
            <span class="icon">📅</span> Attendance
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Reports</div>
        <a href="reports.php" class="nav-link <?= $currentPage==='reports' ? 'active' : '' ?>">
            <span class="icon">📄</span> Reports
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="user-role"><?= htmlspecialchars($user['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <h2><?= ucfirst($currentPage) ?></h2>
        <span class="topbar-date"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="content">
