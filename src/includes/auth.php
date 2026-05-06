<?php
// ============================================================
// PGCEAP Portal - Auth & Session Helper
// ============================================================

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Role Constants ────────────────────────────────────────────
define('ROLE_SUPER_ADMIN', 'SUPER_ADMIN');
define('ROLE_ADMIN', 'ADMIN');
define('ROLE_MODERATOR', 'MODERATOR');
define('ROLE_STAFF', 'STAFF');
define('ROLE_SCHOOL_ADMIN', 'SCHOOL_ADMIN');
define('ROLE_SCHOLAR', 'SCHOLAR');

// ── Permissions Map ───────────────────────────────────────────
$ROLE_PERMISSIONS = [
    ROLE_SUPER_ADMIN  => ['all','manage_staff','manage_billing','manage_payroll','manage_scholars','manage_documents','manage_announcements','manage_graduates','view_audit_logs'],
    ROLE_ADMIN        => ['manage_billing','manage_payroll','manage_scholars','manage_documents','manage_announcements','manage_graduates'],
    ROLE_MODERATOR    => ['manage_scholars','manage_documents','manage_announcements','manage_requirements'],
    ROLE_STAFF        => ['view_scholars'],
    ROLE_SCHOOL_ADMIN => ['view_scholars_school','manage_documents_school','submit_achiever_records'],
    ROLE_SCHOLAR      => ['view_feed','view_profile','submit_documents','view_notifications'],
];

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isScholar(): bool {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'scholar';
}

function getRole(): string {
    return $_SESSION['role'] ?? '';
}

function hasPermission(string $permission): bool {
    global $ROLE_PERMISSIONS;
    $role = getRole();
    $perms = $ROLE_PERMISSIONS[$role] ?? [];
    return in_array('all', $perms) || in_array($permission, $perms);
}

function requireLogin(string $type = 'admin'): void {
    // Not logged in at all → send to login
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?error=session_expired');
        exit;
    }

    // Logged in as scholar but trying to access an admin page → send to login
    // (do NOT redirect to feed.php — that would create a loop if feed also fails)
    if ($type === 'admin' && !isAdmin()) {
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }

    // Logged in as admin but trying to access a scholar page → send to dashboard
    // (do NOT redirect to dashboard.php if that page also calls requireLogin — use login instead)
    if ($type === 'scholar' && !isScholar()) {
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function requirePermission(string $permission): void {
    requireLogin('admin');
    if (!hasPermission($permission)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'    => $_SESSION['user_id'] ?? '',
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['role'] ?? '',
        'type'  => $_SESSION['user_type'] ?? '',
        'school'=> $_SESSION['school_assignment'] ?? null,
    ];
}

function loginAdmin(array $admin): void {
    $_SESSION['user_id']           = $admin['id'];
    $_SESSION['user_name']         = $admin['name'];
    $_SESSION['user_email']        = $admin['email'];
    $_SESSION['role']              = $admin['role'];
    $_SESSION['user_type']         = 'admin';
    $_SESSION['school_assignment'] = $admin['school_assignment'] ?? null;
}

function loginScholar(array $scholar): void {
    $_SESSION['user_id']    = $scholar['id'];
    $_SESSION['user_name']  = $scholar['first_name'] . ' ' . $scholar['last_name'];
    $_SESSION['user_email'] = $scholar['email'];
    $_SESSION['role']       = ROLE_SCHOLAR;
    $_SESSION['user_type']  = 'scholar';
}

function logout(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function formatDate(?string $date): string {
    if (!$date) return '—';
    return date('M d, Y', strtotime($date));
}

function statusBadge(string $status): string {
    $map = [
        'SCHOLAR'     => 'success',
        'APPLICANT'   => 'primary',
        'PENDING'     => 'warning',
        'GRADUATED'   => 'info',
        'DROPPED'     => 'danger',
        'SUSPENDED'   => 'secondary',
        'APPROVED'    => 'success',
        'REJECTED'    => 'danger',
        'DRAFT'       => 'secondary',
        'FOR_APPROVAL'=> 'warning',
        'RELEASED'    => 'info',
        'PROCESSED'   => 'primary',
        'PAID'        => 'success',
        'CANCELLED'   => 'danger',
        'BILLED'      => 'info',
    ];
    $cls = $map[$status] ?? 'secondary';
    $label = str_replace('_', ' ', $status);
    return "<span class=\"badge bg-{$cls}\">{$label}</span>";
}
