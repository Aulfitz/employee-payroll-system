<?php
require_once '../includes/auth.php';
require_once '../includes/db_connection.php';
requireLogin();

$conn = getConnection();

// Stat: total active employees
$r = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE status='Active'");
$totalEmployees = $r->fetch_assoc()['c'];

// Stat: total payroll this month
$r = $conn->query("SELECT COALESCE(SUM(net_salary),0) AS c FROM payroll 
                   WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
$monthlyPayroll = number_format($r->fetch_assoc()['c'], 2);

// Stat: present today
$r = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE date=CURDATE() AND status='Present'");
$presentToday = $r->fetch_assoc()['c'];

// Stat: pending payrolls (Draft)
$r = $conn->query("SELECT COUNT(*) AS c FROM payroll WHERE status='Draft'");
$pendingPayroll = $r->fetch_assoc()['c'];

// Recent employees
$recentEmps = $conn->query("SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS full_name,
    d.department_name, p.position_name, e.date_hired, e.status
    FROM employees e
    LEFT JOIN departments d ON e.department_id=d.department_id
    LEFT JOIN positions p ON e.position_id=p.position_id
    ORDER BY e.employee_id DESC LIMIT 5");

// Recent payroll
$recentPayroll = $conn->query("SELECT CONCAT(e.first_name,' ',e.last_name) AS full_name,
    pr.gross_salary, pr.total_deductions, pr.net_salary, pr.status, pr.created_at
    FROM payroll pr
    JOIN employees e ON pr.employee_id=e.employee_id
    ORDER BY pr.payroll_id DESC LIMIT 5");

$conn->close();

include '../includes/header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: box-shadow 0.2s;
    }

    .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.07); }

    .stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; flex-shrink: 0;
    }

    .stat-icon.navy   { background: #e8edf5; }
    .stat-icon.green  { background: #dcfce7; }
    .stat-icon.amber  { background: #fef3c7; }
    .stat-icon.blue   { background: #dbeafe; }

    .stat-value { font-size: 26px; font-weight: 600; color: var(--text); line-height: 1; }
    .stat-label { font-size: 13px; color: var(--muted); margin-top: 4px; }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
</style>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon navy">👥</div>
        <div>
            <div class="stat-value"><?= $totalEmployees ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div>
            <div class="stat-value">₱<?= $monthlyPayroll ?></div>
            <div class="stat-label">Payroll This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">✅</div>
        <div>
            <div class="stat-value"><?= $presentToday ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">⏳</div>
        <div>
            <div class="stat-value"><?= $pendingPayroll ?></div>
            <div class="stat-label">Pending Payrolls</div>
        </div>
    </div>
</div>

<!-- Tables -->
<div class="two-col">
    <!-- Recent Employees -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Employees</span>
            <a href="employees.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Department</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if ($recentEmps->num_rows > 0): ?>
                    <?php while($e = $recentEmps->fetch_assoc()): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($e['employee_code']) ?></code></td>
                        <td><?= htmlspecialchars($e['full_name']) ?></td>
                        <td><?= htmlspecialchars($e['department_name'] ?? '—') ?></td>
                        <td><span class="badge <?= $e['status']==='Active' ? 'badge-success' : 'badge-muted' ?>"><?= $e['status'] ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:24px">No employees yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payroll -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Payroll</span>
            <a href="payroll.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Employee</th><th>Net Salary</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if ($recentPayroll->num_rows > 0): ?>
                    <?php while($p = $recentPayroll->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td>₱<?= number_format($p['net_salary'], 2) ?></td>
                        <td>
                            <?php
                            $bClass = match($p['status']) {
                                'Paid'      => 'badge-success',
                                'Processed' => 'badge-info',
                                'Draft'     => 'badge-warning',
                                default     => 'badge-muted'
                            };
                            ?>
                            <span class="badge <?= $bClass ?>"><?= $p['status'] ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px">No payroll records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
