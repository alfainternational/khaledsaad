-- تحديث بيانات مستخدم الآدمن
-- استخدم هذا الملف لتحديث بيانات المستخدم في قاعدة البيانات الموجودة

USE `khaledsaad_db`;

-- حذف المستخدم القديم إن وجد
DELETE FROM `users` WHERE `email` = 'admin@khaledsaad.com';

-- إضافة مستخدم جديد بكلمة مرور صحيحة
-- البريد: admin@khaledsaad.com
-- كلمة المرور: admin123
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `is_active`, `login_attempts`, `locked_until`) VALUES
('admin', 'admin@khaledsaad.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'خالد سعد', 'admin', 1, 0, NULL);

-- التحقق من البيانات
SELECT id, username, email, full_name, role, is_active, created_at FROM users WHERE email = 'admin@khaledsaad.com';
