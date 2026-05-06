<?php
// ============================================================
// PGCEAP Portal — Set Password (Scholar Invite)
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$valid = false;
$invite = null;
$scholar = null;

if (!$token) {
    $error = 'Invalid or missing invite token.';
} else {
    $db = getDB();
    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT i.*, s.first_name, s.last_name, s.email FROM auth_invites i JOIN scholars s ON s.id=i.scholar_id WHERE i.token_hash=? AND i.used_at IS NULL AND i.expires_at > NOW() LIMIT 1");
    $stmt->execute([$tokenHash]);
    $invite = $stmt->fetch();

    if (!$invite) {
        $error = 'This invite link is invalid or has already expired. Please request a new one from the admin.';
    } else {
        $valid = true;
    }
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE scholars SET password_hash=?, account_activated=1 WHERE id=?")->execute([$hash, $invite['scholar_id']]);
        $db->prepare("UPDATE auth_invites SET used_at=NOW() WHERE id=?")->execute([$invite['id']]);
        header('Location: ' . BASE_URL . '/index.php?success=password_set');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password — PGCEAP Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-logo"><i class="bi bi-key-fill text-white"></i></div>
    <h1 class="auth-title">Set Your Password</h1>
    <p class="auth-sub">Complete your PGCEAP Portal account setup</p>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($valid): ?>
    <div class="alert alert-info d-flex gap-2 mb-4">
        <i class="bi bi-person-fill flex-shrink-0"></i>
        <div>Welcome, <strong><?= htmlspecialchars($invite['first_name'].' '.$invite['last_name']) ?></strong>! Set a password for <strong><?= htmlspecialchars($invite['email']) ?></strong>.</div>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" name="password" required minlength="8" placeholder="Minimum 8 characters" id="pw1">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Confirm Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control" name="confirm" required placeholder="Re-enter password" id="pw2">
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-check-circle me-2"></i>Activate My Account
        </button>
    </form>
    <?php elseif (!$error): ?>
    <div class="alert alert-danger">Token validation failed.</div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="<?= BASE_URL ?>/index.php" class="small text-muted-pg"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
