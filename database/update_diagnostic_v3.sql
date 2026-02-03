-- =====================================================
-- تحديث جدول نتائج التشخيص للإصدار 3.0 (Smart Logic)
-- =====================================================

USE `khaledsaad_db`;

ALTER TABLE `diagnostic_results` 
ADD COLUMN `full_name` VARCHAR(100) AFTER `email`,
ADD COLUMN `phone` VARCHAR(20) AFTER `full_name`,
ADD COLUMN `company_name` VARCHAR(100) AFTER `phone`,
ADD COLUMN `industry` VARCHAR(100) AFTER `company_name`,
ADD COLUMN `company_size` VARCHAR(50) AFTER `industry`,
ADD COLUMN `benchmark_score` INT DEFAULT 0 AFTER `score`,
ADD COLUMN `estimated_leakage` VARCHAR(100) AFTER `benchmark_score`,
ADD COLUMN `lead_quality_score` INT DEFAULT 0 AFTER `estimated_leakage`,
ADD COLUMN `pillars_data` JSON AFTER `lead_quality_score`,
CHANGE COLUMN `score` `overall_score` INT DEFAULT 0,
CHANGE COLUMN `category` `maturity_level` VARCHAR(100) DEFAULT NULL,
CHANGE COLUMN `recommendations` `recommendations_data` JSON DEFAULT NULL;
