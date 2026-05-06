<?php
// ============================================================
// PGCEAP Portal — Document Review
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$pageTitle = 'Document Review';
$db = getDB();
$msg = '';
$msgType = 'success';

// ── Handle Review Action ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId  = sanitize($_POST['doc_id'] ?? '');
    $action = sanitize($_POST['action'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');

    if ($action === 'approve') {
        $db->prepare("UPDATE scholar_documents SET status='APPROVED', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$_SESSION['user_id'], $docId]);
        // Notify scholar
        $doc = $db->prepare("SELECT * FROM scholar_documents WHERE id=?"); $doc->execute([$docId]);
        $d = $doc->fetch();
        if ($d) {
            $db->prepare("INSERT INTO notifications (id, scholar_id, type, title, message) VALUES (UUID(),?,'approved','Document Approved','Your ".htmlspecialchars($d['type'])." has been approved for SY ".$d['school_year']." Semester ".$d['semester'].".')")
               ->execute([$d['scholar_id']]);
        }
        $msg = 'Document approved.';
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE scholar_documents SET status='REJECTED', rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$reason, $_SESSION['user_id'], $docId]);
        $doc = $db->prepare("SELECT * FROM scholar_documents WHERE id=?"); $doc->execute([$docId]);
        $d = $doc->fetch();
        if ($d) {
            $db->prepare("INSERT INTO notifications (id, scholar_id, type, title, message) VALUES (UUID(),?,'rejected','Document Rejected','Your ".htmlspecialchars($d['type'])." was rejected. Reason: ".htmlspecialchars($reason)."')")
               ->execute([$d['scholar_id']]);
        }
        $msg = 'Document rejected.';
        $msgType = 'warning';
    }
}

// ── Filters ────────────────────────────────────────────────────
$statusF = sanitize($_GET['status'] ?? 'PENDING');
$typeF   = sanitize($_GET['type'] ?? '');
$syF     = sanitize($_GET['sy'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($statusF !== '') { $where[] = "d.status = ?"; $params[] = $statusF; }
if ($typeF)          { $where[] = "d.type = ?";   $params[] = $typeF; }
if ($syF)            { $where[] = "d.school_year = ?"; $params[] = $syF; }
if ($_SESSION['role'] === 'SCHOOL_ADMIN' && !empty($_SESSION['school_assignment'])) {
    $where[] = "s.school = ?"; $params[] = $_SESSION['school_assignment'];
}

$whereStr = implode(' AND ', $where);
$total = $db->prepare("SELECT COUNT(*) FROM scholar_documents d JOIN scholars s ON s.id = d.scholar_id WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("SELECT d.*, CONCAT(s.first_name,' ',s.last_name) AS scholar_name, s.school, s.email FROM scholar_documents d JOIN scholars s ON s.id = d.scholar_id WHERE $whereStr ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$docs = $stmt->fetchAll();

// Compliance stats
$complianceStats = $db->query("SELECT status, COUNT(*) as cnt FROM scholar_documents GROUP BY status")->fetchAll();
$statMap = array_column($complianceStats, 'cnt', 'status');

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-triangle' ?>-fill me-2"></i><?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Compliance Stats ────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-value"><?= $statMap['PENDING'] ?? 0 ?></div><div class="stat-label">Pending Review</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="stat-value"><?= $statMap['APPROVED'] ?? 0 ?></div><div class="stat-label">Approved</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <div><div class="stat-value"><?= $statMap['REJECTED'] ?? 0 ?></div><div class="stat-label">Rejected</div></div>
        </div>
    </div>
</div>

<!-- ── Main Table ─────────────────────────────────────────────── -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-file-earmark-check-fill"></i> Document Submissions</div>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select class="form-select form-select-sm" name="status" style="width:130px">
                <option value="">All Status</option>
                <?php foreach (['PENDING','APPROVED','REJECTED'] as $s): ?>
                <option value="<?=$s?>" <?= $statusF===$s?'selected':'' ?>><?=$s?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="type" style="width:170px">
                <option value="">All Types</option>
                <?php foreach (['COG','REGISTRATION_FORM','RECONSIDERATION'] as $t): ?>
                <option value="<?=$t?>" <?= $typeF===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control form-control-sm" name="sy" value="<?= htmlspecialchars($syF) ?>" placeholder="School Year" style="width:120px">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr><th>Scholar</th><th>School</th><th>Document Type</th><th>Period</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($d['scholar_name']) ?></div>
                        <div class="small text-muted-pg"><?= htmlspecialchars($d['email']) ?></div>
                    </td>
                    <td class="small"><?= htmlspecialchars($d['school'] ?? '—') ?></td>
                    <td>
                        <span class="badge bg-primary"><?= str_replace('_',' ', $d['type']) ?></span>
                    </td>
                    <td class="small"><?= htmlspecialchars($d['school_year']) ?> / Sem <?= $d['semester'] ?></td>
                    <td class="small text-muted-pg"><?= formatDate($d['created_at']) ?></td>
                    <td>
                        <?= statusBadge($d['status']) ?>
                        <?php if ($d['status'] === 'REJECTED' && $d['rejection_reason']): ?>
                        <div class="small text-danger mt-1"><?= htmlspecialchars($d['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($d['file_path']): ?>
                            <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-xs btn-ghost" title="View File">
                                <i class="bi bi-eye text-accent"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($d['status'] === 'PENDING'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-xs btn-success" data-confirm="Approve this document?">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                            </form>
                            <button type="button" class="btn btn-xs btn-danger"
                                onclick="openRejectModal('<?= $d['id'] ?>')">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($docs)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-earmark-x"></i><p>No documents found.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center py-3 border-top" style="border-color:var(--border)!important;">
        <nav><ul class="pagination mb-0">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?=$i?>&status=<?=$statusF?>&type=<?=$typeF?>&sy=<?=urlencode($syF)?>"><?=$i?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ── Reject Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle-fill text-danger me-2"></i>Reject Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="doc_id" id="rejectDocId">
                <div class="modal-body">
                    <label class="form-label">Rejection Reason *</label>
                    <textarea class="form-control" name="reason" rows="3" required placeholder="Explain why this document is being rejected..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Reject Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(docId) {
    document.getElementById('rejectDocId').value = docId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
