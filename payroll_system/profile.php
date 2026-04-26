<?php
// ============================================================
// profile.php  –  My Profile & Change Password (all roles)
// ============================================================
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId  = currentUserId();
$errors  = [];
$success = false;

// Fetch current user info
$db      = getDB();
$stmt    = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user    = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $currentPw  = $_POST['current_password']  ?? '';
    $newPw      = $_POST['new_password']      ?? '';
    $confirmPw  = $_POST['confirm_password']  ?? '';

    if (empty($currentPw))                        $errors[] = 'Current password is required.';
    if (strlen($newPw) < 6)                       $errors[] = 'New password must be at least 6 characters.';
    if ($newPw !== $confirmPw)                     $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        // Verify current password
        $stmtPw = $db->prepare('SELECT password FROM users WHERE id=? LIMIT 1');
        $stmtPw->execute([$userId]);
        $hash = $stmtPw->fetchColumn();

        if (!password_verify($currentPw, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            changeUserPassword($userId, $newHash);
            flashMessage('success', 'Password changed successfully.');
            header('Location: ' . BASE_URL . 'profile.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header-actions">
    <h2 class="section-title">My Profile</h2>
</div>

<div class="profile-layout">
    <!-- ── Profile Info Card ─────────────────────────────── -->
    <div class="section-card glass profile-card">
        <div class="profile-avatar-wrap">
            <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div>
                <h3><?= sanitize($user['name']) ?></h3>
                <p><?= sanitize($user['email']) ?></p>
                <span class="badge badge-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
            </div>
        </div>
        <div class="profile-details">
            <div class="pd-row">
                <span class="pd-label">Full Name</span>
                <span class="pd-val"><?= sanitize($user['name']) ?></span>
            </div>
            <div class="pd-row">
                <span class="pd-label">Email</span>
                <span class="pd-val"><?= sanitize($user['email']) ?></span>
            </div>
            <div class="pd-row">
                <span class="pd-label">Role</span>
                <span class="pd-val"><?= ucfirst($user['role']) ?></span>
            </div>
            <div class="pd-row">
                <span class="pd-label">User ID</span>
                <span class="pd-val" style="font-family:var(--font-mono)">#<?= str_pad($userId, 4, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>
    </div>

    <!-- ── Change Password Card ──────────────────────────── -->
    <div class="section-card glass">
        <div class="section-header">
            <h2>🔑 Change Password</h2>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="profile.php" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="form-group form-full">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label>New Password * <small>(min 6 chars)</small></label>
                    <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
