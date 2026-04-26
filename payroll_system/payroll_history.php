<?php
// ============================================================
// payroll_history.php  –  Saved payroll periods (Manager only)
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireManager();

$periods = getPayrollPeriods();

require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header-actions">
    <h2 class="section-title">Payroll History</h2>
    <a href="payroll.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" width="16"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        New Payroll Run
    </a>
</div>

<div class="section-card glass">
    <?php if (empty($periods)): ?>
        <div class="empty-cta">
            <span style="font-size:3rem">📂</span>
            <p>No saved payroll records yet.</p>
            <a href="payroll.php" class="btn btn-primary">Run & Save Payroll</a>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Employees</th>
                    <th>Total Hours</th>
                    <th>Total Payroll</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td>
                        <strong><?= date('M d', strtotime($p['period_start'])) ?> – <?= date('M d, Y', strtotime($p['period_end'])) ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-employee"><?= $p['emp_count'] ?> emp</span>
                    </td>
                    <td><?= number_format($p['total_hours'], 1) ?> hrs</td>
                    <td><strong style="color:var(--success)"><?= formatCurrency((float)$p['total_salary']) ?></strong></td>
                    <td class="actions-cell">
                        <a href="payroll.php?from=<?= urlencode($p['period_start']) ?>&to=<?= urlencode($p['period_end']) ?>&calculate=1"
                           class="btn btn-sm btn-ghost">View Results</a>
                        <a href="export.php?type=payroll&from=<?= urlencode($p['period_start']) ?>&to=<?= urlencode($p['period_end']) ?>"
                           class="btn btn-sm btn-ghost">📥 Export CSV</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
