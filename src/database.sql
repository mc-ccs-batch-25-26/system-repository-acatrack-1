-- ============================================================
-- PGCEAP Portal - MySQL Database Schema
-- Provincial Government College Educational Assistance Program
-- ============================================================

CREATE DATABASE IF NOT EXISTS pgceap_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pgceap_db;

-- ── Admin Users ──────────────────────────────────────────────
CREATE TABLE admin_users (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('SUPER_ADMIN','ADMIN','MODERATOR','STAFF','SCHOOL_ADMIN') NOT NULL DEFAULT 'STAFF',
    school_assignment VARCHAR(255) NULL COMMENT 'For SCHOOL_ADMIN: their assigned school',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Scholars ─────────────────────────────────────────────────
CREATE TABLE scholars (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    cert_num VARCHAR(50) NULL UNIQUE COMMENT 'PGCEAP certificate number',
    pgceap_status ENUM('APPLICANT','PENDING','SCHOLAR','GRADUATED','DROPPED','SUSPENDED') NOT NULL DEFAULT 'APPLICANT',
    -- Personal Info
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(20) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    contact_number VARCHAR(20) NULL,
    gender ENUM('Male','Female','Other') NULL,
    birthdate DATE NULL,
    -- Address
    municipality VARCHAR(100) NULL,
    barangay VARCHAR(100) NULL,
    address_full TEXT NULL,
    -- Academic Info
    school VARCHAR(255) NULL,
    school_type ENUM('Public','Private') NULL,
    course VARCHAR(255) NULL,
    major_subject VARCHAR(255) NULL,
    year_level TINYINT NULL,
    school_year VARCHAR(20) NULL COMMENT 'e.g. 2024-2025',
    semester TINYINT NULL COMMENT '1 or 2',
    -- Financial
    tf_assistance_amount DECIMAL(12,2) NULL DEFAULT 0,
    allowance DECIMAL(12,2) NULL DEFAULT 0,
    payroll_amount DECIMAL(12,2) NULL DEFAULT 0,
    -- Billing/Payroll Status (inline/legacy)
    billed TINYINT(1) NOT NULL DEFAULT 0,
    bill_amount DECIMAL(12,2) NULL,
    bill_ref VARCHAR(100) NULL,
    billed_at TIMESTAMP NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) NULL,
    pay_ref VARCHAR(100) NULL,
    paid_at TIMESTAMP NULL,
    -- System
    invited_at TIMESTAMP NULL,
    account_activated TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Scholar Documents ─────────────────────────────────────────
CREATE TABLE scholar_documents (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    scholar_id VARCHAR(36) NOT NULL,
    type ENUM('COG','REGISTRATION_FORM','RECONSIDERATION') NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester TINYINT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    rejection_reason TEXT NULL,
    reviewed_by VARCHAR(36) NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Activities ───────────────────────────────────────────────
CREATE TABLE activities (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    activity_date DATE NULL,
    deadline DATE NULL,
    school_year VARCHAR(20) NULL,
    semester TINYINT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Submissions (Activity Proofs) ─────────────────────────────
CREATE TABLE submissions (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    activity_id VARCHAR(36) NOT NULL,
    scholar_id VARCHAR(36) NOT NULL,
    scholar_name VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(500) NULL,
    notes TEXT NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    rejection_reason TEXT NULL,
    reviewed_by VARCHAR(36) NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Announcements ─────────────────────────────────────────────
CREATE TABLE announcements (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    category ENUM('general','urgent','reminder','event') NOT NULL DEFAULT 'general',
    image_path VARCHAR(500) NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    is_draft TINYINT(1) NOT NULL DEFAULT 0,
    posted_by VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Notifications ─────────────────────────────────────────────
CREATE TABLE notifications (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    scholar_id VARCHAR(36) NOT NULL,
    type ENUM('reminder','approved','rejected','system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    activity_id VARCHAR(36) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Billing Batches ───────────────────────────────────────────
CREATE TABLE billing_batches (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    school_name VARCHAR(255) NOT NULL,
    sem TINYINT NOT NULL CHECK (sem IN (1,2)),
    year_start INT NOT NULL,
    year_end INT NOT NULL,
    source_letter_reference VARCHAR(100) NOT NULL,
    source_letter_received_at DATE NOT NULL,
    status ENUM('DRAFT','FOR_APPROVAL','APPROVED','RELEASED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    created_by VARCHAR(36) NULL,
    approved_by VARCHAR(36) NULL,
    released_by VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Billing Items ─────────────────────────────────────────────
CREATE TABLE billing_items (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    batch_id VARCHAR(36) NOT NULL,
    scholar_id VARCHAR(36) NOT NULL,
    scholar_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('PENDING','APPROVED','REJECTED','BILLED') NOT NULL DEFAULT 'PENDING',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_scholar (batch_id, scholar_id),
    FOREIGN KEY (batch_id) REFERENCES billing_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Payroll Batches ───────────────────────────────────────────
CREATE TABLE payroll_batches (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    billing_batch_id VARCHAR(36) NULL UNIQUE,
    period_label VARCHAR(100) NOT NULL,
    status ENUM('DRAFT','FOR_APPROVAL','APPROVED','PROCESSED','PAID','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes TEXT NULL,
    created_by VARCHAR(36) NULL,
    approved_by VARCHAR(36) NULL,
    processed_by VARCHAR(36) NULL,
    paid_by VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Payroll Items ─────────────────────────────────────────────
CREATE TABLE payroll_items (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    payroll_batch_id VARCHAR(36) NOT NULL,
    billing_item_id VARCHAR(36) NULL,
    scholar_id VARCHAR(36) NOT NULL,
    scholar_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('PENDING','PROCESSED','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_scholar (payroll_batch_id, scholar_id),
    FOREIGN KEY (payroll_batch_id) REFERENCES payroll_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Auth Invites ──────────────────────────────────────────────
CREATE TABLE auth_invites (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    scholar_id VARCHAR(36) NOT NULL,
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash)
) ENGINE=InnoDB;

-- ── Scholar Audit Logs ────────────────────────────────────────
CREATE TABLE scholar_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    scholar_id VARCHAR(36) NULL,
    editor_email VARCHAR(255) NULL,
    editor_name VARCHAR(255) NULL,
    editor_role VARCHAR(50) NULL,
    action ENUM('UPDATE','STATUS_UPDATE','UPDATE_CERT_NUM') NOT NULL,
    changes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Announcement Reads ────────────────────────────────────────
CREATE TABLE announcement_reads (
    scholar_id VARCHAR(36) NOT NULL,
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (scholar_id),
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── App Settings ──────────────────────────────────────────────
CREATE TABLE app_settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Graduates ─────────────────────────────────────────────────
CREATE TABLE graduates (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    scholar_id VARCHAR(36) NOT NULL,
    school VARCHAR(255) NOT NULL,
    graduation_date DATE NULL,
    honors VARCHAR(100) NULL COMMENT 'e.g. Cum Laude, Magna Cum Laude',
    submitted_by VARCHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- SEED DATA
-- ────────────────────────────────────────────────────────────

-- Default Admin Accounts
-- superadmin@pgceap.gov.ph / Admin@1234
-- admin@pgceap.gov.ph      / Admin@1234
-- mod@pgceap.gov.ph        / Admin@1234
INSERT INTO admin_users (id, name, email, password_hash, role) VALUES
(UUID(), 'Super Administrator', 'superadmin@pgceap.gov.ph', '$2y$10$kZRWM665XN.Sf0TcltBW.edmxvPQDzjB1PyuZeQ6WZ1Tty87ykkqO', 'SUPER_ADMIN'),
(UUID(), 'System Admin',       'admin@pgceap.gov.ph',       '$2y$10$kZRWM665XN.Sf0TcltBW.edmxvPQDzjB1PyuZeQ6WZ1Tty87ykkqO', 'ADMIN'),
(UUID(), 'Maria Moderator',    'mod@pgceap.gov.ph',         '$2y$10$kZRWM665XN.Sf0TcltBW.edmxvPQDzjB1PyuZeQ6WZ1Tty87ykkqO', 'MODERATOR');

-- App Settings
INSERT INTO app_settings (`key`, `value`) VALUES
('app_name', 'PGCEAP Portal'),
('province', 'Masbate'),
('current_school_year', '2024-2025'),
('current_semester', '2'),
('billing_prefix', 'BILL'),
('payroll_prefix', 'PAY');

-- Sample Announcements
INSERT INTO announcements (id, title, body, category, is_pinned) VALUES
(UUID(), 'Welcome to PGCEAP Portal', 'Dear scholars, welcome to the official Provincial Government College Educational Assistance Program portal. Please complete your profile and upload required documents.', 'general', 1),
(UUID(), 'Document Submission Deadline', 'All scholars must submit their COG and Registration Form on or before March 31, 2025. Late submissions will not be processed for this semester.', 'urgent', 0),
(UUID(), 'Scholarship Disbursement Schedule', 'The tuition fee assistance for 2nd Semester AY 2024-2025 is scheduled for release next week. Please ensure your bank details are updated.', 'reminder', 0);

-- Sample Scholars
-- juan@scholar.com  / Scholar@1234
-- maria@scholar.com / Scholar@1234
INSERT INTO scholars (id, cert_num, pgceap_status, first_name, last_name, email, password_hash, school, school_type, course, year_level, school_year, semester, municipality, tf_assistance_amount, account_activated) VALUES
(UUID(), 'PGCEAP-2024-001', 'SCHOLAR', 'Juan',  'Dela Cruz', 'juan@scholar.com',  '$2y$10$P7rRWN/Zm/hW3myRJyyZfOpRLZVyN3rokn9kaEIF.dEYWtTkg6cO2', 'Masbate College', 'Public',  'Bachelor of Science in Information Technology', 3, '2024-2025', 2, 'Masbate City', 15000.00, 1),
(UUID(), 'PGCEAP-2024-002', 'SCHOLAR', 'Maria', 'Santos',    'maria@scholar.com', '$2y$10$P7rRWN/Zm/hW3myRJyyZfOpRLZVyN3rokn9kaEIF.dEYWtTkg6cO2', 'Masbate College', 'Public',  'Bachelor of Science in Nursing',                 2, '2024-2025', 2, 'Cataingan',   18000.00, 1),
(UUID(), 'PGCEAP-2024-003', 'APPLICANT','Pedro', 'Garcia',   'pedro@scholar.com', NULL,                                                                   'Ateneo de Masbate','Private','Bachelor of Arts in Political Science',           1, '2024-2025', 2, 'Aroroy',      20000.00, 0);
