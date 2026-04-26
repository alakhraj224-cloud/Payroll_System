<?php
// ============================================================
// includes/header.php  –  Global header + sidebar
// ============================================================
require_once __DIR__ . '/auth.php';
$flash   = getFlash();
$curPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> – <?= ucfirst($curPage) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<div class="app-shell">
    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">⬡</span>
            <span class="brand-name"><?= APP_NAME ?></span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= BASE_URL ?>dashboard.php"
               class="nav-link <?= $curPage==='dashboard'  ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>
            <?php if (isManager()): ?>
            <a href="<?= BASE_URL ?>employees.php"
               class="nav-link <?= $curPage==='employees'  ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Employees
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>worklogs.php"
               class="nav-link <?= $curPage==='worklogs'   ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Work Logs
            </a>
            <a href="<?= BASE_URL ?>payroll.php"
               class="nav-link <?= $curPage==='payroll'    ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Payroll
            </a>
            <?php if (isManager()): ?>
            <a href="<?= BASE_URL ?>payroll_history.php"
               class="nav-link <?= $curPage==='payroll_history' ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Pay History
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>profile.php"
               class="nav-link <?= $curPage==='profile' ? 'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                My Profile
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-pill">
                <span class="user-avatar"><?= strtoupper(substr(currentUserName(), 0, 1)) ?></span>
                <div class="user-info">
                    <span class="user-name"><?= currentUserName() ?></span>
                    <span class="user-role"><?= ucfirst(currentRole()) ?></span>
                </div>
            </div>
            <a href="<?= BASE_URL ?>logout.php" class="logout-btn" title="Logout">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- ── Main content ────────────────────────────────── -->
    <div class="main-content">
        <header class="topbar">
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <svg viewBox="0 0 24 24"><line x1="3" y1="6"  x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1 class="page-title"><?= ucwords(str_replace('_', ' ', $curPage)) ?></h1>
            <div class="topbar-right">
                <span class="badge-role <?= currentRole() ?>"><?= ucfirst(currentRole()) ?></span>
            </div>
        </header>

        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>" id="flashMsg">
            <?= htmlspecialchars($flash['msg']) ?>
            <button onclick="this.parentElement.remove()" class="flash-close">×</button>
        </div>
        <?php endif; ?>

        <div class="page-body">