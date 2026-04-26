<?php
// ============================================================
// index.php  –  Login page
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $user = authenticateUser($email, $password);
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // Get employee_id for session
            $emp = getEmployeeByUserId($user['id']);
            $_SESSION['employee_id'] = $emp['id'] ?? null;

            header('Location: ' . BASE_URL . 'dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> – Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="login-page">

    <!-- Animated background blobs -->
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <div class="login-container">
        <div class="login-card glass">

            <div class="login-brand">
                <span class="brand-icon-lg">⬡</span>
                <h1><?= APP_NAME ?></h1>
                <p>Work Contribution &amp; Payroll System</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form" novalidate>
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-icon-wrap">
                        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email"
                               placeholder="you@company.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon-wrap">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password"
                               placeholder="••••••••"
                               required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Sign In
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

        </div>
    </div>

</body>
</html>