-- ============================================================================
-- شبكة البرنس — مخطط قاعدة البيانات
-- MySQL / MariaDB — محرّك InnoDB، ترميز utf8mb4، مبالغ بدقة DECIMAL
-- السكربت قابل للتنفيذ أكثر من مرة بأمان (IF NOT EXISTS + INSERT IGNORE)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ── المستخدمون ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(190)  NOT NULL,
    username      VARCHAR(60)   NULL,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME      NULL,
    created_at    DATETIME      NOT NULL,
    updated_at    DATETIME      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الباقات ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS packages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)  NOT NULL,
    price       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    data_mb     INT UNSIGNED  NOT NULL DEFAULT 0,
    hours       INT UNSIGNED  NOT NULL DEFAULT 0,
    color       VARCHAR(20)   NOT NULL DEFAULT '#2563eb',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    description VARCHAR(255)  NULL,
    created_at  DATETIME      NOT NULL,
    updated_at  DATETIME      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_packages_name (name),
    KEY idx_packages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── حركات المخزون (بالشدات) ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_movements (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id             INT UNSIGNED NOT NULL,
    type                   ENUM('add','sell','return','adjust') NOT NULL,
    direction              ENUM('up','down') NULL,
    quantity               INT UNSIGNED NOT NULL,
    unit_cost              DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reason                 VARCHAR(255)  NULL,
    related_operation_type VARCHAR(40)   NULL,
    related_operation_id   INT UNSIGNED  NULL,
    created_at             DATETIME      NOT NULL,
    created_by             INT UNSIGNED  NULL,
    PRIMARY KEY (id),
    KEY idx_inv_package (package_id),
    KEY idx_inv_type (type),
    KEY idx_inv_created (created_at),
    KEY idx_inv_operation (related_operation_type, related_operation_id),
    CONSTRAINT fk_inv_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inv_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── أرصدة المخزون (سجل واحد نشط لكل باقة) ──────────────────────────────────
-- المفتاح الأساسي على package_id يمنع إنشاء أكثر من سجل مخزون للباقة نفسها.
-- يُحدَّث هذا الرصيد تراكميًا داخل معاملة عند كل حركة، وتبقى سجلات الحركة في
-- جدول inventory_movements كسجل تاريخي يمكن مراجعته.
CREATE TABLE IF NOT EXISTS inventory_stock (
    package_id INT UNSIGNED NOT NULL,
    quantity   INT          NOT NULL DEFAULT 0,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (package_id),
    CONSTRAINT fk_stock_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الموزعون ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS distributors (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(40)  NULL,
    address    VARCHAR(255) NULL,
    notes      TEXT         NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_distributors_status (status),
    KEY idx_distributors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الفواتير ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number   VARCHAR(40)  NOT NULL,
    distributor_id   INT UNSIGNED NOT NULL,
    distributor_name VARCHAR(150) NOT NULL,
    sale_date        DATETIME     NOT NULL,
    subtotal         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    paid_amount      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_type     ENUM('cash','credit') NOT NULL DEFAULT 'cash',
    status           ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    notes            TEXT         NULL,
    cancelled_at     DATETIME     NULL,
    cancel_reason    VARCHAR(255) NULL,
    created_at       DATETIME     NOT NULL,
    created_by       INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_invoice (invoice_number),
    KEY idx_sales_distributor (distributor_id),
    KEY idx_sales_date (sale_date),
    KEY idx_sales_status (status),
    CONSTRAINT fk_sales_distributor FOREIGN KEY (distributor_id) REFERENCES distributors (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── عناصر الفواتير ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sale_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id    INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_items_sale (sale_id),
    KEY idx_items_package (package_id),
    CONSTRAINT fk_items_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_items_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── التحصيلات ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS collections (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    distributor_id  INT UNSIGNED NOT NULL,
    sale_id         INT UNSIGNED NULL,
    amount          DECIMAL(14,2) NOT NULL,
    collection_date DATETIME     NOT NULL,
    notes           TEXT         NULL,
    status          ENUM('active','reversed') NOT NULL DEFAULT 'active',
    created_at      DATETIME     NOT NULL,
    created_by      INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_collections_distributor (distributor_id),
    KEY idx_collections_sale (sale_id),
    KEY idx_collections_date (collection_date),
    KEY idx_collections_status (status),
    CONSTRAINT fk_collections_distributor FOREIGN KEY (distributor_id) REFERENCES distributors (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_collections_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_collections_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── خطوط الإنترنت ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lines` (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(150) NOT NULL,
    provider          VARCHAR(150) NULL,
    identifier        VARCHAR(120) NULL,
    speed             VARCHAR(60)  NULL,
    subscription_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subscription_date DATE         NULL,
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL,
    updated_at        DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_lines_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── دفعات الخطوط ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS line_payments (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id      INT UNSIGNED NOT NULL,
    amount       DECIMAL(14,2) NOT NULL,
    payment_date DATETIME     NOT NULL,
    notes        TEXT         NULL,
    created_at   DATETIME     NOT NULL,
    created_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_lp_line (line_id),
    KEY idx_lp_date (payment_date),
    CONSTRAINT fk_lp_line FOREIGN KEY (line_id) REFERENCES `lines` (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lp_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── المصروفات ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category     VARCHAR(40)  NOT NULL DEFAULT 'other',
    amount       DECIMAL(14,2) NOT NULL,
    description  VARCHAR(255) NOT NULL,
    notes        TEXT         NULL,
    expense_date DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL,
    created_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_category (category),
    CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── سحوبات المالك ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS owner_withdrawals (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    amount           DECIMAL(14,2) NOT NULL,
    reason           VARCHAR(255) NOT NULL,
    notes            TEXT         NULL,
    withdrawal_date  DATETIME     NOT NULL,
    created_at       DATETIME     NOT NULL,
    created_by       INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_withdrawals_date (withdrawal_date),
    CONSTRAINT fk_withdrawals_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── حركات الصندوق ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cash_transactions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type             ENUM('in','out') NOT NULL,
    source           ENUM('sale','collection','expense','line_payment','owner_withdrawal','opening','reversal') NOT NULL,
    amount           DECIMAL(14,2) NOT NULL,
    reference_type   VARCHAR(40)  NULL,
    reference_id     INT UNSIGNED NULL,
    description      VARCHAR(255) NOT NULL,
    transaction_date DATETIME     NOT NULL,
    created_at       DATETIME     NOT NULL,
    created_by       INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_cash_date (transaction_date),
    KEY idx_cash_source (source),
    KEY idx_cash_reference (reference_type, reference_id),
    CONSTRAINT fk_cash_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الإعدادات ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    network_name        VARCHAR(150) NOT NULL DEFAULT 'شبكة البرنس',
    currency            VARCHAR(10)  NOT NULL DEFAULT 'YER',
    currency_symbol     VARCHAR(10)  NOT NULL DEFAULT 'ر.ي',
    admin_email         VARCHAR(190) NULL,
    opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 10,
    updated_at          DATETIME     NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── سجل التدقيق ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    user_name   VARCHAR(120) NULL,
    action      VARCHAR(40)  NOT NULL,
    entity_type VARCHAR(40)  NOT NULL,
    entity_id   INT UNSIGNED NULL,
    details     TEXT         NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── النسخ الاحتياطية ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS backups (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename     VARCHAR(255) NOT NULL,
    size         INT UNSIGNED NOT NULL DEFAULT 0,
    tables_count INT UNSIGNED NOT NULL DEFAULT 0,
    label        VARCHAR(60)  NULL,
    created_at   DATETIME     NOT NULL,
    created_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_backups_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── البيانات الأولية ────────────────────────────────────────────────────────
-- الإعدادات الافتراضية
INSERT IGNORE INTO settings (id, network_name, currency, currency_symbol, admin_email, opening_balance, low_stock_threshold, updated_at)
VALUES (1, 'شبكة البرنس', 'YER', 'ر.ي', NULL, 0.00, 10, NOW());

-- الباقات الأساسية المعروفة في النظام (100 / 200 / 250 / 500 / 1000).
-- ملاحظة: حجم البيانات وعدد الساعات غير مذكورين في ملفات المرجع،
-- لذلك تُركا صفرًا ليُحدَّثا من شاشة «الباقات». سعر الشدة = قيمة الباقة.
INSERT IGNORE INTO packages (id, name, price, data_mb, hours, color, status, description, created_at, updated_at) VALUES
    (1, '100',  100.00, 0, 0, '#2563eb', 'active', NULL, NOW(), NOW()),
    (2, '200',  200.00, 0, 0, '#0ea5e9', 'active', NULL, NOW(), NOW()),
    (3, '250',  250.00, 0, 0, '#10b981', 'active', NULL, NOW(), NOW()),
    (4, '500',  500.00, 0, 0, '#f59e0b', 'active', NULL, NOW(), NOW()),
    (5, '1000', 1000.00, 0, 0, '#8b5cf6', 'active', NULL, NOW(), NOW());

-- تهيئة أرصدة المخزون للباقات التي لها حركات ولا يوجد لها صف رصيد بعد
-- (لا يُنشأ سجل جديد عند كل إضافة؛ يُجمع الرصيد في صف واحد لكل باقة).
INSERT IGNORE INTO inventory_stock (package_id, quantity, updated_at)
SELECT package_id,
       SUM(CASE WHEN type IN ('add','return') THEN quantity
                WHEN type = 'sell' THEN -quantity
                WHEN type = 'adjust' AND direction = 'up' THEN quantity
                WHEN type = 'adjust' AND direction = 'down' THEN -quantity
                ELSE 0 END),
       NOW()
FROM inventory_movements
GROUP BY package_id;
