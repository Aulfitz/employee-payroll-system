<?php
require_once '../includes/auth.php';
require_once '../includes/db_connection.php';
requireLogin();

$conn = getConnection();
$msg = ''; $msgType = '';

// Handle POST: Add or Edit employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['employee_id'] ?? 0);
    $first_name  = trim($_POST['first_name']  ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $contact     = trim($_POST['contact_number'] ?? '');
    $gender      = $_POST['gender']      ?? '';
    $birth_date  = $_POST['birth_date']  ?? '';
    $date_hired  = $_POST['date_hired']  ?? '';
    $dept_id     = intval($_POST['department_id'] ?? 0);
    $pos_id      = intval($_POST['position_id']   ?? 0);
    $address     = trim($_POST['address'] ?? '');

    if ($id === 0) {
        // INSERT
        $emp_code = 'EMP' . strtoupper(substr(uniqid(), -5));
        $stmt = $conn->prepare("INSERT INTO employees
            (employee_code, first_name, last_name, middle_name, email, contact_number,
             gender, birth_date, date_hired, department_id, position_id, address, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Active')");
        $stmt->bind_param("sssssssssiis",
            $emp_code, $first_name, $last_name, $middle_name,
            $email, $contact, $gender, $birth_date, $date_hired,
            $dept_id, $pos_id, $address);
        if ($stmt->execute()) {
            $msg = "Employee added successfully! Code: $emp_code";
            $msgType = 'success';
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = 'danger';
        }
        $stmt->close();
    } else {
        // UPDATE
        $stmt = $conn->prepare("UPDATE employees SET
            first_name=?, last_name=?, middle_name=?, email=?, contact_number=?,
            gender=?, birth_date=?, date_hired=?, department_id=?, position_id=?, address=?
            WHERE employee_id=?");
        $stmt->bind_param("ssssssssiisi",
            $first_name, $last_name, $middle_name, $email, $contact,
            $gender, $birth_date, $date_hired, $dept_id, $pos_id, $address, $id);
        if ($stmt->execute()) {
            $msg = "Employee updated successfully!";
            $msgType = 'success';
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = 'danger';
        }
        $stmt->close();
    }
}

// Handle DELETE (soft delete)
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("UPDATE employees SET status='Resigned' WHERE employee_id=$del_id");
    $msg = "Employee removed from active list.";
    $msgType = 'success';
}

// Search
$search = trim($_GET['search'] ?? '');
$searchSQL = '';
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $searchSQL = "AND (e.first_name LIKE '%$s%' OR e.last_name LIKE '%$s%'
                   OR e.employee_code LIKE '%$s%' OR e.email LIKE '%$s%')";
}

// Fetch employees
$employees = $conn->query("SELECT e.*, d.department_name, p.position_name, p.base_salary
    FROM employees e
    LEFT JOIN departments d ON e.department_id=d.department_id
    LEFT JOIN positions p ON e.position_id=p.position_id
    WHERE e.status='Active' $searchSQL
    ORDER BY e.employee_id DESC");

// Dropdown data
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name");
$positions   = $conn->query("SELECT * FROM positions ORDER BY position_name");

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <?= $msgType==='success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Employee Records</span>
        <div class="search-box">
            <form method="GET" style="display:flex;gap:8px;align-items:center">
                <input type="text" name="search" class="search-input"
                       placeholder="Search name, code, email…"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary btn-sm">🔍 Search</button>
                <?php if ($search): ?>
                <a href="employees.php" class="btn btn-sm" style="background:var(--border);color:var(--text)">✕ Clear</a>
                <?php endif; ?>
            </form>
            <button class="btn btn-primary" onclick="openModal()">+ Add Employee</button>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Base Salary</th>
                    <th>Date Hired</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($employees->num_rows > 0): ?>
                <?php while($e = $employees->fetch_assoc()): ?>
                <tr>
                    <td><code><?= htmlspecialchars($e['employee_code']) ?></code></td>
                    <td>
                        <strong><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></strong>
                        <?php if ($e['middle_name']): ?>
                        <br><small style="color:var(--muted)"><?= htmlspecialchars($e['middle_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($e['department_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($e['position_name'] ?? '—') ?></td>
                    <td>₱<?= number_format($e['base_salary'] ?? 0, 2) ?></td>
                    <td><?= $e['date_hired'] ? date('M d, Y', strtotime($e['date_hired'])) : '—' ?></td>
                    <td><?= htmlspecialchars($e['contact_number'] ?? '—') ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm"
                            onclick="editEmployee(<?= htmlspecialchars(json_encode($e)) ?>)">
                            ✏️ Edit
                        </button>
                        <a href="?delete=<?= $e['employee_id'] ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Remove this employee from active list?')">
                            🗑️ Remove
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:32px">
                    <?= $search ? 'No employees match your search.' : 'No employees found. Add one!' ?>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="empModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Add Employee</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="employee_id" id="f_employee_id" value="0">

            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="f_first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="f_last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" id="f_middle_name" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="f_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" id="f_contact_number" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="f_gender" class="form-control">
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="birth_date" id="f_birth_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Date Hired *</label>
                    <input type="date" name="date_hired" id="f_date_hired" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" id="f_department_id" class="form-control" required>
                        <option value="">-- Select Department --</option>
                        <?php
                        $departments->data_seek(0);
                        while($d = $departments->fetch_assoc()):
                        ?>
                        <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position *</label>
                    <select name="position_id" id="f_position_id" class="form-control" required>
                        <option value="">-- Select Position --</option>
                        <?php
                        $positions->data_seek(0);
                        while($p = $positions->fetch_assoc()):
                        ?>
                        <option value="<?= $p['position_id'] ?>">
                            <?= htmlspecialchars($p['position_name']) ?> — ₱<?= number_format($p['base_salary'], 0) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px">
                <label>Address</label>
                <textarea name="address" id="f_address" class="form-control" rows="2"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn" style="background:var(--border);color:var(--text)"
                    onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">💾 Save Employee</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Employee';
    document.getElementById('submitBtn').textContent  = '💾 Save Employee';
    document.getElementById('f_employee_id').value = '0';
    document.querySelector('#empModal form').reset();
    document.getElementById('empModal').classList.add('open');
}

function editEmployee(data) {
    document.getElementById('modalTitle').textContent = 'Edit Employee';
    document.getElementById('submitBtn').textContent  = '💾 Update Employee';
    document.getElementById('f_employee_id').value    = data.employee_id;
    document.getElementById('f_first_name').value     = data.first_name  || '';
    document.getElementById('f_last_name').value      = data.last_name   || '';
    document.getElementById('f_middle_name').value    = data.middle_name || '';
    document.getElementById('f_email').value          = data.email       || '';
    document.getElementById('f_contact_number').value = data.contact_number || '';
    document.getElementById('f_gender').value         = data.gender      || '';
    document.getElementById('f_birth_date').value     = data.birth_date  || '';
    document.getElementById('f_date_hired').value     = data.date_hired  || '';
    document.getElementById('f_department_id').value  = data.department_id || '';
    document.getElementById('f_position_id').value    = data.position_id || '';
    document.getElementById('f_address').value        = data.address     || '';
    document.getElementById('empModal').classList.add('open');
}

function closeModal() {
    document.getElementById('empModal').classList.remove('open');
}

// Close on overlay click
document.getElementById('empModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include '../includes/footer.php'; ?>
