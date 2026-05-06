<?php
// ============================================================
// PGCEAP Portal — Scholar Profile
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('scholar');

$db  = getDB();
$user= getCurrentUser();
$scholarId = $user['id'];
$msg = '';
$msgType = 'success';

// Fetch scholar
$s = $db->prepare("SELECT * FROM scholars WHERE id=?");
$s->execute([$scholarId]);
$scholar = $s->fetch();

// ── Handle profile update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact  = sanitize($_POST['contact_number'] ?? '');
    $address  = sanitize($_POST['address_full'] ?? '');
    $barangay = sanitize($_POST['barangay'] ?? '');
    $muni     = sanitize($_POST['municipality'] ?? '');

    $db->prepare("UPDATE scholars SET contact_number=?,address_full=?,barangay=?,municipality=?,updated_at=NOW() WHERE id=?")
       ->execute([$contact,$address,$barangay,$muni,$scholarId]);
    $msg = 'Profile updated.';
    // Refresh
    $s->execute([$scholarId]);
    $scholar = $s->fetch();
}

// Unread notif count
$un = $db->prepare("SELECT COUNT(*) FROM notifications WHERE scholar_id=? AND is_read=0");
$un->execute([$scholarId]);
$unreadCount = $un->fetchColumn();

// Fetch audit trail
$audits = $db->prepare("SELECT * FROM scholar_audit_logs WHERE scholar_id=? ORDER BY created_at DESC LIMIT 10");
$audits->execute([$scholarId]);
$audits = $audits->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — PGCEAP Portal</title>
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
    <a href="<?= BASE_URL ?>/scholar/profile.php" class="scholar-nav-link active"><i class="bi bi-person-fill"></i> Profile</a>
    <a href="<?= BASE_URL ?>/scholar/requirements.php" class="scholar-nav-link"><i class="bi bi-clipboard2-check-fill"></i> Requirements</a>
    <a href="<?= BASE_URL ?>/scholar/notifications.php" class="scholar-nav-link position-relative">
        <i class="bi bi-bell-fill"></i> Notifications
        <?php if ($unreadCount>0): ?><span class="position-absolute badge bg-danger" style="top:-4px;right:-4px;font-size:9px;"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="scholar-content">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
        <?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Profile Info -->
        <div class="col-md-4">
            <div class="card text-center p-4 mb-4">
                <div class="user-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:26px;">
                    <?= strtoupper(substr($scholar['first_name'],0,1)) ?>
                </div>
                <h5 class="fw-800 mb-1"><?= htmlspecialchars($scholar['first_name'].' '.($scholar['middle_name']?$scholar['middle_name'].' ':'').$scholar['last_name']) ?></h5>
                <div class="small text-muted-pg mb-2"><?= htmlspecialchars($scholar['email']) ?></div>
                <?= statusBadge($scholar['pgceap_status']) ?>
                <hr style="border-color:var(--border);">
                <div class="text-start small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-pg">Cert. #</span>
                        <span class="fw-600 font-mono"><?= htmlspecialchars($scholar['cert_num']??'Not assigned') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-pg">TF Assistance</span>
                        <span class="fw-700 text-accent"><?= formatCurrency($scholar['tf_assistance_amount']??0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-pg">Billed</span>
                        <span><?= $scholar['billed']?'<span class="text-success fw-600">YES</span>':'<span class="text-muted-pg">NO</span>' ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted-pg">Paid</span>
                        <span><?= $scholar['paid']?'<span class="text-success fw-600">'.formatCurrency($scholar['paid_amount']??0).'</span>':'<span class="text-muted-pg">NO</span>' ?></span>
                    </div>
                </div>
            </div>

            <!-- Academic Info (read-only) -->
            <div class="card">
                <div class="card-header"><i class="bi bi-building"></i> Academic Info</div>
                <div class="card-body small">
                    <?php $fields=[['School',$scholar['school']??'—'],['School Type',$scholar['school_type']??'—'],['Course',$scholar['course']??'—'],['Year Level',$scholar['year_level']??'—'],['School Year',$scholar['school_year']??'—'],['Semester',$scholar['semester']??'—']]; ?>
                    <?php foreach ($fields as [$label,$val]): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-pg"><?= $label ?></span>
                        <span class="fw-600"><?= htmlspecialchars((string)$val) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Editable Fields + Audit -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-pencil-fill"></i> Update Contact Info</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" value="<?= htmlspecialchars($scholar['contact_number']??'') ?>" placeholder="09XX-XXX-XXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Municipality</label>
                                <input type="text" class="form-control" name="municipality" value="<?= htmlspecialchars($scholar['municipality']??'') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Barangay</label>
                                <input type="text" class="form-control" name="barangay" value="<?= htmlspecialchars($scholar['barangay']??'') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full Address</label>
                                <input type="text" class="form-control" name="address_full" value="<?= htmlspecialchars($scholar['address_full']??'') ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Log -->
            <?php if (!empty($audits)): ?>
            <div class="card">
                <div class="card-header"><i class="bi bi-journal-text"></i> Profile Change History</div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Action</th><th>By</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($audits as $a): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= str_replace('_',' ',$a['action']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($a['editor_name']??'System') ?></td>
                                <td class="small text-muted-pg"><?= formatDate($a['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
