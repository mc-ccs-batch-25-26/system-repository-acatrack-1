<?php
// ============================================================
// PGCEAP Portal — Scholar Notifications
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('scholar');

$db = getDB();
$user = getCurrentUser();
$scholarId = $user['id'];

// Mark all read
if (isset($_GET['markread'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE scholar_id=?")->execute([$scholarId]);
    header('Location: ' . BASE_URL . '/scholar/notifications.php');
    exit;
}

// Fetch notifications
$notifs = $db->prepare("SELECT * FROM notifications WHERE scholar_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$scholarId]);
$notifications = $notifs->fetchAll();

// Mark as read on page view
$db->prepare("UPDATE notifications SET is_read=1 WHERE scholar_id=?")->execute([$scholarId]);

$unreadCount = 0; // already marked read

$typeIcons = ['approved'=>'check-circle-fill','rejected'=>'x-circle-fill','reminder'=>'alarm-fill','system'=>'info-circle-fill'];
$typeColors = ['approved'=>'success','rejected'=>'danger','reminder'=>'warning','system'=>'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Notifications — PGCEAP Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="scholar-layout">
<nav class="scholar-navbar">
    <div class="d-flex align-items-center gap-2 me-4">
        <div class="brand-icon" style="width:34px;height:34px;font-size:16px;"><i class="bi bi-mortarboard-fill"></i></div>
        <span style="font-weight:800;font-size:14px;">PGCEAP</span>
    </div>
    <a href="<?= BASE_URL ?>/scholar/feed.php" class="scholar-nav-link"><i class="bi bi-newspaper"></i> Feed</a>
    <a href="<?= BASE_URL ?>/scholar/profile.php" class="scholar-nav-link"><i class="bi bi-person-fill"></i> Profile</a>
    <a href="<?= BASE_URL ?>/scholar/requirements.php" class="scholar-nav-link"><i class="bi bi-clipboard2-check-fill"></i> Requirements</a>
    <a href="<?= BASE_URL ?>/scholar/notifications.php" class="scholar-nav-link active"><i class="bi bi-bell-fill"></i> Notifications</a>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="scholar-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-800 mb-0"><i class="bi bi-bell-fill me-2 text-accent"></i>Notifications</h4>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="empty-state mt-5">
        <i class="bi bi-bell-slash"></i>
        <p>No notifications yet. You'll be notified here when documents are reviewed or announcements are made.</p>
    </div>
    <?php else: ?>

    <?php foreach ($notifications as $n): ?>
    <?php $icon = $typeIcons[$n['type']] ?? 'info-circle-fill'; $color = $typeColors[$n['type']] ?? 'secondary'; ?>
    <div class="card mb-3 <?= !$n['is_read']?'border-start border-4':'border-0' ?>" style="<?= !$n['is_read']?"border-left-color:var(--brand-blue-lt)!important":"" ?>">
        <div class="card-body d-flex gap-3 align-items-start">
            <div class="stat-icon <?= $color ?>" style="width:40px;height:40px;font-size:16px;flex-shrink:0;">
                <i class="bi bi-<?= $icon ?>"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="fw-700"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="small text-muted-pg ms-3 flex-shrink-0"><?= formatDate($n['created_at']) ?></div>
                </div>
                <div class="small text-secondary-pg mt-1"><?= htmlspecialchars($n['message']) ?></div>
                <div class="mt-2"><span class="badge bg-<?= $color ?>"><?= ucfirst($n['type']) ?></span></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
