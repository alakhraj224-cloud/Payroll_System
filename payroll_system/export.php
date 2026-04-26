<?php
// ============================================================
// export.php  –  CSV Export (worklogs | payroll)
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$type = $_GET['type'] ?? 'worklogs'; // 'worklogs' | 'payroll'
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$empId = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : null;

// Security: employees can only export their own data
if (!isManager()) {
    $empId = (int)($_SESSION['employee_id'] ?? 0);
    if ($type === 'payroll') {
        // Employees not allowed to export full payroll
        flashMessage('error', 'Access denied.');
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

// Sanitize filename
$safeFrom = preg_replace('/[^0-9\-]/', '', $from);
$safeTo   = preg_replace('/[^0-9\-]/', '', $to);
$filename = "{$type}_{$safeFrom}_to_{$safeTo}.csv";

// Stream CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM for Excel UTF-8
fwrite($out, "\xEF\xBB\xBF");

if ($type === 'payroll') {
    // ── Payroll CSV ─────────────────────────────────────────
    requireManager();
    $rows = calculatePayroll($from, $to);

    fputcsv($out, ['Employee', 'Department', 'Job Role', 'Total Hours', 'Individual Hrs',
                   'Team Hrs', 'Collab Score', 'Contribution %', 'Base Salary (₹)', 'Final Salary (₹)']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'],
            $r['department'],
            $r['job_role'],
            number_format($r['total_hours'], 2),
            number_format($r['ind_hours'],   2),
            number_format($r['team_hours'],  2),
            number_format($r['collab_score'], 4),
            number_format($r['contribution_pct'], 2),
            number_format($r['base_salary'],  2),
            number_format($r['final_salary'], 2),
        ]);
    }

} else {
    // ── Work Logs CSV ────────────────────────────────────────
    $rows = getWorkLogs($empId, $from, $to);

    fputcsv($out, ['Employee', 'Job Role', 'Date', 'Hours Worked', 'Task Type', 'Description', 'Approved']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['employee_name'],
            $r['job_role'],
            $r['log_date'],
            number_format($r['hours_worked'], 2),
            ucfirst($r['task_type']),
            $r['description'],
            $r['is_approved'] ? 'Yes' : 'No',
        ]);
    }
}

fclose($out);
exit;
