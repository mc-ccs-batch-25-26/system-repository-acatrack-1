<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> — PGCEAP Portal</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
      <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/icon.png">
</head>
<body class="admin-layout">

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<?php
$user = getCurrentUser();
$role = $user['role'];
$currentUri = $_SERVER['REQUEST_URI'];
function navActive(string $path): string {
    global $currentUri;
    return str_contains($currentUri, $path) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div>
            <img src="<?= BASE_URL ?>/assets/images/icon.png" alt="PGCEAP Logo" style="width: 40px; height: 40px;">   
        </div>
        <div class="brand-text">
            <span class="brand-name">PGCEAP</span>
            <span class="brand-sub">Scholar Portal</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= navActive('dashboard') ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <?php if (hasPermission('manage_scholars') || hasPermission('view_scholars') || hasPermission('view_scholars_school')): ?>
        <a href="<?= BASE_URL ?>/admin/scholars.php" class="nav-item <?= navActive('scholars') ?>">
            <i class="bi bi-people-fill"></i>
            <span>Scholars</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_documents') || hasPermission('manage_documents_school')): ?>
        <div class="nav-section-label">Reviews</div>
        <a href="<?= BASE_URL ?>/admin/document-review.php" class="nav-item <?= navActive('document-review') ?>">
            <i class="bi bi-file-earmark-check-fill"></i>
            <span>Document Review</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/requirements.php" class="nav-item <?= navActive('requirements') ?>">
            <i class="bi bi-clipboard2-check-fill"></i>
            <span>Requirements</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_billing') || hasPermission('manage_payroll')): ?>
        <div class="nav-section-label">Finance</div>
        <?php if (hasPermission('manage_billing')): ?>
        <a href="<?= BASE_URL ?>/admin/billing.php" class="nav-item <?= navActive('billing') ?>">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Billing</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('manage_payroll')): ?>
        <a href="<?= BASE_URL ?>/admin/payroll.php" class="nav-item <?= navActive('payroll') ?>">
            <i class="bi bi-cash-coin"></i>
            <span>Payroll</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <div class="nav-section-label">Content</div>
        <?php if (hasPermission('manage_announcements')): ?>
        <a href="<?= BASE_URL ?>/admin/announcements.php" class="nav-item <?= navActive('announcements') ?>">
            <i class="bi bi-megaphone-fill"></i>
            <span>Announcements</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('submit_achiever_records')): ?>
        <a href="<?= BASE_URL ?>/admin/graduates.php" class="nav-item <?= navActive('graduates') ?>">
            <i class="bi bi-award-fill"></i>
            <span>Graduates</span>
        </a>
        <?php endif; ?>

        <?php if (hasPermission('manage_staff')): ?>
        <div class="nav-section-label">System</div>
        <a href="<?= BASE_URL ?>/admin/staff.php" class="nav-item <?= navActive('staff') ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>Staff Accounts</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="user-role"><?= str_replace('_', ' ', $role) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>

<!-- ── Main Content ──────────────────────────────────────────── -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="btn-sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <div class="topbar-actions">
            <div class="topbar-meta">
                <?= date('l, F j, Y') ?>
            </div>
        </div>
    </header>

    <main class="page-content">
