<?php
/**
 * تحديث نظام الذكاء الاصطناعي إلى الإصدار 7.0
 * Advanced AI Expert System Update Script
 */

require_once __DIR__ . '/../includes/init.php';

echo "=== تحديث نظام الذكاء الاصطناعي الخبير v7.0 ===\n\n";

// 1. إنشاء الجداول الجديدة
echo "[1/5] إنشاء جداول قاعدة البيانات...\n";

$tables = [
    // جدول التحليلات السلوكية
    "CREATE TABLE IF NOT EXISTS behavioral_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NULL,
        analysis_data LONGTEXT,
        segment VARCHAR(50),
        conversion_score DECIMAL(5,2) DEFAULT 0,
        risk_score DECIMAL(5,2) DEFAULT 0,
        predicted_ltv DECIMAL(10,2) DEFAULT 0,
        analyzed_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_segment (segment),
        INDEX idx_analyzed_at (analyzed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // جدول الأنماط المتعلمة
    "CREATE TABLE IF NOT EXISTS learned_patterns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pattern_id VARCHAR(100) UNIQUE,
        pattern_type VARCHAR(50),
        industry VARCHAR(100),
        company_size VARCHAR(50),
        score_range VARCHAR(20),
        pattern_data LONGTEXT,
        success_rate DECIMAL(5,2) DEFAULT 0,
        confidence DECIMAL(5,2) DEFAULT 0,
        usage_count INT DEFAULT 0,
        last_used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pattern_type (pattern_type),
        INDEX idx_industry (industry),
        INDEX idx_success_rate (success_rate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // جدول مقاييس أداء الذكاء الاصطناعي
    "CREATE TABLE IF NOT EXISTS ai_performance_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_date DATE UNIQUE,
        accuracy_rate DECIMAL(5,2) DEFAULT 0,
        prediction_success_rate DECIMAL(5,2) DEFAULT 0,
        customer_satisfaction_score DECIMAL(5,2) DEFAULT 0,
        conversion_improvement DECIMAL(5,2) DEFAULT 0,
        total_analyzed_cases INT DEFAULT 0,
        successful_recommendations INT DEFAULT 0,
        learning_iterations INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_metric_date (metric_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // جدول سجل التحسينات الذاتية
    "CREATE TABLE IF NOT EXISTS ai_self_improvements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        improvement_type VARCHAR(100),
        description TEXT,
        confidence_score DECIMAL(5,2),
        impact_level ENUM('low', 'medium', 'high', 'critical'),
        status ENUM('proposed', 'applied', 'tested', 'validated', 'rolled_back') DEFAULT 'proposed',
        improvement_data LONGTEXT,
        before_metrics TEXT,
        after_metrics TEXT,
        applied_at DATETIME NULL,
        validated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_impact_level (impact_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // جدول التغذية من API
    "CREATE TABLE IF NOT EXISTS api_training_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) UNIQUE,
        api_provider VARCHAR(50) DEFAULT 'Z.AI',
        input_data LONGTEXT,
        output_data LONGTEXT,
        quality_score DECIMAL(5,2) DEFAULT 0,
        feedback_score DECIMAL(5,2) NULL,
        used_for_training TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_provider (api_provider),
        INDEX idx_quality_score (quality_score),
        INDEX idx_used_for_training (used_for_training)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // جدول تتبع التنبؤات
    "CREATE TABLE IF NOT EXISTS ai_predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prediction_id VARCHAR(100) UNIQUE,
        user_id VARCHAR(255) NULL,
        prediction_type VARCHAR(100),
        predicted_outcome TEXT,
        predicted_probability DECIMAL(5,2),
        predicted_at DATETIME,
        actual_outcome TEXT NULL,
        accuracy_score DECIMAL(5,2) NULL,
        validated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_prediction_type (prediction_type),
        INDEX idx_predicted_at (predicted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($tables as $sql) {
    try {
        db()->execute($sql);
        echo "  ✓ تم إنشاء الجدول بنجاح\n";
    } catch (Exception $e) {
        echo "  ✗ خطأ: " . $e->getMessage() . "\n";
    }
}

// 2. تحديث الجداول الموجودة
echo "\n[2/5] تحديث الجداول الموجودة...\n";

$alterQueries = [
    // إضافة أعمدة جديدة للجدول user_activity_logs
    "ALTER TABLE user_activity_logs
     ADD COLUMN IF NOT EXISTS session_id VARCHAR(100) AFTER user_id,
     ADD COLUMN IF NOT EXISTS device_type VARCHAR(50) NULL,
     ADD COLUMN IF NOT EXISTS browser VARCHAR(100) NULL,
     ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL,
     ADD INDEX IF NOT EXISTS idx_session_id (session_id);",

    // إضافة أعمدة جديدة للجدول diagnostic_results
    "ALTER TABLE diagnostic_results
     ADD COLUMN IF NOT EXISTS ai_analysis_v7 LONGTEXT NULL,
     ADD COLUMN IF NOT EXISTS behavioral_insights LONGTEXT NULL,
     ADD COLUMN IF NOT EXISTS predicted_success_rate DECIMAL(5,2) NULL,
     ADD COLUMN IF NOT EXISTS ai_confidence_score DECIMAL(5,2) NULL;",

    // إضافة أعمدة جديدة للجدول kb_ai_proposals
    "ALTER TABLE kb_ai_proposals
     ADD COLUMN IF NOT EXISTS auto_applied TINYINT(1) DEFAULT 0,
     ADD COLUMN IF NOT EXISTS impact_analysis TEXT NULL,
     ADD COLUMN IF NOT EXISTS validation_status ENUM('pending', 'success', 'failed') NULL;"
];

foreach ($alterQueries as $sql) {
    try {
        db()->execute($sql);
        echo "  ✓ تم تحديث الجدول بنجاح\n";
    } catch (Exception $e) {
        // قد يفشل إذا كان العمود موجوداً مسبقاً
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "  ⚠ تحذير: " . $e->getMessage() . "\n";
        } else {
            echo "  ✓ العمود موجود مسبقاً\n";
        }
    }
}

// 3. إنشاء الملفات الأساسية
echo "\n[3/5] إنشاء ملفات التعلم الأساسية...\n";

$files = [
    __DIR__ . '/../includes/ai_learning_data.json' => [
        'version' => '7.0',
        'last_update' => date('Y-m-d H:i:s'),
        'total_learning_cycles' => 0,
        'learned_patterns' => [],
        'behavioral_insights' => [],
        'optimization_history' => [],
        'api_training_data' => [],
        'performance_improvements' => [],
        'adaptive_weights' => [
            'strategy' => 0.25,
            'marketing' => 0.25,
            'tech' => 0.25,
            'operations' => 0.25
        ]
    ],
    __DIR__ . '/../includes/ai_performance_metrics.json' => [
        'accuracy_rate' => 0,
        'prediction_success_rate' => 0,
        'customer_satisfaction_score' => 0,
        'conversion_improvement' => 0,
        'total_analyzed_cases' => 0,
        'successful_recommendations' => 0,
        'learning_iterations' => 0,
        'last_update' => date('Y-m-d H:i:s')
    ],
    __DIR__ . '/../includes/behavioral_insights.json' => [
        'version' => '3.0',
        'last_analysis' => null,
        'user_segments' => [],
        'conversion_patterns' => [],
        'engagement_metrics' => [],
        'behavioral_triggers' => [],
        'predictive_models' => []
    ]
];

foreach ($files as $path => $data) {
    if (!file_exists($path)) {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  ✓ تم إنشاء: " . basename($path) . "\n";
    } else {
        echo "  ⚠ موجود مسبقاً: " . basename($path) . "\n";
    }
}

// 4. إنشاء مجلد النسخ الاحتياطية
echo "\n[4/5] إنشاء مجلدات النظام...\n";

$directories = [
    __DIR__ . '/../logs/ai_learning',
    __DIR__ . '/../logs/ai_performance',
    __DIR__ . '/../backups/knowledge_base',
    __DIR__ . '/../backups/ai_models'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  ✓ تم إنشاء: " . basename($dir) . "\n";
    } else {
        echo "  ⚠ موجود مسبقاً: " . basename($dir) . "\n";
    }
}

// 5. إدراج بيانات أولية للاختبار
echo "\n[5/5] إدراج بيانات أولية...\n";

try {
    // إدراج مقاييس أداء أولية
    db()->execute("
        INSERT IGNORE INTO ai_performance_metrics (metric_date, accuracy_rate, prediction_success_rate)
        VALUES (CURDATE(), 0, 0)
    ");
    echo "  ✓ تم إدراج مقاييس الأداء الأولية\n";

    // إدراج نمط تعلم تجريبي
    db()->execute("
        INSERT IGNORE INTO learned_patterns (pattern_id, pattern_type, industry, pattern_data)
        VALUES (
            'pattern_initial_001',
            'conversion',
            'tech',
            '{\"description\": \"نمط تجريبي أولي\", \"success_rate\": 0.75}'
        )
    ");
    echo "  ✓ تم إدراج نمط التعلم التجريبي\n";

} catch (Exception $e) {
    echo "  ⚠ تحذير: " . $e->getMessage() . "\n";
}

echo "\n=== ✓ اكتمل التحديث بنجاح! ===\n";
echo "\nملاحظات مهمة:\n";
echo "- تم ترقية النظام إلى الإصدار 7.0\n";
echo "- نظام التعلم الآلي جاهز للعمل\n";
echo "- نظام التحليلات السلوكية مفعّل\n";
echo "- نظام التطوير الذاتي مفعّل\n\n";

echo "الخطوة التالية:\n";
echo "- تأكد من وجود ZAI_API_KEY في config.php\n";
echo "- راجع ملفات التعلم في /includes/\n";
echo "- راقب الأداء في /logs/ai_learning/\n\n";
