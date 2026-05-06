<?php
// ============================================================
// PGCEAP Portal — Staff Account Management
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin('admin');
requirePermission('manage_staff');

$pageTitle = 'Staff Accounts';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name   = sanitize($_POST['name'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $role   = sanitize($_POST['role'] ?? 'STAFF');
        $school = sanitize($_POST['school_assignment'] ?? '');
        $pw     = $_POST['password'] ?? '';

        if (!$name || !$email) {
            $msg = 'Name and email are required.'; $msgType = 'danger';
        } else {
            try {
                if ($action === 'add') {
                    if (!$pw) { $msg = 'Password is required.'; $msgType = 'danger'; }
                    else {
                        $hash = password_hash($pw, PASSWORD_BCRYPT);
                        $db->prepare("INSERT INTO admin_users (id, name, email, password_hash, role, school_assignment) VALUES (UUID(),?,?,?,?,?)")
                           ->execute([$name, $email, $hash, $role, $school ?: null]);
                        $msg = "Staff account for $name created.";
                    }
                } else {
                    $id = $_POST['id'];
                    if ($pw) {
                        $hash = password_hash($pw, PASSWORD_BCRYPT);
                        $db->prepare("UPDATE admin_users SET name=?,email=?,role=?,school_assignment=?,password_hash=? WHERE id=?")
                           ->execute([$name,$email,$role,$school?:null,$hash,$id]);
                    } else {
                        $db->prepare("UPDATE admin_users SET name=?,email=?,role=?,school_assignment=? WHERE id=?")
                           ->execute([$name,$email,$role,$school?:null,$id]);
                    }
                    $msg = "Account updated.";
                }
            } catch (\PDOException $e) {
                $msg = $e->getCode()==='23000'?'Email already exists.':'Error: '.$e->getMessage();
                $msgType = 'danger';
            }
        }
    }

    if ($action === 'toggle_active') {
        $db->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE id=? AND id != ?")->execute([$_POST['id'], $_SESSION['user_id']]);
        $msg = 'Account status updated.';
    }

    if ($action === 'delete') {
        if ($_POST['id'] === $_SESSION['user_id']) { $msg = 'Cannot delete your own account.'; $msgType = 'danger'; }
        else { $db->prepare("DELETE FROM admin_users WHERE id=?")->execute([$_POST['id']]); $msg = 'Account deleted.'; }
    }
}

$staff = $db->query("SELECT * FROM admin_users ORDER BY role, name")->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $u = $db->prepare("SELECT * FROM admin_users WHERE id=?");
    $u->execute([$_GET['edit']]);
    $editUser = $u->fetch();
}

$schools = $db->query("SELECT DISTINCT school FROM scholars WHERE school IS NOT NULL ORDER BY school")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss alert-dismissible fade show mb-4">
    <?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="table-card">
    <div class="table-toolbar">
        <div class="table-title"><i class="bi bi-person-badge-fill"></i> Staff Accounts <span class="badge bg-primary ms-2"><?= count($staff) ?></span></div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#staffModal">
            <i class="bi bi-person-plus me-1"></i> Add Staff
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>School</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($staff as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar" style="width:30px;height:30px;font-size:12px;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                            <span class="fw-600"><?= htmlspecialchars($u['name']) ?></span>
                            <?php if ($u['id']===$_SESSION['user_id']): ?><span class="badge bg-primary" style="font-size:9px;">YOU</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php $roleColors = ['SUPER_ADMIN'=>'danger','ADMIN'=>'primary','MODERATOR'=>'info','STAFF'=>'secondary','SCHOOL_ADMIN'=>'warning']; ?>
                        <span class="badge bg-<?= $roleColors[$u['role']]??'secondary' ?>"><?= str_replace('_',' ',$u['role']) ?></span>
                    </td>
                    <td class="small text-muted-pg"><?= htmlspecialchars($u['school_assignment'] ?? '—') ?></td>
                    <td class="small text-muted-pg"><?= $u['last_login_at']?formatDate($u['last_login_at']):'Never' ?></td>
                    <td><span class="badge <?= $u['is_active']?'bg-success':'bg-danger' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-xs btn-ghost"><i class="bi bi-pencil-fill"></i></a>
                            <?php if ($u['id']!==$_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" title="Toggle Active">
                                    <i class="bi bi-toggle-<?= $u['is_active']?'on text-success':'off text-muted' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" data-confirm="Delete this account?">
                                    <i class="bi bi-trash-fill text-danger"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i><?= $editUser?'Edit Staff':'Add Staff Account' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editUser?'edit':'add' ?>">
                <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($editUser['name']??'') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($editUser['email']??'') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="roleSelect" onchange="toggleSchool()">
                                <?php foreach (['ADMIN','MODERATOR','STAFF','SCHOOL_ADMIN'] as $r): ?>
                                <option value="<?=$r?>" <?= ($editUser['role']??'STAFF')===$r?'selected':'' ?>><?= str_replace('_',' ',$r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="schoolField" style="<?= ($editUser['role']??'')!=='SCHOOL_ADMIN'?'display:none':'' ?>">
                            <label class="form-label">School Assignment</label>
                            <input type="text" class="form-control" name="school_assignment" list="schoolList2" value="<?= htmlspecialchars($editUser['school_assignment']??'') ?>">
                            <datalist id="schoolList2">
                                <?php foreach ($schools as $sch): ?><option value="<?= htmlspecialchars($sch) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password <?= $editUser?'(leave blank to keep)':'*' ?></label>
                            <input type="password" class="form-control" name="password" <?= !$editUser?'required':'' ?> placeholder="<?= $editUser?'Leave blank to keep current':'Set a strong password' ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSchool() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('schoolField').style.display = role === 'SCHOOL_ADMIN' ? '' : 'none';
}
</script>

<?php if ($editUser): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('staffModal')).show());</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
