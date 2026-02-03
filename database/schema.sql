-- =====================================================
-- قاعدة بيانات موقع خالد سعد للاستشارات
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS `khaledsaad_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `khaledsaad_db`;

-- =====================================================
-- جدول المستخدمين (للإدارة)
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'editor', 'viewer') DEFAULT 'viewer',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `login_attempts` INT DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال مستخدم افتراضي (كلمة المرور: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`) VALUES
('admin', 'admin@khaledsaad.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin');

-- =====================================================
-- جدول العملاء المحتملين (Leads)
-- =====================================================
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `company` VARCHAR(100) DEFAULT NULL,
    `company_size` ENUM('1-10', '11-50', '51-200', '201-500', '500+') DEFAULT NULL,
    `industry` VARCHAR(100) DEFAULT NULL,
    `service_interested` VARCHAR(100) DEFAULT NULL,
    `budget` ENUM('less_10k', '10k_25k', '25k_50k', '50k_100k', 'more_100k') DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `source` VARCHAR(100) DEFAULT 'website',
    `utm_source` VARCHAR(100) DEFAULT NULL,
    `utm_medium` VARCHAR(100) DEFAULT NULL,
    `utm_campaign` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost') DEFAULT 'new',
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `assigned_to` INT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول فئات المدونة
-- =====================================================
DROP TABLE IF EXISTS `blog_categories`;
CREATE TABLE `blog_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT DEFAULT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    FOREIGN KEY (`parent_id`) REFERENCES `blog_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال فئات افتراضية
INSERT INTO `blog_categories` (`name`, `slug`, `description`) VALUES
('التسويق الرقمي', 'digital-marketing', 'مقالات حول استراتيجيات التسويق الرقمي'),
('التحول الرقمي', 'digital-transformation', 'مقالات حول التحول الرقمي للأعمال'),
('ريادة الأعمال', 'entrepreneurship', 'نصائح وإرشادات لرواد الأعمال'),
('دراسات حالة', 'case-studies', 'قصص نجاح ودراسات حالة'),
('أخبار وتحديثات', 'news', 'أحدث الأخبار والتحديثات');

-- =====================================================
-- جدول المقالات
-- =====================================================
DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE `blog_posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `excerpt` TEXT DEFAULT NULL,
    `content` LONGTEXT NOT NULL,
    `featured_image` VARCHAR(255) DEFAULT NULL,
    `author_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('draft', 'published', 'scheduled', 'archived') DEFAULT 'draft',
    `visibility` ENUM('public', 'private', 'password') DEFAULT 'public',
    `password` VARCHAR(255) DEFAULT NULL,
    `published_at` DATETIME DEFAULT NULL,
    `views_count` INT UNSIGNED DEFAULT 0,
    `likes_count` INT UNSIGNED DEFAULT 0,
    `comments_count` INT UNSIGNED DEFAULT 0,
    `reading_time` INT DEFAULT 0,
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT DEFAULT NULL,
    `meta_keywords` TEXT DEFAULT NULL,
    `og_image` VARCHAR(255) DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 0,
    `allow_comments` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_status` (`status`),
    INDEX `idx_published` (`published_at`),
    INDEX `idx_featured` (`is_featured`),
    FULLTEXT `ft_search` (`title`, `content`),
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `blog_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الوسوم (Tags)
-- =====================================================
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول ربط المقالات بالوسوم
-- =====================================================
DROP TABLE IF EXISTS `post_tags`;
CREATE TABLE `post_tags` (
    `post_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`),
    FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول قصص النجاح
-- =====================================================
DROP TABLE IF EXISTS `success_stories`;
CREATE TABLE `success_stories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(100) NOT NULL,
    `client_company` VARCHAR(100) DEFAULT NULL,
    `client_position` VARCHAR(100) DEFAULT NULL,
    `client_image` VARCHAR(255) DEFAULT NULL,
    `company_logo` VARCHAR(255) DEFAULT NULL,
    `industry` VARCHAR(100) DEFAULT NULL,
    `challenge` TEXT NOT NULL,
    `solution` TEXT NOT NULL,
    `results` TEXT NOT NULL,
    `testimonial` TEXT DEFAULT NULL,
    `metrics` JSON DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('draft', 'published') DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_featured` (`is_featured`),
    INDEX `idx_industry` (`industry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الاشتراك في النشرة الإخبارية
-- =====================================================
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `name` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'confirmed', 'unsubscribed') DEFAULT 'pending',
    `confirmation_token` VARCHAR(100) DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `unsubscribed_at` DATETIME DEFAULT NULL,
    `source` VARCHAR(100) DEFAULT 'website',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول نتائج التشخيص
-- =====================================================
DROP TABLE IF EXISTS `diagnostic_results`;
CREATE TABLE `diagnostic_results` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(100) NOT NULL,
    `report_token` VARCHAR(64) DEFAULT NULL,
    `full_name` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `company_name` VARCHAR(150) DEFAULT NULL,
    `industry` VARCHAR(100) DEFAULT NULL,
    `company_size` VARCHAR(50) DEFAULT NULL,
    `answers` JSON NOT NULL,
    `overall_score` INT DEFAULT 0,
    `score` INT DEFAULT 0, -- Keeping for backward compatibility
    `maturity_level` VARCHAR(100) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL, -- Keeping for backward compatibility
    `benchmark_score` INT DEFAULT 0,
    `estimated_leakage` VARCHAR(255) DEFAULT NULL,
    `lead_quality_score` INT DEFAULT 0,
    `pillars_data` JSON DEFAULT NULL,
    `recommendations_data` JSON DEFAULT NULL,
    `recommendations` JSON DEFAULT NULL, -- Keeping for backward compatibility
    `status` VARCHAR(50) DEFAULT 'pending_review',
    `scheduled_send_at` DATETIME DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_report_token` (`report_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول محادثات الشات بوت
-- =====================================================
DROP TABLE IF EXISTS `chatbot_conversations`;
CREATE TABLE `chatbot_conversations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(100) NOT NULL,
    `visitor_name` VARCHAR(100) DEFAULT NULL,
    `visitor_email` VARCHAR(255) DEFAULT NULL,
    `messages` JSON NOT NULL,
    `status` ENUM('active', 'closed', 'transferred') DEFAULT 'active',
    `satisfaction_rating` TINYINT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الخدمات
-- =====================================================
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `short_description` TEXT DEFAULT NULL,
    `full_description` LONGTEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `features` JSON DEFAULT NULL,
    `price_from` DECIMAL(10,2) DEFAULT NULL,
    `price_to` DECIMAL(10,2) DEFAULT NULL,
    `duration` VARCHAR(50) DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال الخدمات الافتراضية
INSERT INTO `services` (`name`, `slug`, `short_description`, `icon`, `is_featured`, `sort_order`) VALUES
('الاستشارات التسويقية', 'marketing-consulting', 'استراتيجيات تسويقية مخصصة لنمو أعمالك', 'fa-chart-line', 1, 1),
('التحول الرقمي', 'digital-transformation', 'رقمنة العمليات وتحسين الكفاءة التشغيلية', 'fa-laptop-code', 1, 2),
('بناء الهوية التجارية', 'branding', 'تصميم هوية تجارية مميزة ومؤثرة', 'fa-palette', 1, 3),
('التدريب والتطوير', 'training', 'برامج تدريبية لتطوير فريقك ومهاراتهم', 'fa-users', 1, 4);

-- =====================================================
-- جدول باقات الأسعار
-- =====================================================
DROP TABLE IF EXISTS `pricing_plans`;
CREATE TABLE `pricing_plans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `billing_cycle` ENUM('one_time', 'monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `features` JSON NOT NULL,
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_popular` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال باقات الأسعار
INSERT INTO `pricing_plans` (`name`, `slug`, `description`, `price`, `billing_cycle`, `features`, `is_featured`, `is_popular`, `sort_order`) VALUES
('باقة الانطلاق', 'starter', 'مثالية للشركات الناشئة', 5000.00, 'monthly', '["تحليل السوق الأولي", "استراتيجية تسويق أساسية", "تقرير شهري", "دعم عبر البريد", "جلسة استشارية واحدة"]', 1, 0, 1),
('باقة النمو', 'growth', 'للشركات في مرحلة التوسع', 10000.00, 'monthly', '["تحليل شامل للسوق", "استراتيجية تسويق متكاملة", "تقارير أسبوعية", "دعم على مدار الساعة", "4 جلسات استشارية شهرياً", "إدارة الحملات الإعلانية"]', 1, 1, 2),
('باقة المؤسسات', 'enterprise', 'حلول مخصصة للمؤسسات الكبيرة', 25000.00, 'monthly', '["تحليل معمق للسوق والمنافسين", "استراتيجية شاملة ومخصصة", "تقارير يومية", "فريق دعم مخصص", "جلسات استشارية غير محدودة", "إدارة كاملة للحملات", "تدريب الفريق", "أولوية في الدعم"]', 1, 0, 3);

-- =====================================================
-- جدول الإعدادات
-- =====================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` LONGTEXT DEFAULT NULL,
    `setting_type` ENUM('text', 'textarea', 'number', 'boolean', 'json', 'image') DEFAULT 'text',
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`),
    INDEX `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدخال الإعدادات الافتراضية
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('site_name', 'خالد سعد للاستشارات', 'text', 'general', 'اسم الموقع'),
('site_tagline', 'شريكك في التحول الرقمي', 'text', 'general', 'الشعار'),
('site_email', 'info@khaledsaad.com', 'text', 'general', 'البريد الإلكتروني'),
('site_phone', '+966 50 000 0000', 'text', 'general', 'رقم الهاتف'),
('site_address', 'الرياض، المملكة العربية السعودية', 'textarea', 'general', 'العنوان'),
('social_twitter', 'https://twitter.com/khaledsaad', 'text', 'social', 'رابط تويتر'),
('social_linkedin', 'https://linkedin.com/in/khaledsaad', 'text', 'social', 'رابط لينكدإن'),
('social_instagram', 'https://instagram.com/khaledsaad', 'text', 'social', 'رابط انستغرام'),
('promo_active', '1', 'boolean', 'promo', 'تفعيل العرض الترويجي'),
('promo_discount', '20', 'number', 'promo', 'نسبة الخصم'),
('promo_message', 'خصم 20% للشهر الأول', 'text', 'promo', 'رسالة العرض');

-- =====================================================
-- جدول سجل النشاطات
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول تتبع الزوار والتحويلات
-- =====================================================
DROP TABLE IF EXISTS `analytics`;
CREATE TABLE `analytics` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(100) NOT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `page_title` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `utm_source` VARCHAR(100) DEFAULT NULL,
    `utm_medium` VARCHAR(100) DEFAULT NULL,
    `utm_campaign` VARCHAR(100) DEFAULT NULL,
    `device_type` ENUM('desktop', 'tablet', 'mobile') DEFAULT NULL,
    `browser` VARCHAR(50) DEFAULT NULL,
    `os` VARCHAR(50) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `time_on_page` INT DEFAULT 0,
    `is_bounce` TINYINT(1) DEFAULT 0,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_session` (`session_id`),
    INDEX `idx_page` (`page_url`(191)),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول معدل الطلبات (Rate Limiting)
-- =====================================================
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(255) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `requests_count` INT DEFAULT 1,
    `window_start` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_identifier_endpoint` (`identifier`, `endpoint`),
    INDEX `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول العملاء المسجلين (Clients)
-- =====================================================
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `company` VARCHAR(100) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_token` VARCHAR(100) DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_token_expires` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `login_attempts` INT DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `preferences` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول الاستشارات والمواعيد
-- =====================================================
DROP TABLE IF EXISTS `consultations`;
CREATE TABLE `consultations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `duration` INT DEFAULT 60,
    `meeting_link` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rescheduled') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `client_notes` TEXT DEFAULT NULL,
    `rating` TINYINT DEFAULT NULL,
    `feedback` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) DEFAULT NULL,
    `paid` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_client` (`client_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_at`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول المقالات المحفوظة
-- =====================================================
DROP TABLE IF EXISTS `saved_posts`;
CREATE TABLE `saved_posts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT UNSIGNED NOT NULL,
    `post_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_client_post` (`client_id`, `post_id`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- تحديث جدول نتائج التشخيص لربطه بالعملاء
-- =====================================================
ALTER TABLE `diagnostic_results` ADD COLUMN `client_id` INT UNSIGNED DEFAULT NULL AFTER `id`;
ALTER TABLE `diagnostic_results` ADD INDEX `idx_client` (`client_id`);
ALTER TABLE `diagnostic_results` ADD FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
