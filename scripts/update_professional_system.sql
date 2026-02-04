-- تحديث قاعدة البيانات لنظام التقارير الاحترافية والمتابعة التنفيذية
-- Professional Reports & Execution Tracking System
-- Version: 1.0

-- ==================== جداول التقارير الاحترافية ====================

-- جدول التقارير المحفوظة
CREATE TABLE IF NOT EXISTS `professional_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `diagnostic_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `report_type` VARCHAR(50) DEFAULT 'comprehensive', -- comprehensive, executive, technical
  `report_data` LONGTEXT, -- JSON للتقرير الكامل
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `generated_at` DATETIME NOT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `viewed_at` DATETIME DEFAULT NULL,
  `download_count` INT DEFAULT 0,
  KEY `idx_diagnostic` (`diagnostic_id`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== جداول نظام التنفيذ والمتابعة ====================

-- جدول خطط التنفيذ
CREATE TABLE IF NOT EXISTS `execution_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `diagnostic_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `plan_type` ENUM('90day', 'quarterly', 'annual') NOT NULL,
  `plan_name` VARCHAR(255) DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('draft', 'active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
  `plan_data` LONGTEXT, -- JSON للخطة الكاملة
  `overall_progress` DECIMAL(5,2) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_diagnostic` (`diagnostic_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المهام التنفيذية
CREATE TABLE IF NOT EXISTS `execution_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `task_id` VARCHAR(100) NOT NULL, -- معرف فريد للمهمة
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `priority` ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
  `status` ENUM('pending', 'in_progress', 'completed', 'blocked', 'cancelled') DEFAULT 'pending',
  `phase` INT DEFAULT 1, -- المرحلة (1, 2, 3)
  `due_date` DATE DEFAULT NULL,
  `estimated_duration` VARCHAR(50) DEFAULT NULL,
  `actual_duration` VARCHAR(50) DEFAULT NULL,
  `responsible` VARCHAR(255) DEFAULT NULL,
  `task_data` TEXT, -- JSON للبيانات الإضافية
  `notes` TEXT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_plan` (`plan_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول نقاط التفتيش (Checkpoints)
CREATE TABLE IF NOT EXISTS `execution_checkpoints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `checkpoint_date` DATE NOT NULL,
  `checkpoint_type` ENUM('weekly', 'monthly', 'quarterly', 'custom') DEFAULT 'weekly',
  `status` ENUM('upcoming', 'completed', 'missed') DEFAULT 'upcoming',
  `duration_minutes` INT DEFAULT 30,
  `agenda` TEXT, -- JSON
  `outcomes` TEXT, -- JSON
  `next_steps` TEXT, -- JSON
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  KEY `idx_plan` (`plan_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_date` (`checkpoint_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول مقاييس الأداء (KPIs)
CREATE TABLE IF NOT EXISTS `execution_kpis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `kpi_name` VARCHAR(255) NOT NULL,
  `kpi_category` VARCHAR(100) DEFAULT 'general', -- financial, operational, customer, team
  `current_value` DECIMAL(12,2) DEFAULT 0,
  `target_value` DECIMAL(12,2) NOT NULL,
  `unit` VARCHAR(50) DEFAULT '%',
  `progress_percentage` DECIMAL(5,2) DEFAULT 0,
  `measurement_frequency` ENUM('daily', 'weekly', 'monthly', 'quarterly') DEFAULT 'weekly',
  `last_measured_at` DATETIME DEFAULT NULL,
  `status` ENUM('active', 'paused', 'archived') DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_plan` (`plan_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_category` (`kpi_category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول قياسات KPIs (تاريخي)
CREATE TABLE IF NOT EXISTS `kpi_measurements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kpi_id` INT NOT NULL,
  `measured_value` DECIMAL(12,2) NOT NULL,
  `progress_percentage` DECIMAL(5,2) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `measured_at` DATETIME NOT NULL,
  KEY `idx_kpi` (`kpi_id`),
  KEY `idx_measured_at` (`measured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل الأنشطة
CREATE TABLE IF NOT EXISTS `execution_activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT DEFAULT NULL,
  `client_id` INT DEFAULT NULL,
  `activity_type` VARCHAR(100) NOT NULL, -- task_updated, kpi_updated, checkpoint_completed, etc.
  `activity_data` TEXT, -- JSON
  `created_at` DATETIME NOT NULL,
  KEY `idx_plan` (`plan_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإشعارات
CREATE TABLE IF NOT EXISTS `execution_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `notification_type` VARCHAR(100) NOT NULL, -- task_due, checkpoint_upcoming, kpi_achieved, etc.
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `related_type` VARCHAR(50) DEFAULT NULL, -- task, kpi, checkpoint
  `related_id` INT DEFAULT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `sent_via` VARCHAR(50) DEFAULT NULL, -- email, sms, push, in_app
  `sent_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  KEY `idx_client` (`client_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== تحديث جدول التشخيصات الموجود ====================

-- إضافة أعمدة جديدة لجدول diagnostic_results
ALTER TABLE `diagnostic_results`
ADD COLUMN IF NOT EXISTS `professional_report` LONGTEXT COMMENT 'JSON للتقرير الاحترافي',
ADD COLUMN IF NOT EXISTS `report_generated_at` DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `has_execution_plan` BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS `execution_plan_id` INT DEFAULT NULL;

-- إضافة مفاتيح
ALTER TABLE `diagnostic_results`
ADD KEY IF NOT EXISTS `idx_execution_plan` (`execution_plan_id`),
ADD KEY IF NOT EXISTS `idx_report_generated` (`report_generated_at`);

-- ==================== Views مفيدة ====================

-- View: نظرة عامة على التقدم
CREATE OR REPLACE VIEW `v_execution_overview` AS
SELECT
    ep.id AS plan_id,
    ep.client_id,
    ep.plan_type,
    ep.start_date,
    ep.end_date,
    ep.status AS plan_status,
    ep.overall_progress,
    COUNT(DISTINCT et.id) AS total_tasks,
    SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
    SUM(CASE WHEN et.status = 'blocked' THEN 1 ELSE 0 END) AS blocked_tasks,
    SUM(CASE WHEN et.due_date < CURDATE() AND et.status != 'completed' THEN 1 ELSE 0 END) AS overdue_tasks,
    COUNT(DISTINCT ek.id) AS total_kpis,
    AVG(ek.progress_percentage) AS avg_kpi_progress
FROM
    execution_plans ep
    LEFT JOIN execution_tasks et ON ep.id = et.plan_id
    LEFT JOIN execution_kpis ek ON ep.id = ek.plan_id AND ek.status = 'active'
GROUP BY
    ep.id;

-- View: المهام العاجلة
CREATE OR REPLACE VIEW `v_urgent_tasks` AS
SELECT
    et.*,
    ep.client_id,
    ep.plan_type,
    DATEDIFF(et.due_date, CURDATE()) AS days_until_due
FROM
    execution_tasks et
    JOIN execution_plans ep ON et.plan_id = ep.id
WHERE
    et.status IN ('pending', 'in_progress')
    AND et.due_date IS NOT NULL
    AND et.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY
    et.due_date ASC,
    et.priority DESC;

-- View: نقاط التفتيش القادمة
CREATE OR REPLACE VIEW `v_upcoming_checkpoints` AS
SELECT
    ec.*,
    ep.client_id,
    ep.plan_type,
    DATEDIFF(ec.checkpoint_date, CURDATE()) AS days_until_checkpoint
FROM
    execution_checkpoints ec
    JOIN execution_plans ep ON ec.plan_id = ep.id
WHERE
    ec.status = 'upcoming'
    AND ec.checkpoint_date >= CURDATE()
ORDER BY
    ec.checkpoint_date ASC;

-- ==================== إدراج بيانات تجريبية (اختياري) ====================

-- يمكن إضافة بيانات تجريبية للاختبار

-- ==================== الفهارس المحسّنة ====================

-- فهارس مركبة لتحسين الأداء
CREATE INDEX IF NOT EXISTS `idx_task_status_due` ON `execution_tasks` (`status`, `due_date`);
CREATE INDEX IF NOT EXISTS `idx_kpi_plan_status` ON `execution_kpis` (`plan_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_checkpoint_plan_date` ON `execution_checkpoints` (`plan_id`, `checkpoint_date`);
CREATE INDEX IF NOT EXISTS `idx_notification_client_read` ON `execution_notifications` (`client_id`, `is_read`);

-- ==================== النهاية ====================

-- تسجيل التحديث
INSERT INTO `system_updates` (`update_name`, `version`, `applied_at`)
VALUES ('Professional Reports & Execution Tracking System', '1.0', NOW())
ON DUPLICATE KEY UPDATE applied_at = NOW();
