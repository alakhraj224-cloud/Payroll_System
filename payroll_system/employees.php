<?php
// ============================================================
// employees.php  –  Manager-only CRUD for employees
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireManager();

$action = $_GET['action'] ?? 'list';
$empId  = (int)($_GET['id'] ?? 0);
$errors = [];
$emp    = null;

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $postAction = $_POST['form_action'] ?? 'create';

    // ── DELETE ──────────────────────────────────────────────
    if ($postAction === 'delete') {
        $delId = (int)($_POST['emp_id'] ?? 0);
        if ($delId && deleteEmployee($delId)) {
            flashMessage('success', 'Employee deleted successfully.');
        } else {
            flashMessage('error', 'Could not delete employee.');
        }
        header('Location: ' . BASE_URL . 'employees.php');
        exit;
    }

    // ── CREATE / UPDATE ──────────────────────────────────────
    $postEmpId = (int)($_POST['emp_id'] ?? 0);

    $data = [
        'name'        => trim($_POST['name']        ?? ''),
        'email'       => trim($_POST['email']       ?? ''),
        'password'    => trim($_POST['password']    ?? ''),
        'department'  => trim($_POST['department']  ?? ''),
        'job_role'    => trim($_POST['job_role']    ?? ''),
        'base_salary' => (float)($_POST['base_salary'] ?? 0),
        'joined_date' => trim($_POST['joined_date'] ?? ''),
    ];

    // Validation
    if (empty($data['name']))       $errors[] = 'Name is required.';
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                     $errors[] = 'Valid email is required.';
    if ($postAction === 'create' && empty($data['password']))
                                     $errors[] = 'Password is required.';
    if (!empty($data['password']) && strlen($data['password']) < 6)
                                     $errors[] = 'Password must be ≥ 6 characters.';
    if ($data['base_salary'] <= 0)   $errors[] = 'Base salary must be positive.';
    if (empty($data['department']))  $errors[] = 'Department is required.';
    if (empty($data['job_role']))    $errors[] = 'Job role is required.';
    if (empty($data['joined_date'])) $errors[] = 'Joined date is required.';

    if (empty($errors)) {
        if ($postAction === 'create') {
            if (createEmployee($data)) {
                flashMessage('success', 'Employee created successfully.');
                header('Location: ' . BASE_URL . 'employees.php');
                exit;
            } else {
                $errors[] = 'Email already in use or DB error.';
            }
        } else {
            if (updateEmployee($postEmpId, $data)) {
                flashMessage('success', 'Employee updated successfully.');
                header('Location: ' . BASE_URL . 'employees.php');
                exit;
            } else {
                $errors[] = 'Could not update employee.';
            }
        }
    }

    // If errors on edit, keep edit form open
    if ($postAction === 'edit') {
        $action = 'edit';
        $empId  = $postEmpId;
        $emp    = getEmployeeById($postEmpId);
    } else {
        $action = 'create';
    }
}

// ── Load employee for edit ───────────────────────────────────
if ($action === 'edit' && $empId && !$emp) {
    $emp = getEmployeeById($empId);
    if (!$emp) {
        flashMessage('error', 'Employee not found.');
        header('Location: ' . BASE_URL . 'employees.php');
        exit;
    }
}

// ── Fetch list ───────────────────────────────────────────────
$employees = getAllUsersWithEmployees();

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <ul><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ── LIST VIEW ─────────────────────────────────────────── -->
<?php if ($action === 'list'): ?>

<div class="section-header-actions">
    <h2 class="section-title">All Employees</h2>
    <div class="btn-row">
        <input type="text" id="empSearch" placeholder="🔍 Search by name, dept, role…"
               style="width:240px" oninput="filterEmployees(this.value)">
        <a href="?action=create" class="btn btn-primary">
            <svg viewBox="0 0 24 24" width="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Employee
        </a>
    </div>
</div>

<div class="section-card glass">
    <div class="table-wrap">
        <table class="data-table" id="empTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Base Salary</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr><td colspan="8" class="empty-state">No employees found.</td></tr>
                <?php else: ?>
                <?php foreach ($employees as $i => $e): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="name-cell">
                            <span class="avatar-sm"><?= strtoupper(substr($e['name'], 0, 1)) ?></span>
                            <?= sanitize($e['name']) ?>
                        </div>
                    </td>
                    <td><?= sanitize($e['email']) ?></td>
                    <td><span class="badge badge-<?= $e['role'] ?>"><?= ucfirst($e['role']) ?></span></td>
                    <td><?= sanitize($e['department'] ?? '—') ?></td>
                    <td><strong><?= formatCurrency((float)($e['base_salary'] ?? 0)) ?></strong></td>
                    <td><?= $e['joined_date'] ? date('M d, Y', strtotime($e['joined_date'])) : '—' ?></td>
                    <td class="actions-cell">
                        <?php if ($e['employee_id']): ?>
                        <a href="?action=edit&id=<?= $e['employee_id'] ?>" class="btn btn-sm btn-ghost">Edit</a>
                        <a href="payslip.php?emp_id=<?= $e['employee_id'] ?>" class="btn btn-sm btn-ghost">Payslip</a>
                        <!-- POST-based delete (no CSRF-vulnerable GET link) -->
                        <form method="POST" action="employees.php" style="display:inline"
                              onsubmit="return confirm('Delete employee &quot;<?= addslashes($e['name']) ?>&quot;? This cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="emp_id" value="<?= $e['employee_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── CREATE / EDIT FORM ────────────────────────────────── -->
<?php else: ?>

<div class="section-header-actions">
    <h2 class="section-title"><?= $action === 'edit' ? 'Edit Employee' : 'Add New Employee' ?></h2>
    <a href="employees.php" class="btn btn-ghost">← Back to List</a>
</div>

<div class="section-card glass form-card">
    <form method="POST" action="employees.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'create' ?>">
        <?php if ($action === 'edit'): ?>
        <input type="hidden" name="emp_id" value="<?= $empId ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required
                       value="<?= sanitize($_POST['name'] ?? $emp['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required
                       value="<?= sanitize($_POST['email'] ?? $emp['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= $action === 'edit' ? 'New Password (leave blank to keep)' : 'Password *' ?></label>
                <input type="password" name="password" <?= $action === 'create' ? 'required' : '' ?> minlength="6">
            </div>
            <div class="form-group">
                <label>Department *</label>
                <input type="text" name="department" required
                       value="<?= sanitize($_POST['department'] ?? $emp['department'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Job Role *</label>
                <input type="text" name="job_role" required
                       value="<?= sanitize($_POST['job_role'] ?? $emp['job_role'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Base Salary (₹/year) *</label>
                <input type="number" name="base_salary" min="1" step="0.01" required
                       value="<?= $_POST['base_salary'] ?? $emp['base_salary'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Joined Date *</label>
                <input type="date" name="joined_date" required
                       value="<?= $_POST['joined_date'] ?? $emp['joined_date'] ?? '' ?>">
            </div>
        </div>

        <div class="form-actions">
            <a href="employees.php" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <?= $action === 'edit' ? 'Update Employee' : 'Create Employee' ?>
            </button>
        </div>
    </form>
</div>

<?php endif; ?>

<script>
function filterEmployees(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#empTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>