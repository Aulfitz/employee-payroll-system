<?php
require_once '../includes/auth.php';
require_once '../includes/db_connection.php';
requireLogin();

$conn = getConnection();
$msg = ''; $msgType = '';

// Handle: Add attendance record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id   = intval($_POST['employee_id']);
    $date     = $_POST['date']     ?? date('Y-m-d');
    $time_in  = $_POST['time_in']  ?? null;
    $time_out = $_POST['time_out'] ?? null;
    $status   = $_POST['status']   ?? 'Present';
    $late_min = intval($_POST['late_minutes'] ?? 0);
    $remarks  = trim($_POST['remarks'] ?? '');

    // Check duplicate
    $chk = $conn->query("SELECT attendance_id FROM attendance
                          WHERE employee_id=$emp_id AND date='$date'");
    if ($chk->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE attendance SET time_in=?, time_out=?, status=?,
            late_minutes=?, remarks=? WHERE employee_id=? AND date=?");
        $stmt->bind_param("sssisis", $time_in, $time_out, $status, $late_min, $remarks, $emp_id, $date);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO attendance
            (employee_id, date, time_in, time_out, status, late_minutes, remarks)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssis", $emp_id, $date, $time_in, $time_out, $status, $late_min, $remarks);
    }

    if ($stmt->execute()) {
        $msg = "Attendance record saved!";
        $msgType = 'success';
    } else {
        $msg = "Error: " . $stmt->error;
        $msgType = 'danger';
    }
    $stmt->close();
}

// Date filter
$filterDate = $_GET['date'] ?? date('Y-m-d');

// Fetch attendance for selected date
$records = $conn->query("SELECT a.*, CONCAT(e.first_name,' ',e.last_name) AS full_name,
    e.employee_code, d.department_name
    FROM attendance a
    JOIN employees e ON a.employee_id=e.employee_id
    LEFT JOIN departments d ON e.department_id=d.department_id
    WHERE a.date='$filterDate'
    ORDER BY a.status, e.first_name");

// Summary counts for selected date
$sumR = $conn->query("SELECT status, COUNT(*) AS cnt FROM attendance WHERE date='$filterDate' GROUP BY status");
$summary = [];
while ($row = $sumR->fetch_assoc()) {
    $summary[$row['status']] = $row['cnt'];
}

// Employees dropdown
$employees_list = $conn->query("SELECT employee_id, CONCAT(employee_code,' — ',first_name,' ',last_name) AS label
    FROM employees WHERE status='Active' ORDER BY first_name");

include '../includes/header.php';
?>

<style>
    .att-filters { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .summary-pills { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .pill {
        padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 500;
        display: flex; align-items: center; gap: 6px;
    }
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= $msgType==='success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Date filter -->
<div class="att-filters">
    <form method="GET" style="display:flex;align-items:center;gap:8px">
        <label style="font-size:14px;font-weight:500">Date:</label>
        <input type="date" name="date" class="form-control" style="width:auto"
               value="<?= htmlspecialchars($filterDate) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
    <button class="btn btn-primary" onclick="openAttModal()">+ Log Attendance</button>
</div>

<!-- Summary pills -->
<div class="summary-pills">
    <div class="pill badge-success">✅ Present: <?= $summary['Present'] ?? 0 ?></div>
    <div class="pill badge-danger">❌ Absent: <?= $summary['Absent'] ?? 0 ?></div>
    <div class="pill badge-warning">⏰ Late: <?= $summary['Late'] ?? 0 ?></div>
    <div class="pill badge-info">🌓 Half Day: <?= $summary['Half Day'] ?? 0 ?></div>
    <div class="pill badge-muted">🏖️ On Leave: <?= $summary['On Leave'] ?? 0 ?></div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            Attendance — <?= date('F j, Y', strtotime($filterDate)) ?>
        </span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Total Hours</th>
                    <th>Late (min)</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($records->num_rows > 0): ?>
                <?php while($a = $records->fetch_assoc()): ?>
                <tr>
                    <td><code><?= htmlspecialchars($a['employee_code']) ?></code></td>
                    <td><?= htmlspecialchars($a['full_name']) ?></td>
                    <td><?= htmlspecialchars($a['department_name'] ?? '—') ?></td>
                    <td><?= $a['time_in']  ? date('h:i A', strtotime($a['time_in']))  : '—' ?></td>
                    <td><?= $a['time_out'] ? date('h:i A', strtotime($a['time_out'])) : '—' ?></td>
                    <td><?= $a['total_hours'] ? number_format($a['total_hours'], 1) . ' hrs' : '—' ?></td>
                    <td><?= $a['late_minutes'] > 0 ? $a['late_minutes'] . ' min' : '—' ?></td>
                    <td>
                        <?php $bClass = match($a['status']) {
                            'Present'  => 'badge-success',
                            'Absent'   => 'badge-danger',
                            'Late'     => 'badge-warning',
                            'Half Day' => 'badge-info',
                            default    => 'badge-muted'
                        }; ?>
                        <span class="badge <?= $bClass ?>"><?= $a['status'] ?></span>
                    </td>
                    <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($a['remarks'] ?? '') ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:32px">
                    No attendance records for this date. Use "+ Log Attendance" to add.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- LOG ATTENDANCE MODAL -->
<div class="modal-overlay" id="attModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Log Attendance</span>
            <button class="modal-close" onclick="closeAttModal()">×</button>
        </div>
        <form method="POST">
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
                    <label>Date *</label>
                    <input type="date" name="date" class="form-control"
                           value="<?= $filterDate ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Time In</label>
                    <input type="time" name="time_in" class="form-control">
                </div>
                <div class="form-group">
                    <label>Time Out</label>
                    <input type="time" name="time_out" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                        <option value="Half Day">Half Day</option>
                        <option value="On Leave">On Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Late Minutes</label>
                    <input type="number" name="late_minutes" class="form-control" value="0" min="0">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control" placeholder="Optional notes">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn" style="background:var(--border);color:var(--text)"
                    onclick="closeAttModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">📅 Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAttModal()  { document.getElementById('attModal').classList.add('open'); }
function closeAttModal() { document.getElementById('attModal').classList.remove('open'); }
document.getElementById('attModal').addEventListener('click', function(e) {
    if (e.target === this) closeAttModal();
});
</script>

<?php include '../includes/footer.php'; ?>
