<?php
require_once '../includes/auth.php';
require_once '../includes/db_connection.php';
requireLogin();

$conn = getConnection();
$msg = ''; $msgType = '';

// Handle: process payroll for an employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $emp_id     = intval($_POST['employee_id']);
    $period_id  = intval($_POST['period_id']);
    $overtime   = floatval($_POST['overtime_pay'] ?? 0);
    $bonus      = floatval($_POST['total_bonus']  ?? 0);
    $deductions = floatval($_POST['total_deductions'] ?? 0);
    $method     = $_POST['payment_method'] ?? 'Bank Transfer';
    $user       = getCurrentUser();

    // Get base salary from position
    $r = $conn->query("SELECT p.base_salary FROM employees e
                       JOIN positions p ON e.position_id=p.position_id
                       WHERE e.employee_id=$emp_id");
    $base = $r->fetch_assoc()['base_salary'] ?? 0;

    // Check for duplicate
    $chk = $conn->query("SELECT payroll_id FROM payroll WHERE employee_id=$emp_id AND period_id=$period_id");
    if ($chk->num_rows > 0) {
        $msg = "Payroll for this employee already exists for this period.";
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO payroll
            (employee_id, period_id, base_salary, regular_hours, overtime_pay,
             total_bonus, total_deductions, payment_method, status, processed_by, processed_date)
            VALUES (?,?,?,160,?,?,?,?,'Processed',?,NOW())");
        $uid = intval($user['id']);
        $stmt->bind_param("iidddssi", $emp_id, $period_id, $base, $overtime, $bonus, $deductions, $method, $uid);
        if ($stmt->execute()) {
            $msg = "Payroll processed successfully!";
            $msgType = 'success';
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

// Handle status update
if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $pid = intval($_GET['pay']);
    $conn->query("UPDATE payroll SET status='Paid', paid_date=NOW() WHERE payroll_id=$pid");
    $msg = "Payroll marked as Paid."; $msgType = 'success';
}

// Search
$search = trim($_GET['search'] ?? '');
$searchSQL = $search
    ? "AND (CONCAT(e.first_name,' ',e.last_name) LIKE '%" . $conn->real_escape_string($search) . "%')"
    : '';

// Fetch payrolls
$payrolls = $conn->query("SELECT pr.*, CONCAT(e.first_name,' ',e.last_name) AS full_name,
    e.employee_code, d.department_name, p.position_name,
    pp.period_code, pp.payroll_date
    FROM payroll pr
    JOIN employees e ON pr.employee_id=e.employee_id
    LEFT JOIN departments d ON e.department_id=d.department_id
    LEFT JOIN positions p ON e.position_id=p.position_id
    LEFT JOIN payroll_periods pp ON pr.period_id=pp.period_id
    WHERE 1=1 $searchSQL
    ORDER BY pr.payroll_id DESC");

// Dropdowns for process modal
$employees_list = $conn->query("SELECT employee_id, CONCAT(employee_code,' — ',first_name,' ',last_name) AS label
    FROM employees WHERE status='Active' ORDER BY first_name");
$periods_list   = $conn->query("SELECT period_id, period_code, payroll_date FROM payroll_periods
    ORDER BY period_id DESC");

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= $msgType==='success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Payroll Records</span>
        <div class="search-box">
            <form method="GET" style="display:flex;gap:8px;align-items:center">
                <input type="text" name="search" class="search-input"
                       placeholder="Search employee…"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                <?php if ($search): ?>
                <a href="payroll.php" class="btn btn-sm" style="background:var(--border);color:var(--text)">✕</a>
                <?php endif; ?>
            </form>
            <button class="btn btn-primary" onclick="openProcessModal()">+ Process Payroll</button>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Period</th>
                    <th>Base Salary</th>
                    <th>OT Pay</th>
                    <th>Bonus</th>
                    <th>Deductions</th>
                    <th>Gross</th>
                    <th>Net Pay</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($payrolls->num_rows > 0): ?>
                <?php while($p = $payrolls->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($p['full_name']) ?></strong><br>
                        <small style="color:var(--muted)"><?= htmlspecialchars($p['employee_code']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($p['department_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['period_code'] ?? '—') ?></td>
                    <td>₱<?= number_format($p['base_salary'], 2) ?></td>
                    <td>₱<?= number_format($p['overtime_pay'], 2) ?></td>
                    <td>₱<?= number_format($p['total_bonus'], 2) ?></td>
                    <td>₱<?= number_format($p['total_deductions'], 2) ?></td>
                    <td>₱<?= number_format($p['gross_salary'], 2) ?></td>
                    <td><strong>₱<?= number_format($p['net_salary'], 2) ?></strong></td>
                    <td>
                        <?php $bClass = match($p['status']) {
                            'Paid'      => 'badge-success',
                            'Processed' => 'badge-info',
                            'Draft'     => 'badge-warning',
                            default     => 'badge-muted'
                        }; ?>
                        <span class="badge <?= $bClass ?>"><?= $p['status'] ?></span>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'Processed'): ?>
                        <a href="?pay=<?= $p['payroll_id'] ?>"
                           class="btn btn-success btn-sm"
                           onclick="return confirm('Mark this payroll as Paid?')">
                            ✅ Mark Paid
                        </a>
                        <?php else: ?>
                        <span style="color:var(--muted);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11" style="text-align:center;color:var(--muted);padding:32px">
                    No payroll records found. Process payroll to get started.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PROCESS PAYROLL MODAL -->
<div class="modal-overlay" id="processModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Process Payroll</span>
            <button class="modal-close" onclick="closeProcessModal()">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="process">

            <div class="form-row">
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">-- Select Employee --</option>
                        <?php while($e = $employees_list->fetch_assoc()): ?>
                        <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['label']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payroll Period *</label>
                    <select name="period_id" class="form-control" required>
                        <option value="">-- Select Period --</option>
                        <?php
                        $periods_list->data_seek(0);
                        while($pp = $periods_list->fetch_assoc()):
                        ?>
                        <option value="<?= $pp['period_id'] ?>">
                            <?= htmlspecialchars($pp['period_code']) ?>
                            (<?= date('M d, Y', strtotime($pp['payroll_date'])) ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Overtime Pay (₱)</label>
                    <input type="number" name="overtime_pay" class="form-control" value="0" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Total Bonus (₱)</label>
                    <input type="number" name="total_bonus" class="form-control" value="0" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Total Deductions (₱)</label>
                    <input type="number" name="total_deductions" class="form-control" value="0" min="0" step="0.01">
                </div>
            </div>

            <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
                💡 Base salary is automatically pulled from the employee's assigned position.
                Net = Base + OT + Bonus − Deductions.
            </p>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn" style="background:var(--border);color:var(--text)"
                    onclick="closeProcessModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💰 Process Payroll</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProcessModal()  { document.getElementById('processModal').classList.add('open'); }
function closeProcessModal() { document.getElementById('processModal').classList.remove('open'); }
document.getElementById('processModal').addEventListener('click', function(e) {
    if (e.target === this) closeProcessModal();
});
</script>

<?php include '../includes/footer.php'; ?>
