<?php
// ============================================================
// PGCEAP Portal — Scholar Requirements & Document Upload
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('scholar');

$pageTitle = 'Requirements';
$db = getDB();
$user = getCurrentUser();
$scholarId = $user['id'];
$msg = '';
$msgType = 'success';

// Fetch scholar
$s = $db->prepare("SELECT * FROM scholars WHERE id=?");
$s->execute([$scholarId]);
$scholar = $s->fetch();

// ── Handle Upload ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_doc') {
        $type     = sanitize($_POST['doc_type'] ?? '');
        $sy       = sanitize($_POST['school_year'] ?? '');
        $sem      = intval($_POST['semester'] ?? 1);
        $allowedTypes = ['COG','REGISTRATION_FORM','RECONSIDERATION'];

        if (!in_array($type, $allowedTypes)) {
            $msg = 'Invalid document type.'; $msgType = 'danger';
        } elseif (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Please select a file to upload.'; $msgType = 'danger';
        } else {
            $file = $_FILES['doc_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf','jpg','jpeg','png'];
            if (!in_array($ext, $allowedExts)) {
                $msg = 'Only PDF, JPG, and PNG files are allowed.'; $msgType = 'danger';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $msg = 'File size must not exceed 5MB.'; $msgType = 'danger';
            } else {
                $uploadDir = UPLOAD_DIR . 'documents/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = $scholarId . '_' . $type . '_' . $sy . '_' . $sem . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $relPath = 'documents/' . $filename;
                    $db->prepare("INSERT INTO scholar_documents (id, scholar_id, type, school_year, semester, file_path, file_name, file_size, status) VALUES (UUID(),?,?,?,?,?,?,?,'PENDING')")
                       ->execute([$scholarId, $type, $sy, $sem, $relPath, $file['name'], $file['size']]);
                    $msg = "Document uploaded successfully. It will be reviewed by the admin.";
                } else {
                    $msg = 'Upload failed. Please try again.'; $msgType = 'danger';
                }
            }
        }
    }

    if ($action === 'submit_activity') {
        $activityId = sanitize($_POST['activity_id'] ?? '');
        $notes      = sanitize($_POST['notes'] ?? '');
        $filePath   = null;

        // Check not already submitted
        $existing = $db->prepare("SELECT id FROM submissions WHERE scholar_id=? AND activity_id=?");
        $existing->execute([$scholarId, $activityId]);
        if ($existing->fetch()) {
            $msg = 'You have already submitted for this activity.'; $msgType = 'warning';
        } else {
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['proof_file'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf','jpg','jpeg','png']) && $file['size'] <= 5*1024*1024) {
                    $uploadDir = UPLOAD_DIR . 'submissions/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = $scholarId.'_activity_'.$activityId.'_'.time().'.'.$ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) {
                        $filePath = 'submissions/'.$filename;
                    }
                }
            }
            $db->prepare("INSERT INTO submissions (id, activity_id, scholar_id, scholar_name, notes, file_path, status) VALUES (UUID(),?,?,?,?,?,'PENDING')")
               ->execute([$activityId, $scholarId, $user['name'], $notes, $filePath]);
            $msg = 'Activity submission sent for review.';
        }
    }
}

// Fetch documents
$myDocs = $db->prepare("SELECT * FROM scholar_documents WHERE scholar_id=? ORDER BY created_at DESC");
$myDocs->execute([$scholarId]);
$myDocs = $myDocs->fetchAll();

// Fetch available activities
$activities = $db->query("SELECT * FROM activities WHERE is_active=1 ORDER BY activity_date DESC")->fetchAll();
$submittedActivityIds = $db->prepare("SELECT activity_id FROM submissions WHERE scholar_id=?");
$submittedActivityIds->execute([$scholarId]);
$submittedIds = $submittedActivityIds->fetchAll(PDO::FETCH_COLUMN);

// Fetch my submissions
$mySubmissions = $db->prepare("SELECT s.*, a.title AS activity_title FROM submissions s JOIN activities a ON a.id=s.activity_id WHERE s.scholar_id=? ORDER BY s.created_at DESC");
$mySubmissions->execute([$scholarId]);
$mySubmissions = $mySubmissions->fetchAll();

$unreadNotif = $db->prepare("SELECT COUNT(*) FROM notifications WHERE scholar_id=? AND is_read=0");
$unreadNotif->execute([$scholarId]);
$unreadCount = $unreadNotif->fetchColumn();

$activeTab = $_GET['tab'] ?? 'documents';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements — PGCEAP Portal</title>
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
    <a href="<?= BASE_URL ?>/scholar/requirements.php" class="scholar-nav-link active"><i class="bi bi-clipboard2-check-fill"></i> Requirements</a>
    <a href="<?= BASE_URL ?>/scholar/notifications.php" class="scholar-nav-link position-relative">
        <i class="bi bi-bell-fill"></i> Notifications
        <?php if ($unreadCount>0): ?><span class="position-absolute badge bg-danger" style="top:-4px;right:-4px;font-size:9px;"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
        <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="scholar-content">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
        <i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-triangle' ?>-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <h4 class="fw-800 mb-4"><i class="bi bi-clipboard2-check-fill me-2 text-accent"></i>Requirements</h4>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" style="border-color:var(--border);">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab==='documents'?'active':'' ?>" href="?tab=documents" style="color:var(--text-secondary);border-color:transparent;">
                <i class="bi bi-file-earmark-text me-1"></i> Documents
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab==='activities'?'active':'' ?>" href="?tab=activities" style="color:var(--text-secondary);border-color:transparent;">
                <i class="bi bi-calendar-check me-1"></i> Activities
            </a>
        </li>
    </ul>

    <?php if ($activeTab === 'documents'): ?>
    <!-- ── Documents Tab ──────────────────────────────────────── -->
    <div class="row g-4">
        <!-- Upload Form -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-cloud-upload-fill"></i> Upload Document</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_doc">
                        <div class="mb-3">
                            <label class="form-label">Document Type *</label>
                            <select class="form-select" name="doc_type" required>
                                <option value="">— Select Type —</option>
                                <option value="COG">Certificate of Grades (COG)</option>
                                <option value="REGISTRATION_FORM">Registration Form</option>
                                <option value="RECONSIDERATION">Reconsideration Letter</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label">School Year *</label>
                                <input type="text" class="form-control" name="school_year" placeholder="2024-2025" value="<?= htmlspecialchars($scholar['school_year']??'') ?>" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Semester</label>
                                <select class="form-select" name="semester">
                                    <option value="1" <?= ($scholar['semester']??2)==1?'selected':'' ?>>1st</option>
                                    <option value="2" <?= ($scholar['semester']??2)==2?'selected':'' ?>>2nd</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">File *</label>
                            <div class="upload-zone" onclick="document.getElementById('docFile').click()">
                                <i class="bi bi-file-earmark-arrow-up"></i>
                                <div class="small fw-600 mb-1">Click to browse or drag & drop</div>
                                <div class="upload-filename small text-muted-pg">PDF, JPG, PNG — max 5MB</div>
                                <input type="file" id="docFile" name="doc_file" accept=".pdf,.jpg,.jpeg,.png" class="d-none" required
                                    onchange="this.closest('.upload-zone').querySelector('.upload-filename').textContent=this.files[0].name">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-cloud-upload me-1"></i> Upload Document
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- My Documents -->
        <div class="col-md-7">
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="table-title"><i class="bi bi-folder2-open"></i> My Documents</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Type</th><th>Period</th><th>Uploaded</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($myDocs as $d): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?= str_replace('_',' ',$d['type']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($d['school_year']) ?> / Sem <?= $d['semester'] ?></td>
                                <td class="small text-muted-pg"><?= formatDate($d['created_at']) ?></td>
                                <td>
                                    <?= statusBadge($d['status']) ?>
                                    <?php if ($d['status']==='REJECTED' && $d['rejection_reason']): ?>
                                    <div class="small text-danger mt-1"><?= htmlspecialchars($d['rejection_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['file_path']): ?>
                                    <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-xs btn-ghost">
                                        <i class="bi bi-eye text-accent"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($myDocs)): ?>
                            <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-folder-x"></i><p>No documents uploaded yet.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Activities Tab ─────────────────────────────────────── -->
    <div class="row g-4">
        <!-- Available Activities -->
        <div class="col-md-6">
            <h6 class="fw-700 mb-3 text-accent">Available Activities</h6>
            <?php foreach ($activities as $a): ?>
            <?php $alreadySubmitted = in_array($a['id'], $submittedIds); ?>
            <div class="card mb-3 <?= $alreadySubmitted?'opacity-75':'' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-700 mb-0"><?= htmlspecialchars($a['title']) ?></h6>
                        <?php if ($alreadySubmitted): ?>
                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Submitted</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($a['description']): ?>
                    <p class="small text-muted-pg mb-2"><?= htmlspecialchars($a['description']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-3 small text-muted-pg mb-3">
                        <?php if ($a['activity_date']): ?><span><i class="bi bi-calendar me-1"></i><?= formatDate($a['activity_date']) ?></span><?php endif; ?>
                        <?php if ($a['deadline']): ?><span><i class="bi bi-alarm me-1"></i>Due <?= formatDate($a['deadline']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!$alreadySubmitted): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal" onclick="setActivity('<?= $a['id'] ?>', '<?= htmlspecialchars(addslashes($a['title'])) ?>')">
                        <i class="bi bi-send me-1"></i> Submit Proof
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($activities)): ?>
            <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No activities available at this time.</p></div>
            <?php endif; ?>
        </div>

        <!-- My Submissions -->
        <div class="col-md-6">
            <h6 class="fw-700 mb-3 text-accent">My Submissions</h6>
            <?php foreach ($mySubmissions as $s): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-600 small"><?= htmlspecialchars($s['activity_title']) ?></div>
                            <div class="x-small text-muted-pg mt-1"><?= formatDate($s['submitted_at']) ?></div>
                        </div>
                        <?= statusBadge($s['status']) ?>
                    </div>
                    <?php if ($s['status']==='REJECTED' && $s['rejection_reason']): ?>
                    <div class="small text-danger mt-2"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($s['rejection_reason']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($mySubmissions)): ?>
            <div class="empty-state"><i class="bi bi-clipboard2-x"></i><p>No submissions yet.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Submit Activity Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send-fill me-2"></i>Submit Activity Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_activity">
                <input type="hidden" name="activity_id" id="activityIdInput">
                <div class="modal-body">
                    <p class="fw-600 mb-3" id="activityTitleDisplay"></p>
                    <div class="mb-3">
                        <label class="form-label">Proof of Attendance/Participation</label>
                        <div class="upload-zone">
                            <i class="bi bi-image"></i>
                            <div class="small fw-600 mb-1">Upload photo/document proof</div>
                            <div class="upload-filename small text-muted-pg">Optional — PDF, JPG, PNG</div>
                            <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" class="d-none"
                                onchange="this.closest('.upload-zone').querySelector('.upload-filename').textContent=this.files[0].name">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Any notes about your participation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
function setActivity(id, title) {
    document.getElementById('activityIdInput').value = id;
    document.getElementById('activityTitleDisplay').textContent = title;
}
</script>
</body>
</html>
