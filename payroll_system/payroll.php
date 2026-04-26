<?php
// ============================================================
// payroll.php  –  Payroll calculation + results
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Default period: current month
$fromDate   = $_GET['from']   ?? date('Y-m-01');
$toDate     = $_GET['to']     ?? date('Y-m-d');
$calculated = false;
$payData    = [];

// ── Manager: calculate & optionally save ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['calculate'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCsrfToken();
    $fromDate = $_POST['from'] ?? $_GET['from'] ?? date('Y-m-01');
    $toDate   = $_POST['to']   ?? $_GET['to']   ?? date('Y-m-d');

    if (strtotime($fromDate) > strtotime($toDate)) {
        flashMessage('error', '"From" date cannot be after "To" date.');
        header('Location: ' . BASE_URL . 'payroll.php');
        exit;
    }

    if (isManager() && isset($_POST['save_payroll'])) {
        savePayroll($fromDate, $toDate);
        flashMessage('success', 'Payroll saved successfully for the selected period.');
    }

    $payData    = calculatePayroll($fromDate, $toDate);
    $calculated = true;
}

// Employee: show only their own record
$myEmpId = (int)($_SESSION['employee_id'] ?? 0);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Period Selector ───────────────────────────────────── -->
<div class="section-card glass form-card">
    <div class="section-header">
        <h2>Calculate Payroll</h2>
    </div>
    <form method="POST" action="payroll.php" class="filter-bar" novalidate>
        <?= csrfField() ?>
        <label>From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>" class="filter-input" required>
        <label>To</label>
        <input type="date" name="to"   value="<?= htmlspecialchars($toDate) ?>"   class="filter-input" required>
        <button type="submit" class="btn btn-primary">Calculate</button>
        <?php if (isManager() && $calculated): ?>
        <button type="submit" name="save_payroll" value="1" class="btn btn-ghost">
            💾 Save Payroll
        </button>
        <a href="export.php?type=payroll&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>"
           class="btn btn-ghost">📥 Export CSV</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($calculated): ?>

<?php
// Filter for employee
$displayData = isManager()
    ? $payData
    : array_filter($payData, fn($r) => (int)$r['employee_id'] === $myEmpId);
?>

<!-- ── Summary Cards ─────────────────────────────────────── -->
<?php if (isManager()): ?>
<div class="stats-grid four-col">
    <?php
    $totHours  = array_sum(array_column($payData, 'total_hours'));
    $totSalary = array_sum(array_column($payData, 'final_salary'));
    $avgScore  = count($payData) > 0 ? array_sum(array_column($payData, 'contribution_pct')) / count($payData) : 0;
    ?>
    <div class="stat-card glass" data-color="blue">
        <div class="stat-icon">👥</div>
        <div class="stat-body">
            <span class="stat-label">Employees Processed</span>
            <span class="stat-value"><?= count($payData) ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="green">
        <div class="stat-icon">⏱</div>
        <div class="stat-body">
            <span class="stat-label">Total Hours</span>
            <span class="stat-value"><?= number_format($totHours, 1) ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="orange">
        <div class="stat-icon">📊</div>
        <div class="stat-body">
            <span class="stat-label">Avg Contribution</span>
            <span class="stat-value"><?= number_format($avgScore, 1) ?>%</span>
        </div>
    </div>
    <div class="stat-card glass" data-color="purple">
        <div class="stat-icon">💰</div>
        <div class="stat-body">
            <span class="stat-label">Total Payroll</span>
            <span class="stat-value"><?= formatCurrency($totSalary) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Results Table ─────────────────────────────────────── -->
<div class="section-card glass">
    <div class="section-header">
        <h2>Payroll Results
            <small><?= date('M d', strtotime($fromDate)) ?> – <?= date('M d, Y', strtotime($toDate)) ?></small>
        </h2>
    </div>

    <?php if (empty($displayData)): ?>
        <p class="empty-state">No work logs found for the selected period.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table payroll-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Total Hrs</th>
                    <th>Indiv. Hrs</th>
                    <th>Team Hrs</th>
                    <th>Collab. Score</th>
                    <th>Contribution %</th>
                    <th>Base Salary</th>
                    <th>Final Salary</th>
                    <th>Payslip</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayData as $r): ?>
                <tr>
                    <td>
                        <div class="name-cell">
                            <span class="avatar-sm"><?= strtoupper(substr($r['name'], 0, 1)) ?></span>
                            <div>
                                <strong><?= sanitize($r['name']) ?></strong><br>
                                <small><?= sanitize($r['job_role']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($r['department']) ?></td>
                    <td><?= number_format($r['total_hours'], 2) ?></td>
                    <td><?= number_format($r['ind_hours'],   2) ?></td>
                    <td><?= number_format($r['team_hours'],  2) ?></td>
                    <td>
                        <div class="score-cell">
                            <?= number_format($r['collab_score'], 3) ?>
                            <div class="score-bar">
                                <div class="score-fill" style="width:<?= min(100, $r['contribution_pct']) ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong class="contrib-pct"><?= number_format($r['contribution_pct'], 2) ?>%</strong>
                    </td>
                    <td><?= formatCurrency($r['base_salary']) ?></td>
                    <td class="salary-cell">
                        <strong><?= formatCurrency($r['final_salary']) ?></strong>
                        <?php
                        $diff    = $r['final_salary'] - $r['base_salary'];
                        $diffPct = $r['base_salary'] > 0 ? ($diff / $r['base_salary'] * 100) : 0;
                        $cls     = $diff >= 0 ? 'up' : 'down';
                        ?>
                        <span class="salary-diff <?= $cls ?>">
                            <?= $diff >= 0 ? '▲' : '▼' ?> <?= number_format(abs($diffPct), 1) ?>%
                        </span>
                    </td>
                    <td>
                        <a href="payslip.php?emp_id=<?= $r['employee_id'] ?>&from=<?= $fromDate ?>&to=<?= $toDate ?>"
                           class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Calculation Explanation ───────────────────────────── -->
<div class="section-card glass formula-card">
    <div class="section-header">
        <h2>📐 How It's Calculated</h2>
    </div>
    <div class="formula-grid">
        <div class="formula-step">
            <span class="formula-num">1</span>
            <div>
                <strong>Collaboration Score</strong>
                <code>(individual_hours × 0.7) + (team_hours × 0.3)</code>
            </div>
        </div>
        <div class="formula-step">
            <span class="formula-num">2</span>
            <div>
                <strong>Contribution %</strong>
                <code>(employee_collab_score ÷ total_team_collab) × 100</code>
            </div>
        </div>
        <div class="formula-step">
            <span class="formula-num">3</span>
            <div>
                <strong>Final Salary</strong>
                <code>base_salary × (0.5 + contribution% ÷ 100)</code>
                <small>50% fixed + up to 50% performance-based (at max contribution)</small>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="empty-cta glass section-card">
    <span style="font-size:3rem">📊</span>
    <p>Select a date range and click <strong>Calculate</strong> to generate payroll results.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>