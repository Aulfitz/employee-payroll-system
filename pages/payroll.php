<?php
require_once '../includes/auth.php';
require_once '../includes/db_connection.php';
requireLogin();

$conn = getConnection();

// ── All pre-built report queries ──────────────────────────────────────────────
$reports = [
    'employee_list' => [
        'title' => 'All Active Employees',
        'sql'   => "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                    e.gender, d.department_name, p.position_name, p.base_salary,
                    e.date_hired, e.status
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id=d.department_id
                    LEFT JOIN positions p ON e.position_id=p.position_id
                    WHERE e.status='Active'
                    ORDER BY e.date_hired DESC",
    ],
    'payroll_summary' => [
        'title' => 'Payroll Summary (GROUP BY Period)',
        'sql'   => "SELECT pp.period_code, pp.payroll_date,
                    COUNT(DISTINCT pr.employee_id) AS employees_paid,
                    SUM(pr.gross_salary) AS total_gross,
                    SUM(pr.total_deductions) AS total_deductions,
                    SUM(pr.net_salary) AS total_net
                    FROM payroll_periods pp
                    JOIN payroll pr ON pp.period_id=pr.period_id
                    GROUP BY pp.period_id
                    ORDER BY pp.payroll_date DESC",
    ],
    'department_headcount' => [
        'title' => 'Headcount by Department (GROUP BY + JOIN)',
        'sql'   => "SELECT d.department_name,
                    COUNT(e.employee_id) AS total_employees,
                    AVG(p.base_salary) AS avg_salary
                    FROM departments d
                    LEFT JOIN employees e ON d.department_id=e.department_id AND e.status='Active'
                    LEFT JOIN positions p ON e.position_id=p.position_id
                    GROUP BY d.department_id
                    ORDER BY total_employees DESC",
    ],
    'salary_ranking' => [
        'title' => 'Salary Ranking (ORDER BY)',
        'sql'   => "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                    p.position_name, d.department_name, p.base_salary
                    FROM employees e
                    JOIN positions p ON e.position_id=p.position_id
                    JOIN departments d ON e.department_id=d.department_id
                    WHERE e.status='Active'
                    ORDER BY p.base_salary DESC",
    ],
    'attendance_today' => [
        'title' => "Today's Attendance (WHERE + JOIN)",
        'sql'   => "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                    a.time_in, a.time_out, a.total_hours, a.late_minutes, a.status
                    FROM attendance a
                    JOIN employees e ON a.employee_id=e.employee_id
                    WHERE a.date = CURDATE()
                    ORDER BY a.status, e.first_name",
    ],
    'late_employees' => [
        'title' => 'Late Employees (WHERE Filter)',
        'sql'   => "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                    a.date, a.late_minutes, a.time_in
                    FROM attendance a
                    JOIN employees e ON a.employee_id=e.employee_id
                    WHERE a.status='Late'
                    ORDER BY a.date DESC, a.late_minutes DESC",
    ],
    'top_earners' => [
        'title' => 'Top Net Earners — Processed Payroll (JOIN + ORDER BY)',
        'sql'   => "SELECT e.employee_code,
                    CONCAT(e.first_name,' ',e.last_name) AS full_name,
                    pos.position_name, pos.base_salary,
                    pr.gross_salary, pr.total_deductions, pr.net_salary,
                    pr.status AS payroll_status
                    FROM employees e
                    JOIN positions pos ON e.position_id=pos.position_id
                    JOIN payroll pr ON e.employee_id=pr.employee_id
                    WHERE pr.status='Processed'
                    ORDER BY pr.net_salary DESC",
    ],
];

// Run selected report
$active = $_GET['report'] ?? 'employee_list';
if (!array_key_exists($active, $reports)) $active = 'employee_list';

$rpt    = $reports[$active];
$result = $conn->query($rpt['sql']);
$cols   = [];
$rows   = [];
if ($result && $result->num_rows > 0) {
    $cols = array_keys($result->fetch_assoc());
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) $rows[] = $row;
}

include '../includes/header.php';
?>

<style>
    .report-nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .report-nav a {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        background: var(--white);
        border: 1.5px solid var(--border);
        color: var(--text);
        transition: all 0.15s;
    }
    .report-nav a:hover { border-color: var(--blue); color: var(--blue); }
    .report-nav a.active { background: var(--navy); color: var(--white); border-color: var(--navy); }

    .sql-block {
        background: #0f1f3d;
        color: #a5d8ff;
        border-radius: 10px;
        padding: 16px 20px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 20px;
        overflow-x: auto;
        white-space: pre;
    }

    .result-count {
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 12px;
    }
</style>

<!-- Report selector -->
<div class="report-nav">
    <?php foreach ($reports as $key => $rpt_item): ?>
    <a href="?report=<?= $key ?>" class="<?= $active===$key ? 'active' : '' ?>">
        <?= htmlspecialchars($rpt_item['title']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- SQL Query Display -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📄 <?= htmlspecialchars($reports[$active]['title']) ?></span>
    </div>

    <p style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--muted)">SQL QUERY:</p>
    <div class="sql-block"><?= htmlspecialchars(trim($reports[$active]['sql'])) ?></div>

    <p class="result-count">Result: <strong><?= count($rows) ?></strong> record(s) found</p>

    <div class="table-wrap">
        <table>
            <?php if (!empty($cols)): ?>
            <thead>
                <tr>
                    <?php foreach ($cols as $col): ?>
                    <th><?= htmlspecialchars(str_replace('_', ' ', strtoupper($col))) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $key => $val): ?>
                    <td>
                        <?php
                        // Format salary columns
                        if (str_contains($key, 'salary') || str_contains($key, 'pay') || str_contains($key, 'gross') || str_contains($key, 'deduction') || str_contains($key, 'net')) {
                            echo $val !== null ? '₱' . number_format((float)$val, 2) : '—';
                        } elseif (str_contains($key, 'date') && $val) {
                            echo date('M d, Y', strtotime($val));
                        } elseif ($val === null || $val === '') {
                            echo '—';
                        } else {
                            echo htmlspecialchars($val);
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php else: ?>
            <tbody>
                <tr><td style="text-align:center;color:var(--muted);padding:32px">
                    No records found for this query.
                </td></tr>
            </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
