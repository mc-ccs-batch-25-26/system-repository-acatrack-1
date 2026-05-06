<?php
// ============================================================
// PGCEAP Portal — Billing Module (Dual Table Picker)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
requirePermission('manage_billing');

$pageTitle = 'Billing';
$db = getDB();
$msg = '';
$msgType = 'success';

// ── Handle Export ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    $pickerDataRaw = $_POST['picker_data'] ?? '[]';
    $pickerData    = json_decode($pickerDataRaw, true) ?: [];
    $billRef       = 'BILL-' . strtoupper(uniqid());
    $schoolYear    = sanitize($_POST['school_year'] ?? '');
    $semester      = intval($_POST['semester'] ?? 1);
    $schoolName    = sanitize($_POST['school_name'] ?? '');

    if (empty($pickerData)) {
        $msg = 'No scholars selected.'; $msgType = 'warning';
    } else {
        // Check for zero TF
        $hasZero = false;
        foreach ($pickerData as $item) {
            if (floatval($item['amount']) <= 0) { $hasZero = true; break; }
        }
        if ($hasZero) {
            $msg = 'Cannot export: one or more scholars have ₱0 TF amount.'; $msgType = 'danger';
        } else {
            // Create billing batch
            $batchId = generateUUID();
            $db->prepare("INSERT INTO billing_batches (id, school_name, sem, year_start, year_end, source_letter_reference, source_letter_received_at, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$batchId, $schoolName ?: 'Multiple Schools', $semester, date('Y'), date('Y')+1, $billRef, date('Y-m-d'), 'APPROVED', $_SESSION['user_id']]);

            $billedIds = [];
            foreach ($pickerData as $item) {
                $scholarId = $item['scholar_id'];
                $amount    = floatval($item['amount']);
                // Get scholar name
                $sname = $db->prepare("SELECT CONCAT(first_name,' ',last_name) FROM scholars WHERE id = ?");
                $sname->execute([$scholarId]);
                $scholarName = $sname->fetchColumn() ?: 'Unknown';

                // Insert billing item
                $db->prepare("INSERT INTO billing_items (id, batch_id, scholar_id, scholar_name, amount, status) VALUES (UUID(),?,?,?,?,'BILLED')")
                   ->execute([$batchId, $scholarId, $scholarName, $amount]);

                // Update scholar
                $db->prepare("UPDATE scholars SET billed=1, bill_amount=?, bill_ref=?, billed_at=NOW(), school_year=?, semester=? WHERE id=?")
                   ->execute([$amount, $billRef, $schoolYear, $semester, $scholarId]);

                $billedIds[] = $scholarId;
            }

            // Generate CSV
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=billing_{$billRef}.csv");
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Bill Ref','Scholar Name','School','TF Amount','School Year','Semester','Date Billed']);
            foreach ($pickerData as $item) {
                $srow = $db->prepare("SELECT * FROM scholars WHERE id = ?");
                $srow->execute([$item['scholar_id']]);
                $sr = $srow->fetch();
                fputcsv($out, [$billRef, ($sr['first_name']??'').' '.($sr['last_name']??''), $sr['school']??'', $item['amount'], $schoolYear, $semester, date('Y-m-d')]);
            }
            fclose($out);
            exit;
        }
    }
}

// ── Fetch Eligible Scholars ────────────────────────────────────
$search  = sanitize($_GET['q'] ?? '');
$statusF = $_GET['status'] ?? 'SCHOLAR';
$billedF = $_GET['billed'] ?? '0'; // 0 = Not Billed
$schoolF = sanitize($_GET['school'] ?? '');
$sem     = intval($_GET['semester'] ?? 0);
$sy      = sanitize($_GET['school_year'] ?? '');

$where = ['1=1'];
$params = [];
if ($search)  { $where[] = "(first_name LIKE ? OR last_name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%"]); }
if ($statusF !== '') { $where[] = "pgceap_status = ?"; $params[] = $statusF; }
if ($billedF !== '') { $where[] = "billed = ?"; $params[] = $billedF; }
if ($schoolF) { $where[] = "school LIKE ?"; $params[] = "%$schoolF%"; }
if ($sem > 0) { $where[] = "semester = ?"; $params[] = $sem; }
if ($sy)      { $where[] = "school_year = ?"; $params[] = $sy; }

$whereStr = implode(' AND ', $where);
$stmt = $db->prepare("SELECT * FROM scholars WHERE $whereStr ORDER BY last_name, first_name LIMIT 200");
$stmt->execute($params);
$availableScholars = $stmt->fetchAll();

// Recent billing batches
$recentBatches = $db->query("SELECT b.*, (SELECT COUNT(*) FROM billing_items WHERE batch_id=b.id) AS item_count FROM billing_batches b ORDER BY b.created_at DESC LIMIT 10")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="alert alert-info d-flex align-items-start gap-3 mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
        <strong>Billing Cycle:</strong> Filter eligible scholars on the left, transfer them to the "For Billing" table on the right, set TF amounts, then export CSV. This will assign a Bill Reference Number and mark scholars as billed.
    </div>
</div>

<!-- ── Filters ────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill"></i> Quick Filters</div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search Scholar</label>
                <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="SCHOLAR" <?= $statusF==='SCHOLAR'?'selected':'' ?>>SCHOLAR</option>
                    <option value="" <?= $statusF===''?'selected':'' ?>>All</option>
                    <option value="APPLICANT" <?= $statusF==='APPLICANT'?'selected':'' ?>>APPLICANT</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Billed?</label>
                <select class="form-select" name="billed">
                    <option value="0" <?= $billedF==='0'?'selected':'' ?>>Not Billed</option>
                    <option value="1" <?= $billedF==='1'?'selected':'' ?>>Already Billed</option>
                    <option value="" <?= $billedF===''?'selected':'' ?>>All</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">School Year</label>
                <input type="text" class="form-control" name="school_year" placeholder="2024-2025" value="<?= htmlspecialchars($sy) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Sem</label>
                <select class="form-select" name="semester">
                    <option value="0">All</option>
                    <option value="1" <?= $sem===1?'selected':'' ?>>1st</option>
                    <option value="2" <?= $sem===2?'selected':'' ?>>2nd</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Dual Table Picker ──────────────────────────────────────── -->
<form id="exportForm" method="POST">
    <input type="hidden" name="action" value="export">
    <input type="hidden" id="pickerData" name="picker_data" value="[]">

    <div class="row g-3 mb-4">
        <!-- Export Settings -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">School Year *</label>
                            <input type="text" class="form-control" name="school_year" placeholder="2024-2025" value="<?= htmlspecialchars($sy ?: date('Y').'-'.(date('Y')+1)) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Semester *</label>
                            <select class="form-select" name="semester" required>
                                <option value="1">1st Semester</option>
                                <option value="2" selected>2nd Semester</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">School Name (optional)</label>
                            <input type="text" class="form-control" name="school_name" placeholder="Leave blank for multiple schools">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                                Export CSV &amp; Bill Scholars
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available (Left) -->
        <div class="col-md-5">
            <div class="picker-panel">
                <div class="picker-panel-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people me-1"></i> Available Scholars</span>
                    <span class="badge bg-secondary" id="availableCount"><?= count($availableScholars) ?></span>
                </div>
                <div class="picker-body">
                    <table class="table table-hover mb-0" id="availableList">
                        <thead><tr><th>Scholar</th><th>TF Amount</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($availableScholars as $s): ?>
                            <?php $isQuarantined = floatval($s['tf_assistance_amount'] ?? 0) <= 0; ?>
                            <tr data-scholar-id="<?= $s['id'] ?>" <?= $isQuarantined ? 'style="opacity:.5"' : '' ?>>
                                <td>
                                    <div class="fw-600 small"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                    <div class="x-small text-muted-pg"><?= htmlspecialchars($s['school'] ?? '') ?></div>
                                    <?php if ($isQuarantined): ?><span class="badge bg-warning" style="font-size:9px;">QUARANTINED</span><?php endif; ?>
                                </td>
                                <td class="font-mono small"><?= formatCurrency($s['tf_assistance_amount'] ?? 0) ?></td>
                                <td><button type="button" class="btn btn-xs btn-primary btn-move-right">›</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($availableScholars)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted-pg small">No eligible scholars found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transfer Controls -->
        <div class="col-md-2 d-flex flex-column justify-content-center">
            <div class="picker-controls">
                <button type="button" id="btnMoveAll" class="btn btn-primary btn-sm">»<br><span style="font-size:10px">All</span></button>
                <button type="button" id="btnRemoveAll" class="btn btn-outline-secondary btn-sm">«<br><span style="font-size:10px">All</span></button>
                <div class="text-center mt-3">
                    <div class="small text-muted-pg">Total:</div>
                    <div class="fw-800 text-accent" id="totalAmount">₱0.00</div>
                </div>
            </div>
        </div>

        <!-- For Billing (Right) -->
        <div class="col-md-5">
            <div class="picker-panel" style="border-color:var(--warning);">
                <div class="picker-panel-header d-flex justify-content-between align-items-center" style="border-color:var(--warning);">
                    <span><i class="bi bi-receipt-cutoff me-1"></i> For Billing</span>
                    <span class="badge bg-warning text-dark" id="selectedCount">0</span>
                </div>
                <div class="picker-body">
                    <table class="table table-hover mb-0" id="selectedList">
                        <thead><tr><th>Scholar</th><th>TF Amount</th><th></th></tr></thead>
                        <tbody>
                            <!-- Rows moved here dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ── Recent Batches ─────────────────────────────────────────── -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-archive-fill"></i> Recent Billing Batches</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Batch ID</th><th>School</th><th>Period</th><th>Scholars</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
                <?php foreach ($recentBatches as $b): ?>
                <tr>
                    <td class="font-mono small"><?= htmlspecialchars(substr($b['id'],0,8)) ?>...</td>
                    <td><?= htmlspecialchars($b['school_name']) ?></td>
                    <td><?= $b['sem'] ?>sem / <?= $b['year_start'] ?>-<?= $b['year_end'] ?></td>
                    <td><?= $b['item_count'] ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td class="small text-muted-pg"><?= formatDate($b['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentBatches)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted-pg">No billing batches yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Inject TF amounts into moved rows
$extraJs = <<<JS
<script>
// Patch: when a row is moved right, auto-fill editable amount
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-move-right')) {
        const row = e.target.closest('tr');
        const amtCell = row.cells[1];
        const rawAmt  = amtCell.textContent.replace(/[^0-9.]/g,'');
        // Replace static amount with editable input
        amtCell.innerHTML = '<input type="number" class="form-control form-control-sm amount-input" style="width:100px" value="'+rawAmt+'" min="0.01" step="0.01">';
        e.target.textContent = '‹';
        e.target.classList.replace('btn-move-right','btn-move-left');
        e.target.classList.replace('btn-primary','btn-outline-secondary');
    }
});
// Pre-process pickerData on submit
document.getElementById('exportForm').addEventListener('submit', function(e) {
    const rows = document.getElementById('selectedList').querySelectorAll('tbody tr');
    if (!rows.length) {
        e.preventDefault();
        alert('Please add at least one scholar to the For Billing list.');
        return;
    }
    const data = [];
    rows.forEach(row => {
        data.push({
            scholar_id: row.dataset.scholarId,
            amount: row.querySelector('.amount-input')?.value || 0
        });
    });
    document.getElementById('pickerData').value = JSON.stringify(data);
});
</script>
JS;
include __DIR__ . '/../includes/footer.php'; ?>
