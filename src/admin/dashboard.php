<?php
// ============================================================
// PGCEAP Portal — Admin Dashboard
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$pageTitle = 'Dashboard';
$db = getDB();
$user = getCurrentUser();

// ── Stats ─────────────────────────────────────────────────────
$schoolFilter = '';
$params = [];
if ($user['role'] === 'SCHOOL_ADMIN' && $user['school']) {
    $schoolFilter = " WHERE school = ?";
    $params[] = $user['school'];
}

$totalScholars = $db->prepare("SELECT COUNT(*) FROM scholars WHERE pgceap_status = 'SCHOLAR'" . ($schoolFilter ? str_replace('WHERE', 'AND', $schoolFilter) : ''));
// Simplified stats queries
$stats = [];

$q = $db->query("SELECT 
    SUM(pgceap_status = 'SCHOLAR')   AS scholars,
    SUM(pgceap_status = 'APPLICANT') AS applicants,
    SUM(pgceap_status = 'GRADUATED') AS graduated,
    SUM(paid = 1) AS paid_count,
    SUM(billed = 1 AND paid = 0) AS pending_payroll
    FROM scholars");
$stats = $q->fetch() ?: [];

$pendingDocs = $db->query("SELECT COUNT(*) as cnt FROM scholar_documents WHERE status = 'PENDING'")->fetch()['cnt'] ?? 0;
$pendingSubm = $db->query("SELECT COUNT(*) as cnt FROM submissions WHERE status = 'PENDING'")->fetch()['cnt'] ?? 0;

// Recent scholars
$recentScholars = $db->query(
    "SELECT id, CONCAT(first_name,' ',last_name) AS name, email, school, pgceap_status, created_at
     FROM scholars ORDER BY created_at DESC LIMIT 6"
)->fetchAll();

// Recent announcements
$announcements = $db->query(
    "SELECT a.*, u.name AS author FROM announcements a
     LEFT JOIN admin_users u ON u.id = a.posted_by
     WHERE a.is_draft = 0 ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 4"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- ── Stats Grid ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-mortarboard-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['scholars'] ?? 0) ?></div>
                <div class="stat-label">Active Scholars</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-person-plus-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['applicants'] ?? 0) ?></div>
                <div class="stat-label">Applicants</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-award-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['graduated'] ?? 0) ?></div>
                <div class="stat-label">Graduated</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-file-earmark-text"></i></div>
            <div>
                <div class="stat-value"><?= number_format($pendingDocs) ?></div>
                <div class="stat-label">Pending Docs</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon indigo"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['pending_payroll'] ?? 0) ?></div>
                <div class="stat-label">Pending Payroll</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['paid_count'] ?? 0) ?></div>
                <div class="stat-label">Disbursed</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Quick Actions ──────────────────────────────────────────── -->
<?php if (hasPermission('manage_scholars')): ?>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-lightning-charge-fill"></i> Quick Actions</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/admin/scholars.php?action=add" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Add Scholar
            </a>
            <?php if (hasPermission('manage_billing')): ?>
            <a href="<?= BASE_URL ?>/admin/billing.php" class="btn btn-warning">
                <i class="bi bi-receipt-cutoff me-1"></i> Billing
            </a>
            <?php endif; ?>
            <?php if (hasPermission('manage_payroll')): ?>
            <a href="<?= BASE_URL ?>/admin/payroll.php" class="btn btn-success">
                <i class="bi bi-cash-coin me-1"></i> Payroll
            </a>
            <?php endif; ?>
            <?php if (hasPermission('manage_announcements')): ?>
            <a href="<?= BASE_URL ?>/admin/announcements.php?action=add" class="btn btn-outline-secondary">
                <i class="bi bi-megaphone me-1"></i> New Announcement
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/document-review.php" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-check me-1"></i>
                Review Documents <?php if ($pendingDocs > 0): ?><span class="badge bg-danger ms-1"><?= $pendingDocs ?></span><?php endif; ?>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Main Content Row ──────────────────────────────────────── -->
<div class="row g-4">
    <!-- Recent Scholars -->
    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="table-toolbar">
                <div class="table-title"><i class="bi bi-people-fill"></i> Recent Scholars</div>
                <a href="<?= BASE_URL ?>/admin/scholars.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>School</th>
                            <th>Status</th>
                            <th>Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentScholars as $s): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/scholars.php?view=<?= $s['id'] ?>" class="fw-600 text-primary">
                                    <?= htmlspecialchars($s['name']) ?>
                                </a>
                                <div class="small text-muted-pg"><?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($s['school'] ?? '—') ?></td>
                            <td><?= statusBadge($s['pgceap_status']) ?></td>
                            <td class="text-muted-pg"><?= formatDate($s['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentScholars)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted-pg">No scholars yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Announcements + Pending -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-megaphone-fill"></i> Announcements</div>
            <div class="card-body p-0">
                <?php foreach ($announcements as $a): ?>
                <div class="px-4 py-3 border-bottom" style="border-color:var(--border)!important;">
                    <div class="d-flex align-items-start gap-2">
                        <?php if ($a['is_pinned']): ?><i class="bi bi-pin-fill text-accent small mt-1"></i><?php endif; ?>
                        <div>
                            <div style="color: white;" class="fw-600 small"><?= htmlspecialchars($a['title']) ?></div>
                            <div class="text-muted-pg" style="font-size:11px;"><?= formatDate($a['created_at']) ?></div>
                        </div>
                        <span class="ann-category <?= $a['category'] ?> ms-auto"><?= ucfirst($a['category']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($announcements)): ?>
                <div class="empty-state py-4"><i class="bi bi-megaphone"></i><p>No announcements</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Tasks -->
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-fill"></i> Pending Reviews</div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="fw-600" style="color: white;">Document Review</div>
                        <div class="small text-muted-pg">COG, Registration Forms</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning"><?= $pendingDocs ?> pending</span>
                        <a href="<?= BASE_URL ?>/admin/document-review.php" class="btn btn-xs btn-outline-secondary">Go</a>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-600" style="color: white;">Activity Submissions</div>
                        <div class="small text-muted-pg">Activity proof reviews</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning"><?= $pendingSubm ?> pending</span>
                        <a href="<?= BASE_URL ?>/admin/requirements.php" class="btn btn-xs btn-outline-secondary">Go</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
