<?php
// ============================================================
// PGCEAP Portal — Announcements Management
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
requirePermission('manage_announcements');

$pageTitle = 'Announcements';
$db = getDB();
$msg = '';
$msgType = 'success';

// ── Handle POST Actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $title    = sanitize($_POST['title'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isDraft  = isset($_POST['is_draft']) ? 1 : 0;

        if (!$title || !$body) {
            $msg = 'Title and body are required.'; $msgType = 'danger';
        } else {
            if ($action === 'add') {
                $db->prepare("INSERT INTO announcements (id, title, body, category, is_pinned, is_draft, posted_by) VALUES (UUID(),?,?,?,?,?,?)")
                   ->execute([$title, $body, $category, $isPinned, $isDraft, $_SESSION['user_id']]);
                $msg = 'Announcement published successfully.';
            } else {
                $db->prepare("UPDATE announcements SET title=?,body=?,category=?,is_pinned=?,is_draft=?,updated_at=NOW() WHERE id=?")
                   ->execute([$title, $body, $category, $isPinned, $isDraft, $_POST['id']]);
                $msg = 'Announcement updated.';
            }
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$_POST['id']]);
        $msg = 'Announcement deleted.';
    }

    if ($action === 'toggle_pin') {
        $db->prepare("UPDATE announcements SET is_pinned = NOT is_pinned WHERE id=?")->execute([$_POST['id']]);
        $msg = 'Pin status updated.';
    }
}

// ── Fetch Data ─────────────────────────────────────────────────
$filter   = sanitize($_GET['filter'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($filter === 'pinned') { $where[] = 'is_pinned=1'; }
if ($filter === 'draft')  { $where[] = 'is_draft=1'; }
if ($filter === 'published') { $where[] = 'is_draft=0'; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM announcements WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("SELECT a.*, u.name AS author FROM announcements a LEFT JOIN admin_users u ON u.id=a.posted_by WHERE $whereStr ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

$editAnn = null;
if (isset($_GET['edit'])) {
    $e = $db->prepare("SELECT * FROM announcements WHERE id=?");
    $e->execute([$_GET['edit']]);
    $editAnn = $e->fetch();
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Toolbar ─────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex gap-2">
        <a href="?" class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline-secondary' ?>">All</a>
        <a href="?filter=published" class="btn btn-sm <?= $filter==='published'?'btn-primary':'btn-outline-secondary' ?>">Published</a>
        <a href="?filter=pinned" class="btn btn-sm <?= $filter==='pinned'?'btn-primary':'btn-outline-secondary' ?>">Pinned</a>
        <a href="?filter=draft" class="btn btn-sm <?= $filter==='draft'?'btn-primary':'btn-outline-secondary' ?>">Drafts</a>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annModal">
        <i class="bi bi-plus-lg me-1"></i> New Announcement
    </button>
</div>

<!-- ── Announcement Cards ─────────────────────────────────────── -->
<?php foreach ($announcements as $a): ?>
<div class="announcement-card <?= $a['category'] ?> <?= $a['is_pinned']?'pinned':'' ?> mb-3">
    <div class="d-flex align-items-start justify-content-between gap-3">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="ann-category <?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
                <?php if ($a['is_pinned']): ?><span class="badge bg-primary"><i class="bi bi-pin-fill me-1"></i>Pinned</span><?php endif; ?>
                <?php if ($a['is_draft']): ?><span class="badge bg-secondary">Draft</span><?php endif; ?>
            </div>
            <h5 class="ann-title"><?= htmlspecialchars($a['title']) ?></h5>
            <p class="ann-body"><?= nl2br(htmlspecialchars(substr($a['body'], 0, 200))) ?><?= strlen($a['body'])>200?'…':'' ?></p>
            <div class="ann-meta">
                <i class="bi bi-person me-1"></i><?= htmlspecialchars($a['author'] ?? 'System') ?>
                &nbsp;·&nbsp;
                <i class="bi bi-clock me-1"></i><?= formatDate($a['created_at']) ?>
            </div>
        </div>
        <div class="d-flex flex-column gap-1 flex-shrink-0">
            <a href="?edit=<?= $a['id'] ?>" class="btn btn-xs btn-ghost"><i class="bi bi-pencil-fill"></i></a>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="toggle_pin">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-xs btn-ghost" title="<?= $a['is_pinned']?'Unpin':'Pin' ?>">
                    <i class="bi bi-pin<?= $a['is_pinned']?'-fill':'' ?> text-accent"></i>
                </button>
            </form>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-xs btn-ghost" data-confirm="Delete this announcement?">
                    <i class="bi bi-trash-fill text-danger"></i>
                </button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($announcements)): ?>
<div class="empty-state mt-5">
    <i class="bi bi-megaphone"></i>
    <p>No announcements yet. Create one to get started.</p>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <nav><ul class="pagination mb-0">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?=$i?>&filter=<?=$filter?>"><?=$i?></a>
        </li>
        <?php endfor; ?>
    </ul></nav>
</div>
<?php endif; ?>

<!-- ── Add/Edit Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="annModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone-fill me-2"></i><?= $editAnn ? 'Edit Announcement' : 'New Announcement' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editAnn ? 'edit' : 'add' ?>">
                <?php if ($editAnn): ?><input type="hidden" name="id" value="<?= $editAnn['id'] ?>"><?php endif; ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($editAnn['title'] ?? '') ?>" placeholder="Announcement headline...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Body *</label>
                        <textarea class="form-control" name="body" rows="5" required placeholder="Write your announcement here..."><?= htmlspecialchars($editAnn['body'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <?php foreach (['general','urgent','reminder','event'] as $cat): ?>
                                <option value="<?=$cat?>" <?= ($editAnn['category'] ?? 'general')===$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_pinned" id="isPinned" <?= !empty($editAnn['is_pinned'])?'checked':'' ?>>
                                <label class="form-check-label" for="isPinned">Pin this announcement</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_draft" id="isDraft" <?= !empty($editAnn['is_draft'])?'checked':'' ?>>
                                <label class="form-check-label" for="isDraft">Save as draft</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= $editAnn ? 'Update' : 'Publish' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editAnn): ?>
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('annModal')).show());</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
