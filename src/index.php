<?php
// ============================================================
// PGCEAP Portal — Login Page
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in — only if user_type is explicitly valid
if (isLoggedIn()) {
    $ut = $_SESSION['user_type'] ?? '';
    if ($ut === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    } elseif ($ut === 'scholar') {
        header('Location: ' . BASE_URL . '/scholar/feed.php');
        exit;
    } else {
        // Corrupt/incomplete session — destroy and show login fresh
        session_destroy();
        session_start();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? 'admin'; // admin or scholar

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $db = getDB();

        if ($userType === 'admin') {
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                loginAdmin($user);
                // Update last login
                $db->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
                header('Location: ' . BASE_URL . '/admin/dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials. Please try again.';
            }
        } else {
            $stmt = $db->prepare("SELECT * FROM scholars WHERE email = ? AND account_activated = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {
                loginScholar($user);
                header('Location: ' . BASE_URL . '/scholar/feed.php');
                exit;
            } else {
                $error = 'Invalid credentials or account not yet activated.';
            }
        }
    }
}

// Check for messages from redirects
if (isset($_GET['error'])) {
    $msgs = [
        'session_expired' => 'Your session has expired. Please log in again.',
        'unauthorized'    => 'Access denied. Please log in with the correct account type.',
    ];
    $error = $msgs[$_GET['error']] ?? 'An error occurred.';
}
if (isset($_GET['success'])) {
    $msgs = ['password_set' => 'Password set successfully! You can now log in.'];
    $success = $msgs[$_GET['success']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PGCEAP Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="text-center mb-4">
        <img src="<?= BASE_URL ?>/assets/images/icon.png" alt="PGCEAP Logo" width="100" height="100">
    </div>
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-sub">Provincial Government College Educational Assistance Program</p>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Tab switcher -->
    <div class="d-flex gap-2 mb-4 p-1 rounded" style="background:var(--bg-elevated);">
        <button class="btn flex-fill tab-btn active" id="tabAdmin" onclick="switchTab('admin')">
            <i class="bi bi-person-badge me-2"></i>Admin / Staff
        </button>
        <button class="btn flex-fill tab-btn" id="tabScholar" onclick="switchTab('scholar')">
            <i class="bi bi-mortarboard me-2"></i>Scholar
        </button>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="user_type" id="userTypeInput" value="admin">

        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" name="password" placeholder="Enter password" required id="passwordInput">
                <button type="button" class="input-group-text" style="cursor:pointer;" onclick="togglePw()">
                    <i class="bi bi-eye" id="pwEyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

    <div class="mt-4 text-center">
        <small class="text-muted-pg">
            Are you a scholar with an invite link? Check your email for the activation link.
        </small>
    </div>

    <hr style="border-color:var(--border); margin: 20px 0 14px;">
    <div class="text-center">
        <small class="text-muted-pg">© <?= date('Y') ?> Provincial Government of Camarines Norte — PGCEAP Portal</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(type) {
    document.getElementById('userTypeInput').value = type;
    document.getElementById('tabAdmin').classList.toggle('btn-primary', type === 'admin');
    document.getElementById('tabAdmin').classList.toggle('btn-ghost',   type !== 'admin');
    document.getElementById('tabScholar').classList.toggle('btn-primary', type === 'scholar');
    document.getElementById('tabScholar').classList.toggle('btn-ghost',   type !== 'scholar');
}

function togglePw() {
    const pw = document.getElementById('passwordInput');
    const ic = document.getElementById('pwEyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        ic.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        ic.className = 'bi bi-eye';
    }
}

// Init tab style
document.getElementById('tabAdmin').classList.add('btn-primary');
document.getElementById('tabScholar').classList.add('btn-ghost');
</script>
</body>
</html>
