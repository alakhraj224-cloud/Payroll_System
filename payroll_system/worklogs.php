<?php
// ============================================================
// worklogs.php  –  Work log entry + management (upgraded)
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$errors = [];
$logsPerPage = 15;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $logsPerPage;

// ── Determine employee context ───────────────────────────────
$filterEmpId = null;
if (!isManager()) {
    $filterEmpId = (int)($_SESSION['employee_id'] ?? 0);
}

// ── Filters ──────────────────────────────────────────────────
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
if (isManager() && !empty($_GET['emp_filter'])) {
    $filterEmpId = (int)$_GET['emp_filter'];
}

// ── Handle POST: add / edit / delete log ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $formAction = $_POST['form_action'] ?? '';

    // ── Add log ──────────────────────────────────────────────
    if ($formAction === 'add_log') {
        $targetEmpId = isManager()
            ? (int)($_POST['employee_id'] ?? 0)
            : (int)($_SESSION['employee_id'] ?? 0);

        $date     = trim($_POST['log_date']     ?? '');
        $hours    = (float)($_POST['hours_worked'] ?? 0);
        $taskType = trim($_POST['task_type']    ?? '');
        $desc     = trim($_POST['description']  ?? '');

        if ($targetEmpId <= 0) {
            $errors[] = 'Select a valid employee.';
        } else {
            $result = addWorkLog($targetEmpId, $date, $hours, $taskType, $desc);
            if (isset($result['ok'])) {
                flashMessage('success', 'Work log added successfully.');
                header('Location: ' . BASE_URL . 'worklogs.php');
                exit;
            } else {
                $errors[] = $result['error'];
            }
        }
    }

    // ── Edit log (employee self-service on unapproved logs) ──
    if ($formAction === 'edit_log') {
        $logId    = (int)($_POST['log_id'] ?? 0);
        $hours    = (float)($_POST['hours_worked'] ?? 0);
        $taskType = trim($_POST['task_type']  ?? '');
        $desc     = trim($_POST['description'] ?? '');

        // Employees may only edit their own unapproved logs
        if (!isManager() && !canEmployeeDeleteLog($logId, (int)($_SESSION['employee_id'] ?? 0))) {
            flashMessage('error', 'You cannot edit this log.');
        } else {
            $result = updateWorkLog($logId, $hours, $taskType, $desc);
            if (isset($result['ok'])) {
                flashMessage('success', 'Work log updated.');
            } else {
                flashMessage('error', $result['error']);
            }
        }
        header('Location: ' . BASE_URL . 'worklogs.php');
        exit;
    }

    // ── Approve log (manager only, POST) ─────────────────────
    if ($formAction === 'approve_log' && isManager()) {
        approveWorkLog((int)($_POST['log_id'] ?? 0));
        flashMessage('success', 'Log approved.');
        header('Location: ' . BASE_URL . 'worklogs.php');
        exit;
    }

    // ── Delete log ────────────────────────────────────────────
    if ($formAction === 'delete_log') {
        $logId = (int)($_POST['log_id'] ?? 0);
        if (isManager()) {
            deleteWorkLog($logId);
            flashMessage('success', 'Log deleted.');
        } elseif (canEmployeeDeleteLog($logId, (int)($_SESSION['employee_id'] ?? 0))) {
            deleteWorkLog($logId);
            flashMessage('success', 'Log deleted.');
        } else {
            flashMessage('error', 'Cannot delete this log (already approved or not yours).');
        }
        header('Location: ' . BASE_URL . 'worklogs.php');
        exit;
    }
}

// ── Fetch data ───────────────────────────────────────────────
$totalLogs = countWorkLogs($filterEmpId, $fromDate, $toDate);
$totalPages = max(1, (int)ceil($totalLogs / $logsPerPage));
$logs = getWorkLogs($filterEmpId, $fromDate, $toDate, $logsPerPage, $offset);

// Fetch employees list for manager form + filter
$allEmployees = [];
if (isManager()) {
    $allEmployees = getAllUsersWithEmployees();
    $allEmployees = array_filter($allEmployees, fn($e) => $e['role'] === 'employee' && $e['employee_id']);
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <ul><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ── ADD LOG FORM ──────────────────────────────────────── -->
<div class="section-card glass form-card">
    <div class="section-header">
        <h2>Log Work Hours</h2>
    </div>
    <form method="POST" action="worklogs.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="add_log">
        <div class="form-grid">
            <?php if (isManager()): ?>
            <div class="form-group">
                <label>Employee *</label>
                <select name="employee_id" required>
                    <option value="">— Select Employee —</option>
                    <?php foreach ($allEmployees as $e): ?>
                    <option value="<?= $e['employee_id'] ?>"
                        <?= (($_POST['employee_id'] ?? '') == $e['employee_id']) ? 'selected' : '' ?>>
                        <?= sanitize($e['name']) ?> (<?= sanitize($e['job_role'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="log_date" required
                       max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($_POST['log_date'] ?? date('Y-m-d')) ?>">
            </div>

            <div class="form-group">
                <label>Hours Worked * <small>(max 12)</small></label>
                <input type="number" name="hours_worked" min="0.1" max="12" step="0.25" required
                       value="<?= htmlspecialchars($_POST['hours_worked'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Task Type *</label>
                <select name="task_type" required>
                    <option value="individual" <?= ($_POST['task_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual (weight: 70%)</option>
                    <option value="team"       <?= ($_POST['task_type'] ?? '') === 'team'       ? 'selected' : '' ?>>Team (weight: 30%)</option>
                </select>
            </div>

            <div class="form-group form-full">
                <label>Task Description</label>
                <textarea name="description" rows="2"
                          placeholder="Brief description of today's work…"><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Log</button>
        </div>
    </form>
</div>

<!-- ── FILTER / SEARCH ───────────────────────────────────── -->
<div class="section-card glass">
    <form method="GET" action="worklogs.php" class="filter-bar">
        <?php if (isManager()): ?>
        <select name="emp_filter" class="filter-select">
            <option value="">All Employees</option>
            <?php foreach ($allEmployees as $e): ?>
            <option value="<?= $e['employee_id'] ?>"
                <?= (($_GET['emp_filter'] ?? '') == $e['employee_id']) ? 'selected' : '' ?>>
                <?= sanitize($e['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>" class="filter-input">
        <span>to</span>
        <input type="date" name="to"   value="<?= htmlspecialchars($toDate) ?>"   class="filter-input">
        <button type="submit" class="btn btn-ghost">Filter</button>
        <a href="worklogs.php" class="btn btn-ghost">Reset</a>
        <a href="export.php?type=worklogs&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?><?= $filterEmpId ? '&emp_id='.$filterEmpId : '' ?>"
           class="btn btn-ghost" style="margin-left:auto">
            📥 Export CSV
        </a>
    </form>
</div>

<!-- ── LOGS TABLE ───────────────────────────────────────── -->
<div class="section-card glass">
    <div class="section-header">
        <h2>Work Log History</h2>
        <span class="count-badge"><?= $totalLogs ?> entries</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Hours</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="empty-state">No logs found for this period.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <span class="avatar-sm"><?= strtoupper(substr($log['employee_name'], 0, 1)) ?></span>
                            <?= sanitize($log['employee_name']) ?>
                        </div>
                    </td>
                    <td><?= date('M d, Y', strtotime($log['log_date'])) ?></td>
                    <td><strong><?= number_format($log['hours_worked'], 2) ?>h</strong></td>
                    <td><span class="badge badge-<?= $log['task_type'] ?>"><?= ucfirst($log['task_type']) ?></span></td>
                    <td class="desc-cell"><?= sanitize($log['description'] ?: '—') ?></td>
                    <td>
                        <?php if ($log['is_approved']): ?>
                            <span class="badge badge-success">✓ Approved</span>
                        <?php else: ?>
                            <span class="badge badge-pending">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <?php if (isManager()): ?>
                            <?php if (!$log['is_approved']): ?>
                            <form method="POST" action="worklogs.php" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="approve_log">
                                <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="worklogs.php" style="display:inline"
                                  onsubmit="return confirm('Delete this log?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="delete_log">
                                <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php elseif (!$log['is_approved']): ?>
                            <!-- Employee can edit/delete their own unapproved logs -->
                            <button class="btn btn-sm btn-ghost"
                                    onclick="openEditModal(<?= $log['id'] ?>, <?= $log['hours_worked'] ?>, '<?= $log['task_type'] ?>', '<?= addslashes($log['description']) ?>')">
                                Edit
                            </button>
                            <form method="POST" action="worklogs.php" style="display:inline"
                                  onsubmit="return confirm('Delete this log?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="delete_log">
                                <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm btn-ghost">← Prev</a>
        <?php endif; ?>
        <span class="pag-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm btn-ghost">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Edit Log Modal ────────────────────────────────────── -->
<div id="editModal" class="modal-overlay" style="display:none">
    <div class="modal-box glass">
        <div class="modal-header">
            <h3>Edit Work Log</h3>
            <button onclick="closeEditModal()" class="modal-close">×</button>
        </div>
        <form method="POST" action="worklogs.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="edit_log">
            <input type="hidden" name="log_id" id="editLogId">
            <div class="form-grid" style="margin-top:16px">
                <div class="form-group">
                    <label>Hours Worked *</label>
                    <input type="number" name="hours_worked" id="editHours" min="0.1" max="12" step="0.25" required>
                </div>
                <div class="form-group">
                    <label>Task Type *</label>
                    <select name="task_type" id="editTaskType">
                        <option value="individual">Individual (70%)</option>
                        <option value="team">Team (30%)</option>
                    </select>
                </div>
                <div class="form-group form-full">
                    <label>Description</label>
                    <textarea name="description" id="editDesc" rows="2"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" onclick="closeEditModal()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(logId, hours, taskType, desc) {
    document.getElementById('editLogId').value = logId;
    document.getElementById('editHours').value = hours;
    document.getElementById('editTaskType').value = taskType;
    document.getElementById('editDesc').value = desc;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('editModal')) closeEditModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>