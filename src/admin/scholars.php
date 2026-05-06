<?php
// ============================================================
// PGCEAP Portal — Scholar Management
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');

$pageTitle = 'Scholars';
$db = getDB();
$user = getCurrentUser();
$msg = '';
$msgType = 'success';

// ── Handle POST Actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        requirePermission('manage_scholars');
        $id        = $_POST['id'] ?? generateUUID();
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $status    = sanitize($_POST['pgceap_status'] ?? 'APPLICANT');
        $school    = sanitize($_POST['school'] ?? '');
        $schoolType= sanitize($_POST['school_type'] ?? '');
        $course    = sanitize($_POST['course'] ?? '');
        $yearLevel = intval($_POST['year_level'] ?? 0);
        $schoolYear= sanitize($_POST['school_year'] ?? '');
        $semester  = intval($_POST['semester'] ?? 1);
        $municipality = sanitize($_POST['municipality'] ?? '');
        $barangay  = sanitize($_POST['barangay'] ?? '');
        $tfAmount  = floatval($_POST['tf_assistance_amount'] ?? 0);

        if (!$firstName || !$lastName || !$email) {
            $msg = 'Name and email are required.'; $msgType = 'danger';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO scholars (id, first_name, last_name, email, pgceap_status, school, school_type, course, year_level, school_year, semester, municipality, barangay, tf_assistance_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$id, $firstName, $lastName, $email, $status, $school, $schoolType, $course, $yearLevel, $schoolYear, $semester, $municipality, $barangay, $tfAmount]);
                    $msg = "Scholar {$firstName} {$lastName} added successfully.";
                } else {
                    $stmt = $db->prepare("UPDATE scholars SET first_name=?,last_name=?,email=?,pgceap_status=?,school=?,school_type=?,course=?,year_level=?,school_year=?,semester=?,municipality=?,barangay=?,tf_assistance_amount=?,updated_at=NOW() WHERE id=?");
                    $stmt->execute([$firstName, $lastName, $email, $status, $school, $schoolType, $course, $yearLevel, $schoolYear, $semester, $municipality, $barangay, $tfAmount, $_POST['id']]);
                    $msg = "Scholar updated.";
                }
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') $msg = 'Email already exists.';
                else $msg = 'Error: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }

    if ($action === 'delete') {
        requirePermission('manage_scholars');
        $db->prepare("DELETE FROM scholars WHERE id = ?")->execute([$_POST['id']]);
        $msg = 'Scholar removed.';
    }

    if ($action === 'invite') {
        requirePermission('manage_scholars');
        $scholarId = $_POST['scholar_id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM scholars WHERE id = ?");
        $stmt->execute([$scholarId]);
        $scholar = $stmt->fetch();
        if ($scholar) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));
            $db->prepare("INSERT INTO auth_invites (id, scholar_id, email, token_hash, expires_at) VALUES (UUID(), ?, ?, ?, ?) ON DUPLICATE KEY UPDATE token_hash=?, expires_at=?, used_at=NULL")
               ->execute([$scholarId, $scholar['email'], $hash, $expires, $hash, $expires]);
            $db->prepare("UPDATE scholars SET invited_at = NOW() WHERE id = ?")->execute([$scholarId]);
            $inviteUrl = BASE_URL . '/set-password.php?token=' . $token;
            $msg = "Invite link generated: <a href='{$inviteUrl}' target='_blank' class='text-accent'>{$inviteUrl}</a> (valid 72h)";
        }
    }
}

// ── Filters & Pagination ───────────────────────────────────────
$search    = sanitize($_GET['q'] ?? '');
$statusF   = sanitize($_GET['status'] ?? '');
$schoolF   = sanitize($_GET['school'] ?? '');
$page      = max(1, intval($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search)  { $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR cert_num LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($statusF) { $where[] = "pgceap_status = ?"; $params[] = $statusF; }
if ($schoolF) { $where[] = "school LIKE ?"; $params[] = "%$schoolF%"; }
if ($user['role'] === 'SCHOOL_ADMIN' && $user['school']) { $where[] = "school = ?"; $params[] = $user['school']; }

$whereStr = implode(' AND ', $where);
$total = $db->prepare("SELECT COUNT(*) FROM scholars WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("SELECT * FROM scholars WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$scholars = $stmt->fetchAll();

// For edit modal
$editScholar = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM scholars WHERE id = ?");
    $s->execute([$_GET['edit']]);
    $editScholar = $s->fetch();
}

$schools = $db->query("SELECT DISTINCT school FROM scholars WHERE school IS NOT NULL ORDER BY school")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible alert-auto-dismiss fade show mb-4">
    <?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Toolbar ─────────────────────────────────────────────────── -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-people-fill"></i> Scholar Records <span class="badge bg-primary ms-2"><?= number_format($totalRows) ?></span></div>
        <?php if (hasPermission('manage_scholars')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scholarModal">
            <i class="bi bi-person-plus me-1"></i> Add Scholar
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="p-3 border-bottom" style="border-color:var(--border)!important;">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, cert#...">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <?php foreach (['APPLICANT','PENDING','SCHOLAR','GRADUATED','DROPPED','SUSPENDED'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="school" value="<?= htmlspecialchars($schoolF) ?>" placeholder="Filter by school...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/admin/scholars.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Cert #</th>
                    <th>Scholar</th>
                    <th>School</th>
                    <th>Course</th>
                    <th>Yr/Sem</th>
                    <th>Status</th>
                    <th>TF Assist.</th>
                    <th>Billed</th>
                    <th>Paid</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scholars as $s): ?>
                <tr>
                    <td class="font-mono small"><?= htmlspecialchars($s['cert_num'] ?? '—') ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="small text-muted-pg"><?= htmlspecialchars($s['email']) ?></div>
                    </td>
                    <td>
                        <div class="small"><?= htmlspecialchars($s['school'] ?? '—') ?></div>
                        <?php if ($s['school_type']): ?><span class="badge bg-secondary"><?= $s['school_type'] ?></span><?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($s['course'] ?? '—') ?></td>
                    <td class="small text-center"><?= $s['year_level'] ?? '—' ?> / <?= $s['semester'] ?? '—' ?></td>
                    <td><?= statusBadge($s['pgceap_status']) ?></td>
                    <td class="font-mono small"><?= formatCurrency($s['tf_assistance_amount'] ?? 0) ?></td>
                    <td>
                        <?php if ($s['billed']): ?>
                            <span class="badge bg-info">YES</span>
                            <div class="small text-muted-pg font-mono"><?= htmlspecialchars($s['bill_ref'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="badge bg-secondary">NO</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['paid']): ?>
                            <span class="badge bg-success">YES</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">NO</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (hasPermission('manage_scholars')): ?>
                            <a href="?edit=<?= $s['id'] ?>" class="btn btn-xs btn-ghost" data-bs-toggle="tooltip" title="Edit">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="invite">
                                <input type="hidden" name="scholar_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" data-bs-toggle="tooltip" title="Send Invite">
                                    <i class="bi bi-envelope-fill text-accent"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" data-confirm="Remove this scholar?" data-bs-toggle="tooltip" title="Delete">
                                    <i class="bi bi-trash-fill text-danger"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($scholars)): ?>
                <tr><td colspan="10"><div class="empty-state"><i class="bi bi-people"></i><p>No scholars found.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="border-color:var(--border)!important;">
        <small class="text-muted-pg">Showing <?= min($offset+1,$totalRows) ?>–<?= min($offset+$perPage,$totalRows) ?> of <?= $totalRows ?></small>
        <nav><ul class="pagination mb-0">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?=$i?>&q=<?=urlencode($search)?>&status=<?=$statusF?>&school=<?=urlencode($schoolF)?>"><?=$i?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ── Add/Edit Scholar Modal ──────────────────────────────────── -->
<div class="modal fade" id="scholarModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-fill me-2"></i><?= $editScholar ? 'Edit Scholar' : 'Add New Scholar' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editScholar ? 'edit' : 'add' ?>">
                <?php if ($editScholar): ?>
                <input type="hidden" name="id" value="<?= $editScholar['id'] ?>">
                <?php endif; ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required value="<?= htmlspecialchars($editScholar['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" value="<?= htmlspecialchars($editScholar['middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required value="<?= htmlspecialchars($editScholar['last_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($editScholar['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PGCEAP Status</label>
                            <select class="form-select" name="pgceap_status">
                                <?php foreach (['APPLICANT','PENDING','SCHOLAR','GRADUATED','DROPPED','SUSPENDED'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($editScholar['pgceap_status'] ?? 'APPLICANT') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">School</label>
                            <input type="text" class="form-control" name="school" list="schoolList" value="<?= htmlspecialchars($editScholar['school'] ?? '') ?>">
                            <datalist id="schoolList">
                                <?php foreach ($schools as $sch): ?><option value="<?= htmlspecialchars($sch) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">School Type</label>
                            <select class="form-select" name="school_type">
                                <option value="">— Select —</option>
                                <option value="Public" <?= ($editScholar['school_type'] ?? '') === 'Public' ? 'selected' : '' ?>>Public</option>
                                <option value="Private" <?= ($editScholar['school_type'] ?? '') === 'Private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" name="course" value="<?= htmlspecialchars($editScholar['course'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level">
                                <?php for ($y=1; $y<=6; $y++): ?>
                                <option value="<?=$y?>" <?= ($editScholar['year_level'] ?? 1) == $y ? 'selected' : '' ?>><?=$y?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="semester">
                                <option value="1" <?= ($editScholar['semester'] ?? 1) == 1 ? 'selected' : '' ?>>1st</option>
                                <option value="2" <?= ($editScholar['semester'] ?? 1) == 2 ? 'selected' : '' ?>>2nd</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">School Year</label>
                            <input type="text" class="form-control" name="school_year" placeholder="2024-2025" value="<?= htmlspecialchars($editScholar['school_year'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Municipality</label>
                            <input type="text" class="form-control" name="municipality" value="<?= htmlspecialchars($editScholar['municipality'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Barangay</label>
                            <input type="text" class="form-control" name="barangay" value="<?= htmlspecialchars($editScholar['barangay'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">TF Assistance Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" name="tf_assistance_amount" value="<?= $editScholar['tf_assistance_amount'] ?? 0 ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Scholar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editScholar): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('scholarModal')).show();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
