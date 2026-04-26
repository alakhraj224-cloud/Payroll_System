<?php
// ============================================================
// includes/auth.php  –  Session & role-based access control
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Require login ───────────────────────────────────────────
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

// ── Require manager role ────────────────────────────────────
function requireManager(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'manager') {
        header('Location: ' . BASE_URL . 'dashboard.php?err=forbidden');
        exit;
    }
}

// ── Check if current user is manager ───────────────────────
function isManager(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

// ── Current user helpers ────────────────────────────────────
function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserName(): string
{
    return htmlspecialchars($_SESSION['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
}

function currentRole(): string
{
    return $_SESSION['role'] ?? 'employee';
}