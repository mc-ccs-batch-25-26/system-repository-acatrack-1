<?php
// ============================================================
// PGCEAP Portal — Scholar Feed (Announcements)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('scholar');

$pageTitle = 'Announcements Feed';
$db = getDB();
$user = getCurrentUser();
$scholarId = $user['id'];

// Mark as read
$db->prepare("INSERT INTO announcement_reads (scholar_id, last_read_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE last_read_at=NOW()")
   ->execute([$scholarId]);

// Fetch scholar info
$scholar = $db->prepare("SELECT * FROM scholars WHERE id=?");
$scholar->execute([$scholarId]);
$scholar = $scholar->fetch();

// Unread notifications count
$unreadNotif = $db->prepare("SELECT COUNT(*) FROM notifications WHERE scholar_id=? AND is_read=0");
$unreadNotif->execute([$scholarId]);
$unreadCount = $unreadNotif->fetchColumn();

// Fetch announcements
$filter = sanitize($_GET['filter'] ?? '');
$where  = ['is_draft=0'];
$params = [];
if ($filter && $filter !== 'all') { $where[] = 'category=?'; $params[] = $filter; }
$whereStr = implode(' AND ', $where);
$announcements = $db->prepare("SELECT a.*, u.name AS author FROM announcements a LEFT JOIN admin_users u ON u.id=a.posted_by WHERE $whereStr ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 30");
$announcements->execute($params);
$announcements = $announcements->fetchAll();

// PGCEAP Status progress
$statusSteps = ['APPLICANT'=>0,'PENDING'=>1,'SCHOLAR'=>2,'GRADUATED'=>3];
$currentStep = $statusSteps[$scholar['pgceap_status']] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Feed — PGCEAP Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="scholar-layout">

<!-- ── Scholar Navbar ─────────────────────────────────────────── -->
<nav class="scholar-navbar">
    <div class="d-flex align-items-center gap-2 me-4">
        <div class="brand-icon" style="width:34px;height:34px;font-size:16px;"><i class="bi bi-mortarboard-fill"></i></div>
        <span style="font-weight:800;font-size:14px;letter-spacing:.5px;">PGCEAP</span>
    </div>
    <a href="<?= BASE_URL ?>/scholar/feed.php" class="scholar-nav-link active"><i class="bi bi-newspaper"></i> Feed</a>
    <a href="<?= BASE_URL ?>/scholar/profile.php" class="scholar-nav-link"><i class="bi bi-person-fill"></i> Profile</a>
    <a href="<?= BASE_URL ?>/scholar/requirements.php" class="scholar-nav-link"><i class="bi bi-clipboard2-check-fill"></i> Requirements</a>
    <a href="<?= BASE_URL ?>/scholar/notifications.php" class="scholar-nav-link position-relative">
        <i class="bi bi-bell-fill"></i> Notifications
        <?php if ($unreadCount > 0): ?>
        <span class="position-absolute badge bg-danger" style="top:-4px;right:-4px;font-size:9px;padding:2px 5px;"><?= $unreadCount ?></span>
        <?php endif; ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <div class="d-none d-md-block">
            <div class="small fw-600"><?= htmlspecialchars($user['name']) ?></div>
            <div style="font-size:10px;color:var(--text-muted);"><?= htmlspecialchars($scholar['pgceap_status']??'') ?></div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<!-- ── Main Content ───────────────────────────────────────────── -->
<div class="scholar-content">
    <div class="row g-4">

        <!-- Left: Status Card + Filters -->
        <div class="col-lg-4 col-xl-3 scholar-sidebar-col">
            <!-- Scholar Status Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="user-avatar" style="width:48px;height:48px;font-size:18px;"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                        <div>
                            <div class="fw-700"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="small text-muted-pg"><?= htmlspecialchars($scholar['cert_num']??'No cert #') ?></div>
                        </div>
                    </div>

                    <!-- Status Progress -->
                    <div class="mb-3">
                        <div class="small text-muted-pg mb-2">PGCEAP Status</div>
                        <div class="status-stepper">
                            <?php
                            $steps = [['APPLICANT','bi-person-plus'],['SCHOLAR','bi-mortarboard-fill'],['GRADUATED','bi-award-fill']];
                            $step_keys = ['APPLICANT'=>0,'PENDING'=>0,'SCHOLAR'=>1,'GRADUATED'=>2,'DROPPED'=>99,'SUSPENDED'=>99];
                            $curIdx = $step_keys[$scholar['pgceap_status']??'APPLICANT'] ?? 0;
                            foreach ($steps as $i=>[$lbl,$ico]): ?>
                            <div class="status-step">
                                <div class="step-dot <?= $i<$curIdx?'done':($i===$curIdx?'active':'') ?>">
                                    <i class="bi <?= $ico ?>" style="font-size:11px;"></i>
                                </div>
                                <div class="step-label"><?= $lbl ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-2"><?= statusBadge($scholar['pgceap_status']??'APPLICANT') ?></div>
                    </div>

                    <hr style="border-color:var(--border);">
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted-pg">School</span>
                            <span class="fw-600 text-end" style="max-width:60%;"><?= htmlspecialchars($scholar['school']??'—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted-pg">Course</span>
                            <span class="fw-600 text-end" style="max-width:60%;"><?= htmlspecialchars($scholar['course']??'—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted-pg">TF Assistance</span>
                            <span class="fw-700 text-accent font-mono"><?= formatCurrency($scholar['tf_assistance_amount']??0) ?></span>
                        </div>
                        <?php if ($scholar['paid']): ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted-pg">Last Paid</span>
                            <span class="fw-600 text-success"><?= formatCurrency($scholar['paid_amount']??0) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Category Filters -->
            <div class="card">
                <div class="card-header"><i class="bi bi-funnel-fill"></i> Filter</div>
                <div class="card-body p-2 filter-pill-body">
                    <?php foreach (['all'=>'All Announcements','general'=>'General','urgent'=>'Urgent','reminder'=>'Reminders','event'=>'Events'] as $v=>$l): ?>
                    <a href="?filter=<?=$v?>" class="nav-item <?= ($filter?:'all')===$v?'active':'' ?> mb-1">
                        <i class="bi bi-<?= ['all'=>'grid','general'=>'info-circle','urgent'=>'exclamation-triangle','reminder'=>'alarm','event'=>'calendar-event'][$v] ?>"></i>
                        <?= $l ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Announcements Feed -->
        <div class="col-lg-8 col-xl-9">
            <h5 class="fw-700 mb-3">
                <i class="bi bi-newspaper me-2 text-accent"></i>
                <?= $filter && $filter!=='all' ? ucfirst($filter).' Announcements' : 'All Announcements' ?>
                <span class="badge bg-secondary ms-2"><?= count($announcements) ?></span>
            </h5>

            <?php foreach ($announcements as $a): ?>
            <div class="announcement-card <?= $a['category'] ?> <?= $a['is_pinned']?'pinned':'' ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="ann-category <?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
                    <?php if ($a['is_pinned']): ?><span class="badge bg-primary"><i class="bi bi-pin-fill me-1"></i>Pinned</span><?php endif; ?>
                </div>
                <h5 class="ann-title"><?= htmlspecialchars($a['title']) ?></h5>
                <p class="ann-body"><?= nl2br(htmlspecialchars($a['body'])) ?></p>
                <div class="ann-meta">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($a['author']??'PGCEAP Admin') ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-clock me-1"></i><?= formatDate($a['created_at']) ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <i class="bi bi-newspaper"></i>
                <p>No announcements yet. Check back later.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
