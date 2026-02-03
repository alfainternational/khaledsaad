<?php
/**
 * API نظام الذكاء الاصطناعي الخبير
 * Advanced AI Expert System API Endpoint
 * v7.0
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/ai_expert_system.php';
require_once __DIR__ . '/../includes/behavioral_analytics.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من الصلاحيات (للإدارة فقط)
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $aiExpert = new AIExpertSystem();
    $behavioralAnalytics = new BehavioralAnalytics();

    switch ($action) {

        // تحليل متقدم لحالة معينة
        case 'advanced_analysis':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                throw new Exception('No data provided');
            }

            $result = $aiExpert->advancedAnalysis($data);

            echo json_encode([
                'success' => true,
                'analysis' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // تحليل سلوك مستخدم
        case 'analyze_user_behavior':
            $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
            $timeframe = $_GET['timeframe'] ?? $_POST['timeframe'] ?? '30days';

            $result = $behavioralAnalytics->analyzeUserBehavior($userId, $timeframe);

            echo json_encode([
                'success' => true,
                'behavioral_analysis' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // الحصول على مقاييس الأداء
        case 'get_performance_metrics':
            $metrics = $aiExpert->getPerformanceMetrics();

            echo json_encode([
                'success' => true,
                'metrics' => $metrics
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // الحصول على إحصائيات التعلم
        case 'get_learning_stats':
            $stats = $aiExpert->getLearningStats();

            echo json_encode([
                'success' => true,
                'learning_stats' => $stats
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // تحليل شامل للنظام
        case 'system_analysis':
            $performanceMetrics = $aiExpert->getPerformanceMetrics();
            $learningStats = $aiExpert->getLearningStats();

            // إحصائيات قاعدة البيانات
            $dbStats = [
                'total_diagnostics' => db()->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results")['c'],
                'total_users' => db()->fetchOne("SELECT COUNT(DISTINCT user_id) as c FROM user_activity_logs")['c'],
                'total_patterns' => db()->fetchOne("SELECT COUNT(*) as c FROM learned_patterns")['c'],
                'total_predictions' => db()->fetchOne("SELECT COUNT(*) as c FROM ai_predictions")['c']
            ];

            echo json_encode([
                'success' => true,
                'system_health' => [
                    'status' => 'operational',
                    'version' => '7.0',
                    'ai_engine' => 'AIExpertSystem',
                    'last_update' => $learningStats['last_update'] ?? 'N/A'
                ],
                'performance_metrics' => $performanceMetrics,
                'learning_stats' => $learningStats,
                'database_stats' => $dbStats
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // تحليل تشخيص معين باستخدام الذكاء الخبير
        case 'reanalyze_diagnostic':
            $diagnosticId = $_GET['diagnostic_id'] ?? $_POST['diagnostic_id'] ?? null;

            if (!$diagnosticId) {
                throw new Exception('Diagnostic ID required');
            }

            $diagnostic = db()->fetchOne("SELECT * FROM diagnostic_results WHERE id = ?", [$diagnosticId]);

            if (!$diagnostic) {
                throw new Exception('Diagnostic not found');
            }

            // إعداد البيانات للتحليل
            $data = [
                'industry' => $diagnostic['industry'],
                'company_size' => $diagnostic['company_size'],
                'overall_score' => $diagnostic['overall_score'],
                'answers' => json_decode($diagnostic['answers'], true)
            ];

            $result = $aiExpert->advancedAnalysis($data);

            // تحديث التشخيص بالتحليل الجديد
            db()->update('diagnostic_results', [
                'ai_analysis_v7' => json_encode($result),
                'ai_confidence_score' => $result['confidence_score'] ?? 0
            ], ['id' => $diagnosticId]);

            echo json_encode([
                'success' => true,
                'diagnostic_id' => $diagnosticId,
                'advanced_analysis' => $result,
                'updated' => true
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // تصدير بيانات التعلم
        case 'export_learning_data':
            $learningDataPath = __DIR__ . '/../includes/ai_learning_data.json';

            if (!file_exists($learningDataPath)) {
                throw new Exception('Learning data not found');
            }

            $learningData = json_decode(file_get_contents($learningDataPath), true);

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="ai_learning_data_export_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($learningData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;

        // الحصول على توصيات ذكية للنظام
        case 'get_system_recommendations':
            // تحليل الأداء العام وتقديم توصيات
            $performanceMetrics = $aiExpert->getPerformanceMetrics();
            $learningStats = $aiExpert->getLearningStats();

            $recommendations = [];

            // توصيات بناءً على مقاييس الأداء
            if (($performanceMetrics['accuracy_rate'] ?? 0) < 70) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'accuracy',
                    'recommendation' => 'معدل الدقة منخفض - يحتاج النظام لمزيد من البيانات التدريبية',
                    'action' => 'زيادة عدد الحالات المحللة وتحسين جودة البيانات'
                ];
            }

            if (($learningStats['learned_patterns_count'] ?? 0) < 50) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'learning',
                    'recommendation' => 'عدد الأنماط المتعلمة قليل - النظام بحاجة لمزيد من التدريب',
                    'action' => 'السماح للنظام بتحليل المزيد من الحالات'
                ];
            }

            if (empty($recommendations)) {
                $recommendations[] = [
                    'priority' => 'info',
                    'category' => 'status',
                    'recommendation' => 'النظام يعمل بكفاءة عالية',
                    'action' => 'متابعة المراقبة الدورية'
                ];
            }

            echo json_encode([
                'success' => true,
                'recommendations' => $recommendations
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
