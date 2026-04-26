<?php
// ============================================================
// dashboard.php  –  Upgraded with charts & role-aware stats
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$stats   = getDashboardStats();
$empId   = $_SESSION['employee_id'] ?? null;
$isMan   = isManager();

// Recent work logs
$recentLogs = getWorkLogs(
    $isMan ? null : $empId,
    date('Y-m-01'),
    date('Y-m-d')
);
$recentLogs = array_slice($recentLogs, 0, 6);

// Own contribution this month (employee only)
$ownMonthHours = 0;
$ownLogs       = [];
if ($empId) {
    $ownLogs = getWorkLogs($empId, date('Y-m-01'), date('Y-m-d'));
    foreach ($ownLogs as $l) $ownMonthHours += $l['hours_worked'];
}

// Chart data (manager)
$chartEmpHours  = [];
$chartDailyHrs  = [];
if ($isMan) {
    $chartEmpHours = getHoursPerEmployee(date('Y-m-01'), date('Y-m-d'));
    $chartDailyHrs = getDailyHours(date('Y-m-01'), date('Y-m-d'));
}

// Employee chart (individual vs team split)
$empIndHours  = 0;
$empTeamHours = 0;
if (!$isMan && $empId) {
    foreach ($ownLogs as $l) {
        if ($l['task_type'] === 'individual') $empIndHours  += $l['hours_worked'];
        else                                  $empTeamHours += $l['hours_worked'];
    }
}

// Employee pending/approved counts
$ownPending  = count(array_filter($ownLogs, fn($l) => !$l['is_approved']));
$ownApproved = count(array_filter($ownLogs, fn($l) => $l['is_approved']));

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Stat Cards ────────────────────────────────────────── -->
<div class="stats-grid">
    <?php if ($isMan): ?>
    <div class="stat-card glass" data-color="blue">
        <div class="stat-icon">👥</div>
        <div class="stat-body">
            <span class="stat-label">Total Employees</span>
            <span class="stat-value"><?= $stats['total_employees'] ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="green">
        <div class="stat-icon">⏱</div>
        <div class="stat-body">
            <span class="stat-label">Hours This Month</span>
            <span class="stat-value"><?= number_format($stats['total_hours_month'], 1) ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="orange">
        <div class="stat-icon">⚡</div>
        <div class="stat-body">
            <span class="stat-label">Pending Approvals</span>
            <span class="stat-value"><?= $stats['pending_approvals'] ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="purple">
        <div class="stat-icon">💰</div>
        <div class="stat-body">
            <span class="stat-label">Payroll Processed</span>
            <span class="stat-value"><?= formatCurrency($stats['salary_month']) ?></span>
        </div>
    </div>
    <?php else: ?>
    <!-- Employee: role-appropriate stats -->
    <div class="stat-card glass" data-color="green">
        <div class="stat-icon">⏱</div>
        <div class="stat-body">
            <span class="stat-label">My Hours This Month</span>
            <span class="stat-value"><?= number_format($ownMonthHours, 1) ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="blue">
        <div class="stat-icon">📋</div>
        <div class="stat-body">
            <span class="stat-label">Log Entries</span>
            <span class="stat-value"><?= count($ownLogs) ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="orange">
        <div class="stat-icon">⏳</div>
        <div class="stat-body">
            <span class="stat-label">Pending Approval</span>
            <span class="stat-value"><?= $ownPending ?></span>
        </div>
    </div>
    <div class="stat-card glass" data-color="purple">
        <div class="stat-icon">✅</div>
        <div class="stat-body">
            <span class="stat-label">Approved Logs</span>
            <span class="stat-value"><?= $ownApproved ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Charts Row ────────────────────────────────────────── -->
<?php if ($isMan && (!empty($chartEmpHours) || !empty($chartDailyHrs))): ?>
<div class="charts-grid">
    <!-- Bar chart: hours per employee -->
    <div class="section-card glass chart-card">
        <div class="section-header">
            <h2>Hours per Employee <small>(this month)</small></h2>
        </div>
        <canvas id="chartEmpHours" height="200"></canvas>
    </div>
    <!-- Line chart: daily team hours -->
    <div class="section-card glass chart-card">
        <div class="section-header">
            <h2>Daily Team Hours <small>(this month)</small></h2>
        </div>
        <canvas id="chartDailyHrs" height="200"></canvas>
    </div>
</div>
<?php elseif (!$isMan && $empId && ($empIndHours + $empTeamHours) > 0): ?>
<!-- Employee: donut chart -->
<div class="section-card glass chart-card-single">
    <div class="section-header">
        <h2>My Work Split <small>(this month)</small></h2>
    </div>
    <div style="max-width:280px;margin:0 auto">
        <canvas id="chartDonut" height="220"></canvas>
    </div>
</div>
<?php endif; ?>

<?php if (!$isMan && $empId): ?>
<!-- Personal summary for employee -->
<div class="section-card glass">
    <div class="section-header">
        <h2>My Activity This Month</h2>
    </div>
    <div class="mini-stats">
        <div class="mini-stat">
            <span class="ms-label">Hours Logged</span>
            <span class="ms-val"><?= number_format($ownMonthHours, 2) ?></span>
        </div>
        <div class="mini-stat">
            <span class="ms-label">Log Entries</span>
            <span class="ms-val"><?= count($ownLogs) ?></span>
        </div>
        <div class="mini-stat">
            <span class="ms-label">Avg per Day</span>
            <span class="ms-val"><?= count($ownLogs) > 0 ? number_format($ownMonthHours / count($ownLogs), 1) : '0' ?></span>
        </div>
        <div class="mini-stat">
            <span class="ms-label">Individual Hrs</span>
            <span class="ms-val"><?= number_format($empIndHours, 1) ?></span>
        </div>
        <div class="mini-stat">
            <span class="ms-label">Team Hrs</span>
            <span class="ms-val"><?= number_format($empTeamHours, 1) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Logs -->
<div class="section-card glass">
    <div class="section-header">
        <h2><?= $isMan ? 'Recent Work Logs (All Team)' : 'My Recent Logs' ?></h2>
        <a href="<?= BASE_URL ?>worklogs.php" class="btn btn-sm btn-ghost">View All →</a>
    </div>
    <?php if (empty($recentLogs)): ?>
        <p class="empty-state">No work logs found for this month.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Hours</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= sanitize($log['employee_name']) ?></td>
                    <td><?= date('M d, Y', strtotime($log['log_date'])) ?></td>
                    <td><strong><?= number_format($log['hours_worked'], 2) ?>h</strong></td>
                    <td><span class="badge badge-<?= $log['task_type'] ?>"><?= ucfirst($log['task_type']) ?></span></td>
                    <td>
                        <?php if ($log['is_approved']): ?>
                            <span class="badge badge-success">Approved</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
<?php if ($isMan && !empty($chartEmpHours)): ?>
// Bar chart – hours per employee
new Chart(document.getElementById('chartEmpHours'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chartEmpHours, 'name')) ?>,
        datasets: [{
            label: 'Hours',
            data: <?= json_encode(array_map(fn($r) => round((float)$r['hours'], 2), $chartEmpHours)) ?>,
            backgroundColor: 'rgba(240,165,0,0.6)',
            borderColor: 'rgba(240,165,0,1)',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#8b949e' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#8b949e' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
        }
    }
});
<?php endif; ?>

<?php if ($isMan && !empty($chartDailyHrs)): ?>
// Line chart – daily team hours
new Chart(document.getElementById('chartDailyHrs'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('M d', strtotime($r['log_date'])), $chartDailyHrs)) ?>,
        datasets: [{
            label: 'Team Hours',
            data: <?= json_encode(array_map(fn($r) => round((float)$r['hours'], 2), $chartDailyHrs)) ?>,
            borderColor: 'rgba(0,191,165,1)',
            backgroundColor: 'rgba(0,191,165,0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: 'rgba(0,191,165,1)',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#8b949e' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#8b949e' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
        }
    }
});
<?php endif; ?>

<?php if (!$isMan && ($empIndHours + $empTeamHours) > 0): ?>
// Donut chart – individual vs team
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Individual', 'Team'],
        datasets: [{
            data: [<?= round($empIndHours, 2) ?>, <?= round($empTeamHours, 2) ?>],
            backgroundColor: ['rgba(240,165,0,0.8)', 'rgba(0,191,165,0.8)'],
            borderColor: ['rgba(240,165,0,1)', 'rgba(0,191,165,1)'],
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#e6edf3' } }
        },
        cutout: '65%'
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>