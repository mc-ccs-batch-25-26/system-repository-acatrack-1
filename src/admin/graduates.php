<?php
// ============================================================
// PGCEAP Portal — Graduates & Achievers
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$pageTitle = 'Graduates & Achievers';
$db = getDB();
$user = getCurrentUser();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_graduate') {
        requirePermission('submit_achiever_records');
        $scholarId   = sanitize($_POST['scholar_id'] ?? '');
        $gradDate    = $_POST['graduation_date'] ?? null;
        $honors      = sanitize($_POST['honors'] ?? '');

        // Verify scholar exists and is in submitter's school
        $s = $db->prepare("SELECT * FROM scholars WHERE id=?");
        $s->execute([$scholarId]);
        $sch = $s->fetch();

        if (!$sch) {
            $msg = 'Scholar not found.'; $msgType = 'danger';
        } else {
            $db->prepare("INSERT INTO graduates (id, scholar_id, school, graduation_date, honors, submitted_by) VALUES (UUID(),?,?,?,?,?)")
               ->execute([$scholarId, $sch['school']??'', $gradDate?:null, $honors?:null, $_SESSION['user_id']]);
            // Update scholar status
            $db->prepare("UPDATE scholars SET pgceap_status='GRADUATED', updated_at=NOW() WHERE id=?")->execute([$scholarId]);
            $msg = 'Graduate record submitted.';
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM graduates WHERE id=?")->execute([$_POST['id']]);
        $msg = 'Record deleted.';
    }
}

// Fetch graduates
$where = ['1=1'];
$params = [];
if ($user['role'] === 'SCHOOL_ADMIN' && $user['school']) {
    $where[] = "g.school = ?"; $params[] = $user['school'];
}
$whereStr = implode(' AND ', $where);
$graduates = $db->prepare("SELECT g.*, CONCAT(s.first_name,' ',s.last_name) AS scholar_name, s.course, u.name AS submitted_by_name FROM graduates g JOIN scholars s ON s.id=g.scholar_id LEFT JOIN admin_users u ON u.id=g.submitted_by WHERE $whereStr ORDER BY g.created_at DESC");
$graduates->execute($params);
$graduates = $graduates->fetchAll();

// Scholars eligible (status=SCHOLAR, not yet in graduates)
$eligibleWhere = ["pgceap_status='SCHOLAR'"];
$eligibleParams = [];
if ($user['role'] === 'SCHOOL_ADMIN' && $user['school']) {
    $eligibleWhere[] = "school=?"; $eligibleParams[] = $user['school'];
}
$eligibleStr = implode(' AND ', $eligibleWhere);
$eligible = $db->prepare("SELECT id, CONCAT(first_name,' ',last_name) as name, school, course FROM scholars WHERE $eligibleStr AND id NOT IN (SELECT scholar_id FROM graduates) ORDER BY last_name");
$eligible->execute($eligibleParams);
$eligible = $eligible->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-award-fill"></i> Submit Graduate</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_graduate">
                    <div class="mb-3">
                        <label class="form-label">Scholar *</label>
                        <select class="form-select" name="scholar_id" required>
                            <option value="">— Select Scholar —</option>
                            <?php foreach ($eligible as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> – <?= htmlspecialchars($e['school']??'') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($eligible)): ?>
                        <div class="form-text text-warning">No eligible scholars found.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Graduation Date</label>
                        <input type="date" class="form-control" name="graduation_date">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Latin Honors (if any)</label>
                        <select class="form-select" name="honors">
                            <option value="">None</option>
                            <option>Cum Laude</option>
                            <option>Magna Cum Laude</option>
                            <option>Summa Cum Laude</option>
                            <option>With Honors</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> Submit Graduate
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="col-md-8">
        <div class="table-card">
            <div class="table-toolbar">
                <div class="table-title"><i class="bi bi-mortarboard-fill"></i> Graduate Records <span class="badge bg-primary ms-2"><?= count($graduates) ?></span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Scholar</th><th>School</th><th>Graduation Date</th><th>Honors</th><th>Submitted By</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($graduates as $g): ?>
                        <tr>
                            <td>
                                <div class="fw-600"><?= htmlspecialchars($g['scholar_name']) ?></div>
                                <div class="small text-muted-pg"><?= htmlspecialchars($g['course']??'') ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars($g['school']) ?></td>
                            <td class="small"><?= $g['graduation_date']?formatDate($g['graduation_date']):'—' ?></td>
                            <td>
                                <?php if ($g['honors']): ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($g['honors']) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small text-muted-pg"><?= htmlspecialchars($g['submitted_by_name']??'System') ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-ghost" data-confirm="Remove this graduate record?">
                                        <i class="bi bi-trash-fill text-danger"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($graduates)): ?>
                        <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-mortarboard"></i><p>No graduate records yet.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
