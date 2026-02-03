<?php
/**
 * Behavioral Analytics Engine - محرك تحليل السلوكيات المتقدم
 * v3.0 - Advanced User Behavior Analysis & Pattern Recognition
 *
 * نظام تحليل متقدم للسلوكيات والأنماط مع التعلم الآلي
 */

class BehavioralAnalytics {

    private $db;
    private $analyticsDataPath;
    private $insightsCache = [];

    public function __construct() {
        $this->db = db();
        $this->analyticsDataPath = __DIR__ . '/behavioral_insights.json';
        $this->initializeAnalytics();
    }

    /**
     * تهيئة نظام التحليلات
     */
    private function initializeAnalytics() {
        if (!file_exists($this->analyticsDataPath)) {
            $initialData = [
                'version' => '3.0',
                'last_analysis' => null,
                'user_segments' => [],
                'conversion_patterns' => [],
                'engagement_metrics' => [],
                'behavioral_triggers' => [],
                'predictive_models' => []
            ];
            file_put_contents($this->analyticsDataPath, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * تحليل شامل للسلوك
     */
    public function analyzeUserBehavior($userId = null, $timeframe = '30days') {
        $analysis = [
            'user_journey' => $this->analyzeUserJourney($userId, $timeframe),
            'engagement_patterns' => $this->analyzeEngagementPatterns($userId, $timeframe),
            'conversion_signals' => $this->identifyConversionSignals($userId),
            'risk_indicators' => $this->identifyRiskIndicators($userId),
            'recommendations' => $this->generateBehavioralRecommendations($userId),
            'predicted_actions' => $this->predictNextActions($userId),
            'segment' => $this->identifyUserSegment($userId),
            'lifetime_value_prediction' => $this->predictLifetimeValue($userId)
        ];

        // حفظ التحليل
        $this->saveBehavioralAnalysis($userId, $analysis);

        return $analysis;
    }

    /**
     * تحليل رحلة المستخدم
     */
    private function analyzeUserJourney($userId, $timeframe) {
        $timeCondition = $this->getTimeframeCondition($timeframe);

        $query = "
            SELECT event_type, page_url, meta_data, created_at
            FROM user_activity_logs
            " . ($userId ? "WHERE user_id = ? AND " : "WHERE ") . $timeCondition . "
            ORDER BY created_at ASC
            LIMIT 1000
        ";

        $params = $userId ? [$userId] : [];
        $activities = $this->db->fetchAll($query, $params);

        if (empty($activities)) {
            return ['status' => 'no_data', 'journey' => []];
        }

        // تحليل المسار
        $journey = [
            'entry_point' => $this->identifyEntryPoint($activities),
            'key_milestones' => $this->extractKeyMilestones($activities),
            'exit_point' => $this->identifyExitPoint($activities),
            'total_touchpoints' => count($activities),
            'avg_session_duration' => $this->calculateAverageSessionDuration($activities),
            'bounce_rate' => $this->calculateBounceRate($activities),
            'conversion_path' => $this->identifyConversionPath($activities),
            'drop_off_points' => $this->identifyDropOffPoints($activities)
        ];

        return $journey;
    }

    /**
     * تحليل أنماط التفاعل
     */
    private function analyzeEngagementPatterns($userId, $timeframe) {
        $timeCondition = $this->getTimeframeCondition($timeframe);

        $query = "
            SELECT event_type, COUNT(*) as count, AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_duration
            FROM user_activity_logs
            " . ($userId ? "WHERE user_id = ? AND " : "WHERE ") . $timeCondition . "
            GROUP BY event_type
            ORDER BY count DESC
        ";

        $params = $userId ? [$userId] : [];
        $patterns = $this->db->fetchAll($query, $params);

        return [
            'most_common_actions' => array_slice($patterns, 0, 5),
            'engagement_score' => $this->calculateEngagementScore($patterns),
            'interaction_frequency' => $this->calculateInteractionFrequency($userId, $timeframe),
            'peak_activity_times' => $this->identifyPeakActivityTimes($userId, $timeframe),
            'feature_usage' => $this->analyzeFeatureUsage($userId, $timeframe)
        ];
    }

    /**
     * تحديد إشارات التحويل
     */
    private function identifyConversionSignals($userId) {
        $signals = [];

        // إشارات إيجابية
        $positiveSignals = [
            'completed_diagnostic' => $this->checkDiagnosticCompletion($userId),
            'multiple_visits' => $this->checkMultipleVisits($userId),
            'time_on_site' => $this->checkTimeOnSite($userId),
            'page_depth' => $this->checkPageDepth($userId),
            'engaged_with_content' => $this->checkContentEngagement($userId),
            'contacted_support' => $this->checkSupportContact($userId)
        ];

        // إشارات سلبية
        $negativeSignals = [
            'high_bounce_rate' => $this->checkBounceRate($userId),
            'abandoned_forms' => $this->checkFormAbandonment($userId),
            'short_sessions' => $this->checkShortSessions($userId),
            'no_engagement' => $this->checkNoEngagement($userId)
        ];

        // حساب نقاط التحويل
        $conversionScore = 0;

        foreach ($positiveSignals as $signal => $value) {
            if ($value) {
                $conversionScore += 10;
                $signals['positive'][] = $signal;
            }
        }

        foreach ($negativeSignals as $signal => $value) {
            if ($value) {
                $conversionScore -= 5;
                $signals['negative'][] = $signal;
            }
        }

        $signals['conversion_score'] = max(0, min(100, $conversionScore));
        $signals['conversion_likelihood'] = $this->categorizeConversionLikelihood($signals['conversion_score']);

        return $signals;
    }

    /**
     * تحديد مؤشرات الخطر
     */
    private function identifyRiskIndicators($userId) {
        $risks = [
            'churn_risk' => 0,
            'disengagement_risk' => 0,
            'technical_issues_risk' => 0,
            'satisfaction_risk' => 0,
            'indicators' => []
        ];

        // تحليل مخاطر التوقف
        if ($this->checkInactivity($userId, 7)) {
            $risks['churn_risk'] += 30;
            $risks['indicators'][] = 'inactive_7_days';
        }

        if ($this->checkDecreasingEngagement($userId)) {
            $risks['disengagement_risk'] += 25;
            $risks['indicators'][] = 'decreasing_engagement';
        }

        if ($this->checkErrorPatterns($userId)) {
            $risks['technical_issues_risk'] += 20;
            $risks['indicators'][] = 'technical_errors';
        }

        if ($this->checkNegativeFeedback($userId)) {
            $risks['satisfaction_risk'] += 35;
            $risks['indicators'][] = 'negative_feedback';
        }

        $risks['overall_risk_score'] = round((
            $risks['churn_risk'] +
            $risks['disengagement_risk'] +
            $risks['technical_issues_risk'] +
            $risks['satisfaction_risk']
        ) / 4, 2);

        $risks['risk_level'] = $this->categorizeRiskLevel($risks['overall_risk_score']);

        return $risks;
    }

    /**
     * توليد توصيات سلوكية
     */
    private function generateBehavioralRecommendations($userId) {
        $recommendations = [];

        $journey = $this->analyzeUserJourney($userId, '7days');
        $engagement = $this->analyzeEngagementPatterns($userId, '7days');
        $conversionSignals = $this->identifyConversionSignals($userId);
        $risks = $this->identifyRiskIndicators($userId);

        // توصيات بناءً على نقاط التحويل
        if ($conversionSignals['conversion_score'] > 60) {
            $recommendations[] = [
                'type' => 'high_conversion_potential',
                'priority' => 'high',
                'action' => 'إرسال عرض خاص أو دعوة لاستشارة مجانية',
                'timing' => 'immediate',
                'expected_impact' => 'high'
            ];
        }

        // توصيات بناءً على المخاطر
        if ($risks['overall_risk_score'] > 50) {
            $recommendations[] = [
                'type' => 'retention',
                'priority' => 'urgent',
                'action' => 'تدخل فوري للاحتفاظ بالعميل - رسالة شخصية أو عرض خاص',
                'timing' => 'within_24h',
                'expected_impact' => 'critical'
            ];
        }

        // توصيات بناءً على التفاعل
        if (($engagement['engagement_score'] ?? 0) < 30) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'medium',
                'action' => 'إرسال محتوى تعليمي أو دعوة لحدث',
                'timing' => 'within_week',
                'expected_impact' => 'medium'
            ];
        }

        // توصيات بناءً على نقاط الانقطاع
        if (!empty($journey['drop_off_points'])) {
            $recommendations[] = [
                'type' => 'ux_improvement',
                'priority' => 'medium',
                'action' => 'تحسين تجربة المستخدم في نقاط الانقطاع المحددة',
                'timing' => 'strategic_planning',
                'expected_impact' => 'long_term'
            ];
        }

        return $recommendations;
    }

    /**
     * التنبؤ بالإجراءات القادمة
     */
    private function predictNextActions($userId) {
        $analyticsData = $this->loadAnalyticsData();
        $userHistory = $this->getUserHistory($userId);

        if (empty($userHistory)) {
            return ['predictions' => [], 'confidence' => 0];
        }

        $predictions = [];

        // التنبؤ بناءً على الأنماط المتعلمة
        if (!empty($analyticsData['conversion_patterns'])) {
            $matchingPatterns = $this->findMatchingBehaviorPatterns($userHistory, $analyticsData['conversion_patterns']);

            foreach ($matchingPatterns as $pattern) {
                $predictions[] = [
                    'action' => $pattern['next_action'],
                    'probability' => $pattern['probability'],
                    'timeframe' => $pattern['expected_timeframe'],
                    'based_on' => 'historical_pattern'
                ];
            }
        }

        // التنبؤ بناءً على التعلم الآلي
        $mlPrediction = $this->mlBasedPrediction($userHistory);
        if (!empty($mlPrediction)) {
            $predictions = array_merge($predictions, $mlPrediction);
        }

        // ترتيب حسب الاحتمالية
        usort($predictions, function($a, $b) {
            return ($b['probability'] ?? 0) <=> ($a['probability'] ?? 0);
        });

        $avgConfidence = 0;
        if (!empty($predictions)) {
            $avgConfidence = array_sum(array_column($predictions, 'probability')) / count($predictions);
        }

        return [
            'predictions' => array_slice($predictions, 0, 5),
            'confidence' => round($avgConfidence, 2)
        ];
    }

    /**
     * تحديد شريحة المستخدم
     */
    private function identifyUserSegment($userId) {
        $engagement = $this->analyzeEngagementPatterns($userId, '30days');
        $conversionSignals = $this->identifyConversionSignals($userId);

        $engagementScore = $engagement['engagement_score'] ?? 0;
        $conversionScore = $conversionSignals['conversion_score'] ?? 0;

        // تصنيف الشرائح
        if ($conversionScore >= 70 && $engagementScore >= 70) {
            return ['segment' => 'hot_lead', 'priority' => 'urgent', 'description' => 'عميل محتمل ساخن - جاهز للتحويل'];
        } elseif ($conversionScore >= 50 && $engagementScore >= 50) {
            return ['segment' => 'warm_lead', 'priority' => 'high', 'description' => 'عميل محتمل دافئ - يحتاج لرعاية'];
        } elseif ($conversionScore >= 30 || $engagementScore >= 30) {
            return ['segment' => 'cold_lead', 'priority' => 'medium', 'description' => 'عميل محتمل بارد - يحتاج لتفعيل'];
        } else {
            return ['segment' => 'passive', 'priority' => 'low', 'description' => 'زائر سلبي - يحتاج لجذب'];
        }
    }

    /**
     * التنبؤ بالقيمة الدائمة للعميل
     */
    private function predictLifetimeValue($userId) {
        $diagnosticCompleted = $this->checkDiagnosticCompletion($userId);
        $engagement = $this->analyzeEngagementPatterns($userId, '30days');
        $conversionSignals = $this->identifyConversionSignals($userId);

        $baseValue = 0;

        // حساب القيمة المتوقعة
        if ($diagnosticCompleted) {
            $baseValue += 500; // قيمة إكمال التشخيص
        }

        $baseValue += ($engagement['engagement_score'] ?? 0) * 10;
        $baseValue += ($conversionSignals['conversion_score'] ?? 0) * 15;

        // عامل الثقة
        $confidence = min(100, ($engagement['engagement_score'] ?? 0) + ($conversionSignals['conversion_score'] ?? 0)) / 2;

        return [
            'predicted_ltv' => round($baseValue, 2),
            'confidence' => round($confidence, 2),
            'currency' => 'SAR',
            'timeframe' => '12_months'
        ];
    }

    /**
     * حفظ التحليل السلوكي
     */
    private function saveBehavioralAnalysis($userId, $analysis) {
        try {
            $this->db->insert('behavioral_analytics', [
                'user_id' => $userId,
                'analysis_data' => json_encode($analysis),
                'segment' => $analysis['segment']['segment'] ?? 'unknown',
                'conversion_score' => $analysis['conversion_signals']['conversion_score'] ?? 0,
                'risk_score' => $analysis['risk_indicators']['overall_risk_score'] ?? 0,
                'predicted_ltv' => $analysis['lifetime_value_prediction']['predicted_ltv'] ?? 0,
                'analyzed_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error saving behavioral analysis: " . $e->getMessage());
        }
    }

    // ==================== Helper Methods ====================

    private function getTimeframeCondition($timeframe) {
        $days = (int)filter_var($timeframe, FILTER_SANITIZE_NUMBER_INT);
        return "created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
    }

    private function identifyEntryPoint($activities) {
        return $activities[0] ?? null;
    }

    private function extractKeyMilestones($activities) {
        $milestones = [];
        foreach ($activities as $activity) {
            if (in_array($activity['event_type'], ['diagnostic_start', 'diagnostic_complete', 'contact_form', 'booking'])) {
                $milestones[] = $activity;
            }
        }
        return $milestones;
    }

    private function identifyExitPoint($activities) {
        return end($activities) ?: null;
    }

    private function calculateAverageSessionDuration($activities) {
        // تنفيذ مبسط - يمكن تطويره
        return count($activities) * 45; // ثانية
    }

    private function calculateBounceRate($activities) {
        if (count($activities) <= 1) {
            return 100;
        }
        return 0;
    }

    private function identifyConversionPath($activities) {
        $path = [];
        foreach ($activities as $activity) {
            if ($activity['event_type'] === 'diagnostic_complete') {
                $path['converted'] = true;
                $path['conversion_point'] = $activity;
            }
        }
        return $path;
    }

    private function identifyDropOffPoints($activities) {
        // تحليل نقاط الانقطاع
        return [];
    }

    private function calculateEngagementScore($patterns) {
        $score = min(100, count($patterns) * 10);
        return $score;
    }

    private function calculateInteractionFrequency($userId, $timeframe) {
        return ['frequency' => 'medium']; // مبسط
    }

    private function identifyPeakActivityTimes($userId, $timeframe) {
        return ['peak_hours' => [10, 14, 20]]; // مبسط
    }

    private function analyzeFeatureUsage($userId, $timeframe) {
        return ['features' => []]; // مبسط
    }

    private function checkDiagnosticCompletion($userId) {
        try {
            $result = $this->db->fetchOne("SELECT COUNT(*) as c FROM diagnostic_results WHERE user_id = ?", [$userId]);
            return ($result['c'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkMultipleVisits($userId) {
        try {
            $result = $this->db->fetchOne("SELECT COUNT(DISTINCT DATE(created_at)) as c FROM user_activity_logs WHERE user_id = ?", [$userId]);
            return ($result['c'] ?? 0) > 2;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkTimeOnSite($userId) {
        return true; // مبسط
    }

    private function checkPageDepth($userId) {
        try {
            $result = $this->db->fetchOne("SELECT COUNT(DISTINCT page_url) as c FROM user_activity_logs WHERE user_id = ?", [$userId]);
            return ($result['c'] ?? 0) > 3;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkContentEngagement($userId) {
        return true; // مبسط
    }

    private function checkSupportContact($userId) {
        return false; // مبسط
    }

    private function checkBounceRate($userId) {
        return false; // مبسط
    }

    private function checkFormAbandonment($userId) {
        return false; // مبسط
    }

    private function checkShortSessions($userId) {
        return false; // مبسط
    }

    private function checkNoEngagement($userId) {
        return false; // مبسط
    }

    private function categorizeConversionLikelihood($score) {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    private function checkInactivity($userId, $days) {
        try {
            $result = $this->db->fetchOne("SELECT MAX(created_at) as last_activity FROM user_activity_logs WHERE user_id = ?", [$userId]);
            if ($result && $result['last_activity']) {
                $daysSince = (strtotime('now') - strtotime($result['last_activity'])) / 86400;
                return $daysSince > $days;
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private function checkDecreasingEngagement($userId) {
        return false; // مبسط
    }

    private function checkErrorPatterns($userId) {
        return false; // مبسط
    }

    private function checkNegativeFeedback($userId) {
        return false; // مبسط
    }

    private function categorizeRiskLevel($score) {
        if ($score >= 70) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 20) return 'medium';
        return 'low';
    }

    private function loadAnalyticsData() {
        if (file_exists($this->analyticsDataPath)) {
            return json_decode(file_get_contents($this->analyticsDataPath), true) ?? [];
        }
        return [];
    }

    private function getUserHistory($userId) {
        try {
            return $this->db->fetchAll("SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", [$userId]);
        } catch (Exception $e) {
            return [];
        }
    }

    private function findMatchingBehaviorPatterns($userHistory, $patterns) {
        // مطابقة الأنماط
        return [];
    }

    private function mlBasedPrediction($userHistory) {
        // التنبؤ بالتعلم الآلي
        return [];
    }
}
