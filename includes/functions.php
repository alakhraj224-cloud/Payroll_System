<?php
// ============================================================
// includes/functions.php  –  Business logic & helpers
// ============================================================

require_once __DIR__ . '/../config/config.php';

// ════════════════════════════════════════════════════════════
// CSRF PROTECTION
// ════════════════════════════════════════════════════════════

function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// ── Base URL (auto-detect) ──────────────────────────────────
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = dirname($_SERVER['SCRIPT_NAME']);
    // Walk up to find payroll_system root
    $parts    = explode('/', trim($script, '/'));
    $root     = '';
    foreach ($parts as $part) {
        $root .= '/' . $part;
        if ($part === 'payroll_system') break;
    }
    define('BASE_URL', $protocol . '://' . $host . $root . '/');
}

// ════════════════════════════════════════════════════════════
// USER / AUTH FUNCTIONS
// ════════════════════════════════════════════════════════════

/**
 * Authenticate a user by email + password.
 * Returns user row or null.
 */
function authenticateUser(string $email, string $password): ?array
{
    $db  = getDB();
    $sql = 'SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1';
    $st  = $db->prepare($sql);
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}

/**
 * Change a user's password.
 */
function changeUserPassword(int $userId, string $newHash): bool
{
    $db = getDB();
    $st = $db->prepare('UPDATE users SET password=? WHERE id=?');
    $st->execute([$newHash, $userId]);
    return $st->rowCount() > 0;
}

/**
 * Fetch all users with their employee profile.
 */
function getAllUsersWithEmployees(): array
{
    $db  = getDB();
    $sql = "SELECT u.id AS user_id, u.name, u.email, u.role,
                   e.id AS employee_id, e.department, e.job_role,
                   e.base_salary, e.joined_date
            FROM users u
            LEFT JOIN employees e ON e.user_id = u.id
            ORDER BY u.name ASC";
    return $db->query($sql)->fetchAll();
}

/**
 * Fetch a single employee row by user_id.
 */
function getEmployeeByUserId(int $userId): ?array
{
    $db  = getDB();
    $st  = $db->prepare(
        'SELECT e.*, u.name, u.email, u.role
         FROM employees e
         JOIN users u ON u.id = e.user_id
         WHERE e.user_id = ? LIMIT 1'
    );
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

/**
 * Fetch a single employee row by employee id.
 */
function getEmployeeById(int $empId): ?array
{
    $db  = getDB();
    $st  = $db->prepare(
        'SELECT e.*, u.name, u.email, u.role
         FROM employees e
         JOIN users u ON u.id = e.user_id
         WHERE e.id = ? LIMIT 1'
    );
    $st->execute([$empId]);
    return $st->fetch() ?: null;
}

// ════════════════════════════════════════════════════════════
// EMPLOYEE CRUD
// ════════════════════════════════════════════════════════════

function createEmployee(array $data): bool
{
    $db  = getDB();
    $db->beginTransaction();
    try {
        // Create user
        $hashedPw = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $st = $db->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)'
        );
        $st->execute([$data['name'], $data['email'], $hashedPw, 'employee']);
        $userId = $db->lastInsertId();

        // Create employee profile
        $st2 = $db->prepare(
            'INSERT INTO employees (user_id, department, job_role, base_salary, joined_date)
             VALUES (?,?,?,?,?)'
        );
        $st2->execute([
            $userId,
            $data['department'],
            $data['job_role'],
            $data['base_salary'],
            $data['joined_date'],
        ]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

function updateEmployee(int $empId, array $data): bool
{
    $db  = getDB();
    $db->beginTransaction();
    try {
        // Update employee profile
        $st = $db->prepare(
            'UPDATE employees SET department=?, job_role=?, base_salary=?, joined_date=?
             WHERE id=?'
        );
        $st->execute([
            $data['department'],
            $data['job_role'],
            $data['base_salary'],
            $data['joined_date'],
            $empId,
        ]);

        // Update user name/email
        $st2 = $db->prepare(
            'UPDATE users SET name=?, email=? WHERE id=
             (SELECT user_id FROM employees WHERE id=?)'
        );
        $st2->execute([$data['name'], $data['email'], $empId]);

        // Update password only if supplied
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $st3  = $db->prepare(
                'UPDATE users SET password=? WHERE id=
                 (SELECT user_id FROM employees WHERE id=?)'
            );
            $st3->execute([$hash, $empId]);
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

function deleteEmployee(int $empId): bool
{
    $db = getDB();
    // Cascade deletes work_logs via FK; user cascade deletes employee
    $st = $db->prepare(
        'DELETE u FROM users u JOIN employees e ON e.user_id=u.id WHERE e.id=?'
    );
    $st->execute([$empId]);
    return $st->rowCount() > 0;
}

// ════════════════════════════════════════════════════════════
// WORK LOG FUNCTIONS
// ════════════════════════════════════════════════════════════

/**
 * Add a work log entry with full server-side validation.
 * Returns ['ok'=>true] or ['error'=>'message']
 */
function addWorkLog(int $empId, string $date, float $hours, string $taskType, string $desc): array
{
    // ── Validation ──────────────────────────────────────────
    if ($hours <= 0 || $hours > 12) {
        return ['error' => 'Hours must be between 0.1 and 12.'];
    }
    if (!in_array($taskType, ['individual', 'team'], true)) {
        return ['error' => 'Invalid task type.'];
    }
    if (empty($date) || !strtotime($date)) {
        return ['error' => 'Invalid date.'];
    }
    if (strtotime($date) > time()) {
        return ['error' => 'Cannot log future dates.'];
    }

    $db = getDB();

    // ── Duplicate prevention (enforced by UNIQUE key too) ───
    $st = $db->prepare(
        'SELECT id FROM work_logs WHERE employee_id=? AND log_date=? LIMIT 1'
    );
    $st->execute([$empId, $date]);
    if ($st->fetch()) {
        return ['error' => 'A log for this date already exists.'];
    }

    // ── Insert ──────────────────────────────────────────────
    $st2 = $db->prepare(
        'INSERT INTO work_logs (employee_id, log_date, hours_worked, task_type, description)
         VALUES (?,?,?,?,?)'
    );
    $st2->execute([$empId, $date, $hours, $taskType, $desc]);

    return ['ok' => true];
}

/**
 * Update an existing work log (employee can only update own unapproved log).
 */
function updateWorkLog(int $logId, float $hours, string $taskType, string $desc): array
{
    if ($hours <= 0 || $hours > 12) {
        return ['error' => 'Hours must be between 0.1 and 12.'];
    }
    if (!in_array($taskType, ['individual', 'team'], true)) {
        return ['error' => 'Invalid task type.'];
    }
    $db = getDB();
    $st = $db->prepare(
        'UPDATE work_logs SET hours_worked=?, task_type=?, description=?
         WHERE id=? AND is_approved=0'
    );
    $st->execute([$hours, $taskType, $desc, $logId]);
    return $st->rowCount() > 0 ? ['ok' => true] : ['error' => 'Log not found or already approved.'];
}

/**
 * Check if an employee can delete a given log (must be unapproved + own).
 */
function canEmployeeDeleteLog(int $logId, int $empId): bool
{
    $db = getDB();
    $st = $db->prepare(
        'SELECT id FROM work_logs WHERE id=? AND employee_id=? AND is_approved=0 LIMIT 1'
    );
    $st->execute([$logId, $empId]);
    return (bool)$st->fetch();
}

/**
 * Fetch work logs with optional pagination.
 * Manager sees all; employee sees own only.
 */
function getWorkLogs(?int $empId = null, ?string $from = null, ?string $to = null, ?int $limit = null, int $offset = 0): array
{
    $db     = getDB();
    $params = [];
    $sql    = "SELECT wl.*, u.name AS employee_name, e.job_role
               FROM work_logs wl
               JOIN employees e ON e.id = wl.employee_id
               JOIN users u ON u.id = e.user_id
               WHERE 1=1";

    if ($empId !== null) {
        $sql     .= ' AND wl.employee_id = ?';
        $params[] = $empId;
    }
    if ($from) {
        $sql     .= ' AND wl.log_date >= ?';
        $params[] = $from;
    }
    if ($to) {
        $sql     .= ' AND wl.log_date <= ?';
        $params[] = $to;
    }

    $sql .= ' ORDER BY wl.log_date DESC, u.name ASC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    }
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Count work logs (for pagination).
 */
function countWorkLogs(?int $empId = null, ?string $from = null, ?string $to = null): int
{
    $db     = getDB();
    $params = [];
    $sql    = 'SELECT COUNT(*) FROM work_logs wl WHERE 1=1';
    if ($empId !== null) { $sql .= ' AND wl.employee_id = ?'; $params[] = $empId; }
    if ($from)           { $sql .= ' AND wl.log_date >= ?';   $params[] = $from; }
    if ($to)             { $sql .= ' AND wl.log_date <= ?';   $params[] = $to; }
    $st = $db->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function approveWorkLog(int $logId): bool
{
    $db = getDB();
    $st = $db->prepare('UPDATE work_logs SET is_approved=1 WHERE id=?');
    $st->execute([$logId]);
    return $st->rowCount() > 0;
}

function deleteWorkLog(int $logId): bool
{
    $db = getDB();
    $st = $db->prepare('DELETE FROM work_logs WHERE id=?');
    $st->execute([$logId]);
    return $st->rowCount() > 0;
}

// ════════════════════════════════════════════════════════════
// CONTRIBUTION & PAYROLL CALCULATION
// ════════════════════════════════════════════════════════════

/**
 * Calculate payroll for all employees in a date range.
 *
 * Logic:
 *  1. collaboration_score = (individual_hours * 0.7) + (team_hours * 0.3)
 *  2. contribution_pct    = (emp_collab_score / sum_all_collab_scores) * 100
 *  3. salary              = base_salary * (0.5 + contribution_pct / 100)
 *
 * Returns array of per-employee payroll rows.
 */
function calculatePayroll(string $from, string $to): array
{
    $db = getDB();

    // ── 1. Aggregate hours per employee ─────────────────────
    $st = $db->prepare(
        "SELECT wl.employee_id,
                u.name,
                e.job_role,
                e.department,
                e.base_salary,
                SUM(wl.hours_worked) AS total_hours,
                SUM(CASE WHEN wl.task_type='individual' THEN wl.hours_worked ELSE 0 END) AS ind_hours,
                SUM(CASE WHEN wl.task_type='team'       THEN wl.hours_worked ELSE 0 END) AS team_hours
         FROM work_logs wl
         JOIN employees e ON e.id  = wl.employee_id
         JOIN users     u ON u.id  = e.user_id
         WHERE wl.log_date BETWEEN ? AND ?
         GROUP BY wl.employee_id, u.name, e.job_role, e.department, e.base_salary"
    );
    $st->execute([$from, $to]);
    $rows = $st->fetchAll();

    if (empty($rows)) return [];

    // ── 2. Collaboration scores ──────────────────────────────
    foreach ($rows as &$row) {
        $row['collab_score'] = ($row['ind_hours'] * 0.7) + ($row['team_hours'] * 0.3);
    }
    unset($row);

    // ── 3. Normalise across team ─────────────────────────────
    $totalCollab = array_sum(array_column($rows, 'collab_score'));

    foreach ($rows as &$row) {
        // contribution_pct: 0-100
        $row['contribution_pct'] = $totalCollab > 0
            ? ($row['collab_score'] / $totalCollab) * 100
            : 0;

        // ── 4. Salary formula ────────────────────────────────
        // salary = base_salary * (0.5 + contribution_pct/100)
        // Max multiplier = 1.5 when contribution_pct = 100
        $row['final_salary'] = $row['base_salary'] * (0.5 + ($row['contribution_pct'] / 100));
    }
    unset($row);

    return $rows;
}

/**
 * Persist calculated payroll to the payroll table (replace existing for period).
 */
function savePayroll(string $from, string $to): void
{
    $db   = getDB();
    $rows = calculatePayroll($from, $to);

    foreach ($rows as $r) {
        // Delete existing record for this employee + period
        $del = $db->prepare(
            'DELETE FROM payroll WHERE employee_id=? AND period_start=? AND period_end=?'
        );
        $del->execute([$r['employee_id'], $from, $to]);

        // Insert fresh
        $ins = $db->prepare(
            'INSERT INTO payroll
             (employee_id, period_start, period_end, total_hours,
              individual_hours, team_hours, collaboration_score,
              contribution_pct, final_salary)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $r['employee_id'],
            $from,
            $to,
            $r['total_hours'],
            $r['ind_hours'],
            $r['team_hours'],
            $r['collab_score'],
            $r['contribution_pct'],
            $r['final_salary'],
        ]);
    }
}

/**
 * Retrieve saved payroll records.
 */
function getSavedPayroll(string $from, string $to): array
{
    $db = getDB();
    $st = $db->prepare(
        "SELECT p.*, u.name, e.job_role, e.department, e.base_salary
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         JOIN users     u ON u.id = e.user_id
         WHERE p.period_start = ? AND p.period_end = ?
         ORDER BY p.contribution_pct DESC"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

/**
 * Get all saved payroll periods (distinct) for history page.
 */
function getPayrollPeriods(): array
{
    $db = getDB();
    return $db->query(
        "SELECT period_start, period_end,
                COUNT(DISTINCT employee_id) AS emp_count,
                SUM(final_salary) AS total_salary,
                SUM(total_hours) AS total_hours
         FROM payroll
         GROUP BY period_start, period_end
         ORDER BY period_end DESC"
    )->fetchAll();
}

/**
 * Get hours per employee for chart (manager dashboard).
 */
function getHoursPerEmployee(string $from, string $to): array
{
    $db = getDB();
    $st = $db->prepare(
        "SELECT u.name, SUM(wl.hours_worked) AS hours
         FROM work_logs wl
         JOIN employees e ON e.id = wl.employee_id
         JOIN users u ON u.id = e.user_id
         WHERE wl.log_date BETWEEN ? AND ?
         GROUP BY wl.employee_id, u.name
         ORDER BY hours DESC"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

/**
 * Get daily total hours for chart (manager dashboard).
 */
function getDailyHours(string $from, string $to): array
{
    $db = getDB();
    $st = $db->prepare(
        "SELECT log_date, SUM(hours_worked) AS hours
         FROM work_logs
         WHERE log_date BETWEEN ? AND ?
         GROUP BY log_date
         ORDER BY log_date ASC"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

// ════════════════════════════════════════════════════════════
// DASHBOARD STATS
// ════════════════════════════════════════════════════════════

function getDashboardStats(): array
{
    $db = getDB();

    $totalEmp = $db->query(
        "SELECT COUNT(*) FROM users WHERE role='employee'"
    )->fetchColumn();

    $totalHoursMonth = $db->query(
        "SELECT COALESCE(SUM(hours_worked),0)
         FROM work_logs
         WHERE MONTH(log_date)=MONTH(CURDATE())
           AND YEAR(log_date)=YEAR(CURDATE())"
    )->fetchColumn();

    $pendingApprovals = $db->query(
        "SELECT COUNT(*) FROM work_logs WHERE is_approved=0"
    )->fetchColumn();

    $salaryMonth = $db->query(
        "SELECT COALESCE(SUM(final_salary),0)
         FROM payroll
         WHERE MONTH(period_end)=MONTH(CURDATE())
           AND YEAR(period_end)=YEAR(CURDATE())"
    )->fetchColumn();

    return [
        'total_employees'   => (int)$totalEmp,
        'total_hours_month' => (float)$totalHoursMonth,
        'pending_approvals' => (int)$pendingApprovals,
        'salary_month'      => (float)$salaryMonth,
    ];
}

// ════════════════════════════════════════════════════════════
// UTILITY HELPERS
// ════════════════════════════════════════════════════════════

function sanitize(string $val): string
{
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

function formatHours(float $h): string
{
    return number_format($h, 2) . ' hrs';
}

function flashMessage(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}