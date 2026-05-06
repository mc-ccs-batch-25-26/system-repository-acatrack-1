<?php
// ============================================================
// PGCEAP Portal — Payroll Module
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
requirePermission('manage_payroll');

$pageTitle = 'Payroll';
$db = getDB();
$msg = '';
$msgType = 'success';

// ── Handle Export ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    $pickerDataRaw = $_POST['picker_data'] ?? '[]';
    $pickerData    = json_decode($pickerDataRaw, true) ?: [];
    $payRef        = 'PAY-' . strtoupper(uniqid());
    $periodLabel   = sanitize($_POST['period_label'] ?? '');
    $paymentRef    = sanitize($_POST['payment_reference'] ?? '') ?: $payRef;

    if (empty($pickerData)) {
        $msg = 'No scholars selected for payroll.'; $msgType = 'warning';
    } else {
        $hasZero = false;
        foreach ($pickerData as $item) {
            if (floatval($item['amount']) <= 0) { $hasZero = true; break; }
        }
        if ($hasZero) {
            $msg = 'Cannot export: one or more scholars have ₱0 paid amount.'; $msgType = 'danger';
        } else {
            // Create payroll batch
            $batchId = generateUUID();
            $db->prepare("INSERT INTO payroll_batches (id, period_label, status, created_by) VALUES (?,?,'PAID',?)")
               ->execute([$batchId, $periodLabel ?: date('Y-m-d'), $_SESSION['user_id']]);

            foreach ($pickerData as $item) {
                $scholarId = $item['scholar_id'];
                $amount    = floatval($item['amount']);
                $sname = $db->prepare("SELECT CONCAT(first_name,' ',last_name) FROM scholars WHERE id=?");
                $sname->execute([$scholarId]);
                $scholarName = $sname->fetchColumn() ?: 'Unknown';

                $db->prepare("INSERT INTO payroll_items (id, payroll_batch_id, scholar_id, scholar_name, amount, status) VALUES (UUID(),?,?,?,?,'PAID')")
                   ->execute([$batchId, $scholarId, $scholarName, $amount]);

                $db->prepare("UPDATE scholars SET paid=1, paid_amount=?, pay_ref=?, paid_at=NOW() WHERE id=?")
                   ->execute([$amount, $paymentRef, $scholarId]);

                $db->prepare("INSERT INTO notifications (id, scholar_id, type, title, message) VALUES (UUID(),?,'system','Scholarship Disbursed','Your tuition fee assistance of ".formatCurrency($amount)." has been processed. Reference: ".htmlspecialchars($paymentRef)."')")
                   ->execute([$scholarId]);
            }

            // Generate CSV
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=payroll_{$payRef}.csv");
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Pay Ref','Scholar Name','School','Paid Amount','Period','Date Paid','Status']);
            foreach ($pickerData as $item) {
                $srow = $db->prepare("SELECT * FROM scholars WHERE id=?");
                $srow->execute([$item['scholar_id']]);
                $sr = $srow->fetch();
                fputcsv($out, [$paymentRef, ($sr['first_name']??'').' '.($sr['last_name']??''), $sr['school']??'', $item['amount'], $periodLabel, date('Y-m-d'), 'PAID']);
            }
            fclose($out);
            exit;
        }
    }
}

// ── Fetch Eligible Scholars (billed=YES, paid=NO, status=SCHOLAR) ──
$search  = sanitize($_GET['q'] ?? '');
$schoolF = sanitize($_GET['school'] ?? '');
$syF     = sanitize($_GET['sy'] ?? '');

$where = ["pgceap_status='SCHOLAR'", "billed=1", "paid=0"];
$params = [];
if ($search)  { $where[] = "(first_name LIKE ? OR last_name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%"]); }
if ($schoolF) { $where[] = "school LIKE ?"; $params[] = "%$schoolF%"; }
if ($syF)     { $where[] = "school_year = ?"; $params[] = $syF; }
$whereStr = implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM scholars WHERE $whereStr ORDER BY last_name, first_name LIMIT 200");
$stmt->execute($params);
$eligible = $stmt->fetchAll();

// Stats
$totalBilled   = $db->query("SELECT COUNT(*) FROM scholars WHERE billed=1")->fetchColumn();
$totalPaid     = $db->query("SELECT COUNT(*) FROM scholars WHERE paid=1")->fetchColumn();
$totalPending  = $db->query("SELECT COUNT(*) FROM scholars WHERE billed=1 AND paid=0")->fetchColumn();
$totalDisbursed= $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM scholars WHERE paid=1")->fetchColumn();

// Recent payroll batches
$recentBatches = $db->query("SELECT b.*, (SELECT COUNT(*) FROM payroll_items WHERE payroll_batch_id=b.id) as item_count, (SELECT SUM(amount) FROM payroll_items WHERE payroll_batch_id=b.id) as total_amount FROM payroll_batches b ORDER BY b.created_at DESC LIMIT 8")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <?= $msg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-receipt-cutoff"></i></div>
            <div><div class="stat-value"><?= number_format($totalBilled) ?></div><div class="stat-label">Billed</div></div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-value"><?= number_format($totalPending) ?></div><div class="stat-label">Pending Payroll</div></div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalPaid) ?></div><div class="stat-label">Paid</div></div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-cash-stack"></i></div>
            <div><div class="stat-value" style="font-size:18px;"><?= formatCurrency($totalDisbursed) ?></div><div class="stat-label">Total Disbursed</div></div>
        </div>
    </div>
</div>

<div class="alert alert-success d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div><strong>Payroll Cycle:</strong> Only scholars with <code>billed=YES</code> and <code>paid=NO</code> appear here. Transfer them to the For Payroll table, set paid amounts, then export CSV (used as Payroll Masterlist for Treasury).</div>
</div>

<!-- ── Filters ────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-funnel-fill"></i> Filter Eligible Scholars</div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search Scholar</label>
                <div class="search-box"><i class="bi bi-search"></i>
                    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">School</label>
                <input type="text" class="form-control" name="school" value="<?= htmlspecialchars($schoolF) ?>" placeholder="Filter by school...">
            </div>
            <div class="col-md-2">
                <label class="form-label">School Year</label>
                <input type="text" class="form-control" name="sy" value="<?= htmlspecialchars($syF) ?>" placeholder="2024-2025">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Dual Table Picker ──────────────────────────────────────── -->
<form id="exportForm" method="POST">
    <input type="hidden" name="action" value="export">
    <input type="hidden" id="pickerData" name="picker_data" value="[]">

    <div class="row g-3 mb-4">
        <!-- Settings Row -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Period Label</label>
                            <input type="text" class="form-control" name="period_label" placeholder="e.g. 2nd Sem 2024-2025">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Reference (optional)</label>
                            <input type="text" class="form-control" name="payment_reference" placeholder="Auto-generated if blank">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                                Export Payroll CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available -->
        <div class="col-md-5">
            <div class="picker-panel">
                <div class="picker-panel-header d-flex justify-content-between">
                    <span><i class="bi bi-people me-1"></i> Billed & Unpaid Scholars</span>
                    <span class="badge bg-secondary" id="availableCount"><?= count($eligible) ?></span>
                </div>
                <div class="picker-body">
                    <table class="table table-hover mb-0" id="availableList">
                        <thead><tr><th>Scholar</th><th>Bill Amount</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($eligible as $s): ?>
                            <tr data-scholar-id="<?= $s['id'] ?>">
                                <td>
                                    <div class="fw-600 small"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                    <div class="x-small text-muted-pg"><?= htmlspecialchars($s['school'] ?? '') ?></div>
                                    <div class="x-small font-mono text-muted-pg"><?= htmlspecialchars($s['bill_ref'] ?? '') ?></div>
                                </td>
                                <td class="font-mono small"><?= formatCurrency($s['bill_amount'] ?? $s['tf_assistance_amount'] ?? 0) ?></td>
                                <td><button type="button" class="btn btn-xs btn-primary btn-move-right">›</button></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($eligible)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted-pg small">No eligible scholars. Make sure scholars are billed first.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="col-md-2 d-flex flex-column justify-content-center">
            <div class="picker-controls">
                <button type="button" id="btnMoveAll" class="btn btn-primary btn-sm">»<br><span style="font-size:10px">All</span></button>
                <button type="button" id="btnRemoveAll" class="btn btn-outline-secondary btn-sm">«<br><span style="font-size:10px">All</span></button>
                <div class="text-center mt-3">
                    <div class="small text-muted-pg">Total Payroll:</div>
                    <div class="fw-800 text-accent" id="totalAmount">₱0.00</div>
                </div>
            </div>
        </div>

        <!-- For Payroll -->
        <div class="col-md-5">
            <div class="picker-panel" style="border-color:var(--success);">
                <div class="picker-panel-header d-flex justify-content-between" style="border-color:var(--success);">
                    <span><i class="bi bi-cash-coin me-1"></i> For Payroll</span>
                    <span class="badge bg-success" id="selectedCount">0</span>
                </div>
                <div class="picker-body">
                    <table class="table table-hover mb-0" id="selectedList">
                        <thead><tr><th>Scholar</th><th>Paid Amount</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- ── Recent Payroll Batches ────────────────────────────────── -->
<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-archive-fill"></i> Recent Payroll Batches</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>ID</th><th>Period</th><th>Scholars</th><th>Total Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($recentBatches as $b): ?>
                <tr>
                    <td class="font-mono small"><?= htmlspecialchars(substr($b['id'],0,8)) ?>...</td>
                    <td><?= htmlspecialchars($b['period_label']) ?></td>
                    <td><?= $b['item_count'] ?></td>
                    <td class="font-mono"><?= formatCurrency($b['total_amount'] ?? 0) ?></td>
                    <td><?= statusBadge($b['status']) ?></td>
                    <td class="small text-muted-pg"><?= formatDate($b['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentBatches)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted-pg">No payroll batches yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-move-right')) {
        const row = e.target.closest('tr');
        const amtCell = row.cells[1];
        const rawAmt = amtCell.textContent.replace(/[^0-9.]/g,'');
        amtCell.innerHTML = '<input type="number" class="form-control form-control-sm amount-input" style="width:110px" value="'+rawAmt+'" min="0.01" step="0.01">';
        e.target.textContent = '‹';
        e.target.classList.replace('btn-move-right','btn-move-left');
        e.target.classList.replace('btn-primary','btn-outline-secondary');
    }
});
document.getElementById('exportForm').addEventListener('submit', function(e) {
    const rows = document.getElementById('selectedList').querySelectorAll('tbody tr');
    if (!rows.length) { e.preventDefault(); alert('Add at least one scholar to the For Payroll list.'); return; }
    const data = [];
    rows.forEach(row => {
        data.push({ scholar_id: row.dataset.scholarId, amount: row.querySelector('.amount-input')?.value || 0 });
    });
    document.getElementById('pickerData').value = JSON.stringify(data);
});
</script>
JS;
include __DIR__ . '/../includes/footer.php'; ?>
