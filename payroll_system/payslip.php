<?php
// ============================================================
// payslip.php  –  Beautiful payslip card
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$empId    = (int)($_GET['emp_id'] ?? $_SESSION['employee_id'] ?? 0);
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

// Security: employee can only view their own payslip
if (!isManager() && $empId !== (int)($_SESSION['employee_id'] ?? 0)) {
    flashMessage('error', 'Access denied.');
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

// Fetch employee info
$emp = getEmployeeById($empId);
if (!$emp) {
    flashMessage('error', 'Employee not found.');
    header('Location: ' . BASE_URL . 'payroll.php');
    exit;
}

// Calculate for just this employee
$allData  = calculatePayroll($fromDate, $toDate);
$myData   = null;
foreach ($allData as $r) {
    if ((int)$r['employee_id'] === $empId) {
        $myData = $r;
        break;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header-actions">
    <h2 class="section-title">Payslip</h2>
    <div class="btn-row">
        <a href="javascript:window.print()" class="btn btn-ghost">🖨 Print</a>
        <a href="payroll.php?from=<?= $fromDate ?>&to=<?= $toDate ?>&calculate=1"
           class="btn btn-ghost">← Back to Payroll</a>
    </div>
</div>

<div class="payslip-wrap">
    <div class="payslip-card glass">
        <!-- Header -->
        <div class="payslip-header">
            <div class="payslip-company">
                <span class="ps-brand-icon">⬡</span>
                <div>
                    <h2><?= APP_NAME ?></h2>
                    <p>Work Contribution & Payroll System</p>
                </div>
            </div>
            <div class="payslip-meta">
                <span class="ps-label">Pay Period</span>
                <strong><?= date('M d', strtotime($fromDate)) ?> – <?= date('M d, Y', strtotime($toDate)) ?></strong>
                <span class="ps-label" style="margin-top:.5rem">Generated</span>
                <strong><?= date('M d, Y') ?></strong>
            </div>
        </div>

        <hr class="ps-divider">

        <!-- Employee Info -->
        <div class="payslip-employee">
            <div class="ps-avatar"><?= strtoupper(substr($emp['name'], 0, 1)) ?></div>
            <div class="ps-emp-details">
                <h3><?= sanitize($emp['name']) ?></h3>
                <p><?= sanitize($emp['job_role']) ?> · <?= sanitize($emp['department']) ?></p>
                <p><?= sanitize($emp['email']) ?></p>
            </div>
            <div class="ps-emp-id">
                <span class="ps-label">Employee ID</span>
                <strong>#<?= str_pad($emp['id'], 4, '0', STR_PAD_LEFT) ?></strong>
            </div>
        </div>

        <hr class="ps-divider">

        <?php if ($myData): ?>
        <!-- Earnings Breakdown -->
        <div class="payslip-body">
            <div class="ps-section">
                <h4>Work Summary</h4>
                <div class="ps-row"><span>Total Hours Worked</span><strong><?= formatHours($myData['total_hours']) ?></strong></div>
                <div class="ps-row"><span>Individual Hours</span>  <strong><?= formatHours($myData['ind_hours'])   ?></strong></div>
                <div class="ps-row"><span>Team Hours</span>        <strong><?= formatHours($myData['team_hours'])  ?></strong></div>
                <div class="ps-row highlight">
                    <span>Collaboration Score
                        <small>(ind×0.7 + team×0.3)</small>
                    </span>
                    <strong><?= number_format($myData['collab_score'], 4) ?></strong>
                </div>
                <div class="ps-row highlight">
                    <span>Contribution % of Team</span>
                    <strong class="pct-badge"><?= number_format($myData['contribution_pct'], 2) ?>%</strong>
                </div>
            </div>

            <div class="ps-section">
                <h4>Salary Breakdown</h4>
                <div class="ps-row">
                    <span>Base Salary (Annual)</span>
                    <strong><?= formatCurrency($myData['base_salary']) ?></strong>
                </div>
                <div class="ps-row">
                    <span>Fixed Component (50%)</span>
                    <strong><?= formatCurrency($myData['base_salary'] * 0.5) ?></strong>
                </div>
                <div class="ps-row">
                    <span>Performance Component
                        <small>(contribution% ÷ 100 × base)</small>
                    </span>
                    <strong><?= formatCurrency($myData['base_salary'] * ($myData['contribution_pct'] / 100)) ?></strong>
                </div>
                <hr class="ps-mini-divider">
                <div class="ps-row ps-total">
                    <span>💰 Net Payable (Period)</span>
                    <strong class="salary-big"><?= formatCurrency($myData['final_salary']) ?></strong>
                </div>
            </div>
        </div>

        <!-- Contribution Bar -->
        <div class="payslip-contrib-viz">
            <div class="contrib-labels">
                <span>Contribution</span>
                <span><?= number_format($myData['contribution_pct'], 2) ?>%</span>
            </div>
            <div class="contrib-track">
                <div class="contrib-progress"
                     style="width: <?= min(100, $myData['contribution_pct']) ?>%"></div>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state" style="padding:2rem">
            <p>No work logs found for this employee in the selected period.</p>
            <a href="payroll.php" class="btn btn-ghost">← Change Period</a>
        </div>
        <?php endif; ?>

        <hr class="ps-divider">

        <div class="payslip-footer">
            <p>This is a system-generated payslip. Formula: <code>salary = base × (0.5 + contribution% ÷ 100)</code></p>
            <p><strong><?= APP_NAME ?></strong> · Payroll Management System v<?= APP_VERSION ?></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>