<?php
// ============================================================
// PGCEAP Portal — Requirements / Activity Submissions Review
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$pageTitle = 'Requirements';
$db = getDB();
$msg = '';
$msgType = 'success';
$activeTab = $_GET['tab'] ?? 'submissions';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'review_submission') {
        $submId  = sanitize($_POST['submission_id'] ?? '');
        $verdict = sanitize($_POST['verdict'] ?? '');
        $reason  = sanitize($_POST['reason'] ?? '');
        $status  = $verdict === 'approve' ? 'APPROVED' : 'REJECTED';
        $db->prepare("UPDATE submissions SET status=?, rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$status, $reason, $_SESSION['user_id'], $submId]);
        // Notify scholar
        $sub = $db->prepare("SELECT s.*, a.title as activity_title FROM submissions s JOIN activities a ON a.id=s.activity_id WHERE s.id=?");
        $sub->execute([$submId]);
        $sub = $sub->fetch();
        if ($sub) {
            $db->prepare("INSERT INTO notifications (id,scholar_id,type,title,message) VALUES (UUID(),?,?,?,?)")
               ->execute([$sub['scholar_id'], $status==='APPROVED'?'approved':'rejected', 'Activity Submission '.ucfirst(strtolower($status)), 'Your submission for "'.$sub['activity_title'].'" has been '.strtolower($status).'.'.($reason?" Reason: $reason":'')]);
        }
        $msg = "Submission $status."; $msgType = $status==='APPROVED'?'success':'warning';
    }

    if ($action === 'add_activity') {
        $title   = sanitize($_POST['title'] ?? '');
        $desc    = sanitize($_POST['description'] ?? '');
        $date    = $_POST['activity_date'] ?? null;
        $deadline= $_POST['deadline'] ?? null;
        $sy      = sanitize($_POST['school_year'] ?? '');
        $sem     = intval($_POST['semester'] ?? 1);
        if ($title) {
            $db->prepare("INSERT INTO activities (id,title,description,activity_date,deadline,school_year,semester,created_by) VALUES (UUID(),?,?,?,?,?,?,?)")
               ->execute([$title,$desc,$date?:null,$deadline?:null,$sy,$sem,$_SESSION['user_id']]);
            $msg = "Activity '$title' created.";
        }
    }

    if ($action === 'toggle_activity') {
        $db->prepare("UPDATE activities SET is_active = NOT is_active WHERE id=?")->execute([$_POST['activity_id']]);
        $msg = 'Activity status toggled.';
    }
}

// ── Submissions Tab ───────────────────────────────────────────
$statusF  = sanitize($_GET['status'] ?? 'PENDING');
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

$swhere = ['1=1'];
$sparams = [];
if ($statusF !== '') { $swhere[] = "s.status=?"; $sparams[] = $statusF; }
if ($_SESSION['role'] === 'SCHOOL_ADMIN' && !empty($_SESSION['school_assignment'])) {
    $swhere[] = "sc.school=?"; $sparams[] = $_SESSION['school_assignment'];
}
$swhereStr = implode(' AND ',$swhere);

$stotal = $db->prepare("SELECT COUNT(*) FROM submissions s JOIN scholars sc ON sc.id=s.scholar_id WHERE $swhereStr");
$stotal->execute($sparams);
$totalRows = $stotal->fetchColumn();
$totalPages = ceil($totalRows/$perPage);

$sstmt = $db->prepare("SELECT s.*, a.title AS activity_title, a.activity_date, sc.first_name, sc.last_name, sc.school FROM submissions s JOIN activities a ON a.id=s.activity_id JOIN scholars sc ON sc.id=s.scholar_id WHERE $swhereStr ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset");
$sstmt->execute($sparams);
$submissions = $sstmt->fetchAll();

// ── Activities Tab ────────────────────────────────────────────
$activities = $db->query("SELECT a.*, u.name AS creator, (SELECT COUNT(*) FROM submissions WHERE activity_id=a.id) as sub_count, (SELECT COUNT(*) FROM submissions WHERE activity_id=a.id AND status='PENDING') as pending_count FROM activities a LEFT JOIN admin_users u ON u.id=a.created_by ORDER BY a.created_at DESC")->fetchAll();

// Pending counts
$pendingSubm = $db->query("SELECT COUNT(*) FROM submissions WHERE status='PENDING'")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Tabs ───────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-4" style="border-color:var(--border);">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab==='submissions'?'active':'' ?>" href="?tab=submissions" style="color:var(--text-secondary);border-color:transparent;">
            <i class="bi bi-clipboard2-check me-1"></i> Submissions
            <?php if ($pendingSubm>0): ?><span class="badge bg-warning ms-1"><?= $pendingSubm ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab==='activities'?'active':'' ?>" href="?tab=activities" style="color:var(--text-secondary);border-color:transparent;">
            <i class="bi bi-calendar-event me-1"></i> Manage Activities
        </a>
    </li>
</ul>

<?php if ($activeTab === 'submissions'): ?>

<!-- ── Submissions Review ─────────────────────────────────────── -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-clipboard2-check-fill"></i> Activity Submissions</div>
        <div class="d-flex gap-2">
            <?php foreach ([''=>'All','PENDING'=>'Pending','APPROVED'=>'Approved','REJECTED'=>'Rejected'] as $v=>$l): ?>
            <a href="?tab=submissions&status=<?=$v?>" class="btn btn-sm <?= $statusF===$v?'btn-primary':'btn-outline-secondary' ?>"><?=$l?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Scholar</th><th>Activity</th><th>Date</th><th>Notes</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($submissions as $s): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                        <div class="small text-muted-pg"><?= htmlspecialchars($s['school'] ?? '') ?></div>
                    </td>
                    <td>
                        <div class="fw-600 small"><?= htmlspecialchars($s['activity_title']) ?></div>
                        <div class="small text-muted-pg"><?= $s['activity_date'] ? formatDate($s['activity_date']) : '' ?></div>
                    </td>
                    <td class="small text-muted-pg"><?= formatDate($s['submitted_at']) ?></td>
                    <td class="small"><?= htmlspecialchars(substr($s['notes'] ?? '',0,60)) ?><?= strlen($s['notes']??'')>60?'…':'' ?></td>
                    <td><?= statusBadge($s['status']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($s['file_path']): ?>
                            <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($s['file_path']) ?>" target="_blank" class="btn btn-xs btn-ghost">
                                <i class="bi bi-eye text-accent"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($s['status']==='PENDING'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="review_submission">
                                <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="verdict" value="approve">
                                <button type="submit" class="btn btn-xs btn-success" data-confirm="Approve this submission?"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <button type="button" class="btn btn-xs btn-danger" onclick="openRejectModal('<?= $s['id'] ?>')">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($submissions)): ?>
                <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-clipboard2-x"></i><p>No submissions found.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages>1): ?>
    <div class="d-flex justify-content-center py-3 border-top" style="border-color:var(--border)!important;">
        <nav><ul class="pagination mb-0">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?tab=submissions&page=<?=$i?>&status=<?=$statusF?>"><?=$i?></a></li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ── Activities Management ──────────────────────────────────── -->

<div class="d-flex justify-content-end mb-3">
    <?php if (hasPermission('manage_scholars')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#activityModal">
        <i class="bi bi-plus-lg me-1"></i> Add Activity
    </button>
    <?php endif; ?>
</div>

<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-calendar-event-fill"></i> Activities</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Activity</th><th>Date</th><th>Deadline</th><th>Submissions</th><th>Pending</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($activities as $a): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($a['title']) ?></div>
                        <div class="small text-muted-pg"><?= htmlspecialchars(substr($a['description']??'',0,60)) ?></div>
                    </td>
                    <td class="small"><?= $a['activity_date']?formatDate($a['activity_date']):'—' ?></td>
                    <td class="small"><?= $a['deadline']?formatDate($a['deadline']):'—' ?></td>
                    <td><?= $a['sub_count'] ?></td>
                    <td><?php if ($a['pending_count']>0): ?><span class="badge bg-warning"><?= $a['pending_count'] ?></span><?php else: ?>—<?php endif; ?></td>
                    <td><span class="badge <?= $a['is_active']?'bg-success':'bg-secondary' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle_activity">
                            <input type="hidden" name="activity_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-ghost">
                                <i class="bi bi-toggle-<?= $a['is_active']?'on text-success':'off text-muted' ?>"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($activities)): ?>
                <tr><td colspan="7"><div class="empty-state py-4"><i class="bi bi-calendar-x"></i><p>No activities yet.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>New Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_activity">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" required placeholder="Activity name...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Brief description..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Activity Date</label>
                            <input type="date" class="form-control" name="activity_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Submission Deadline</label>
                            <input type="date" class="form-control" name="deadline">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">School Year</label>
                            <input type="text" class="form-control" name="school_year" placeholder="2024-2025">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="semester">
                                <option value="1">1st</option>
                                <option value="2">2nd</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reject Submission Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle-fill text-danger me-2"></i>Reject Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="review_submission">
                <input type="hidden" name="verdict" value="reject">
                <input type="hidden" name="submission_id" id="rejectSubmId">
                <div class="modal-body">
                    <label class="form-label">Reason for Rejection</label>
                    <textarea class="form-control" name="reason" rows="3" placeholder="Explain the rejection reason..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectSubmId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
