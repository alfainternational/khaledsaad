<?php
/**
 * AI Expert System - نظام الذكاء الاصطناعي الخبير
 * v7.0 - Advanced Machine Learning & Self-Improvement System
 *
 * نظام ذكاء اصطناعي متقدم يتعلم ويطور نفسه تلقائياً
 */

require_once __DIR__ . '/diagnostic_llm.php';

class AIExpertSystem {

    private $llm;
    private $db;
    private $knowledgeBasePath;
    private $learningDataPath;
    private $performanceMetricsPath;

    // معاملات التعلم الآلي
    private $learningRate = 0.1;
    private $minConfidenceThreshold = 0.75;
    private $adaptiveWeights = [];

    public function __construct() {
        $this->llm = new DiagnosticLLM();
        $this->db = db();
        $this->knowledgeBasePath = __DIR__ . '/knowledge_base.json';
        $this->learningDataPath = __DIR__ . '/ai_learning_data.json';
        $this->performanceMetricsPath = __DIR__ . '/ai_performance_metrics.json';

        $this->initializeLearningSystem();
    }

    /**
     * تهيئة نظام التعلم الآلي
     */
    private function initializeLearningSystem() {
        // إنشاء ملف بيانات التعلم إذا لم يكن موجوداً
        if (!file_exists($this->learningDataPath)) {
            $initialData = [
                'version' => '7.0',
                'last_update' => date('Y-m-d H:i:s'),
                'total_learning_cycles' => 0,
                'learned_patterns' => [],
                'behavioral_insights' => [],
                'optimization_history' => [],
                'api_training_data' => [],
                'performance_improvements' => []
            ];
            file_put_contents($this->learningDataPath, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // إنشاء ملف مقاييس الأداء
        if (!file_exists($this->performanceMetricsPath)) {
            $initialMetrics = [
                'accuracy_rate' => 0,
                'prediction_success_rate' => 0,
                'customer_satisfaction_score' => 0,
                'conversion_improvement' => 0,
                'total_analyzed_cases' => 0,
                'successful_recommendations' => 0,
                'learning_iterations' => 0
            ];
            file_put_contents($this->performanceMetricsPath, json_encode($initialMetrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->loadAdaptiveWeights();
    }

    /**
     * تحليل ذكي متقدم مع التعلم الآلي
     */
    public function advancedAnalysis($data, $context = []) {
        $startTime = microtime(true);

        // 1. تحليل السلوك والأنماط التاريخية
        $behavioralInsights = $this->analyzeBehavioralPatterns($data);

        // 2. التنبؤ بالنتائج باستخدام الأنماط المتعلمة
        $predictions = $this->predictOutcomes($data, $behavioralInsights);

        // 3. تحليل متقدم باستخدام الذكاء الاصطناعي
        $aiDeepAnalysis = $this->performDeepAIAnalysis($data, $behavioralInsights, $predictions);

        // 4. توليد توصيات ذكية بناءً على التعلم السابق
        $smartRecommendations = $this->generateSmartRecommendations($data, $aiDeepAnalysis);

        // 5. تقييم الثقة والدقة
        $confidenceScore = $this->calculateConfidenceScore($aiDeepAnalysis);

        // 6. التعلم من هذا التحليل
        $this->learnFromAnalysis($data, $aiDeepAnalysis, $predictions);

        $executionTime = microtime(true) - $startTime;

        return [
            'behavioral_insights' => $behavioralInsights,
            'predictions' => $predictions,
            'deep_analysis' => $aiDeepAnalysis,
            'smart_recommendations' => $smartRecommendations,
            'confidence_score' => $confidenceScore,
            'execution_time' => round($executionTime, 3),
            'learning_applied' => true,
            'ai_version' => '7.0'
        ];
    }

    /**
     * تحليل الأنماط السلوكية من البيانات التاريخية
     */
    private function analyzeBehavioralPatterns($currentData) {
        // جلب البيانات التاريخية المشابهة
        $historicalData = $this->fetchSimilarCases($currentData);

        if (empty($historicalData)) {
            return ['status' => 'no_historical_data', 'patterns' => []];
        }

        $patterns = [
            'industry_trends' => $this->analyzeIndustryTrends($historicalData, $currentData),
            'score_patterns' => $this->analyzeScorePatterns($historicalData),
            'success_indicators' => $this->identifySuccessIndicators($historicalData),
            'failure_warnings' => $this->identifyFailureWarnings($historicalData),
            'common_pathways' => $this->extractCommonPathways($historicalData)
        ];

        return $patterns;
    }

    /**
     * التنبؤ بالنتائج باستخدام الذكاء الاصطناعي
     */
    private function predictOutcomes($data, $behavioralInsights) {
        $learningData = $this->loadLearningData();

        $predictions = [
            'success_probability' => 0,
            'optimal_strategy' => '',
            'expected_roi' => 0,
            'risk_level' => 'medium',
            'recommended_focus_areas' => []
        ];

        // استخدام الأنماط المتعلمة للتنبؤ
        if (!empty($learningData['learned_patterns'])) {
            $matchingPatterns = $this->findMatchingPatterns($data, $learningData['learned_patterns']);

            foreach ($matchingPatterns as $pattern) {
                $predictions['success_probability'] += $pattern['success_rate'] * $pattern['confidence'];
                $predictions['recommended_focus_areas'] = array_merge(
                    $predictions['recommended_focus_areas'],
                    $pattern['focus_areas'] ?? []
                );
            }

            $predictions['success_probability'] = min(100, $predictions['success_probability']);
        }

        // استخدام الذكاء الاصطناعي للتنبؤ المتقدم
        $aiPrediction = $this->getAIPrediction($data, $behavioralInsights);

        return array_merge($predictions, $aiPrediction);
    }

    /**
     * تحليل عميق باستخدام الذكاء الاصطناعي المتقدم
     */
    private function performDeepAIAnalysis($data, $behavioralInsights, $predictions) {
        $prompt = $this->buildAdvancedAnalysisPrompt($data, $behavioralInsights, $predictions);

        try {
            $response = $this->llm->generateNarrative([], $data, $prompt);

            // محاولة تحليل الاستجابة كـ JSON
            $jsonMatch = [];
            if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
                $analysisData = json_decode($jsonMatch[0], true);
                if ($analysisData && is_array($analysisData)) {
                    return $analysisData;
                }
            }

            // إذا لم يكن JSON، استخدم التحليل النصي
            return [
                'analysis_type' => 'narrative',
                'content' => $response,
                'requires_manual_review' => false
            ];

        } catch (Exception $e) {
            error_log("Deep AI Analysis Error: " . $e->getMessage());
            return $this->fallbackDeepAnalysis($data, $behavioralInsights);
        }
    }

    /**
     * بناء Prompt متقدم للتحليل العميق
     */
    private function buildAdvancedAnalysisPrompt($data, $behavioralInsights, $predictions) {
        $industry = $data['industry'] ?? 'غير محدد';
        $companySize = $data['company_size'] ?? 'غير محدد';
        $overallScore = $data['overall_score'] ?? 0;

        $historicalContext = json_encode($behavioralInsights, JSON_UNESCAPED_UNICODE);
        $predictionsContext = json_encode($predictions, JSON_UNESCAPED_UNICODE);

        return "
        أنت نظام ذكاء اصطناعي خبير في تحليل الأعمال والاستشارات الاستراتيجية.

        **المهمة:** قم بتحليل عميق ومتقدم للبيانات التالية واستخدم المعرفة المتراكمة لتقديم رؤى استثنائية.

        **بيانات العميل:**
        - القطاع: {$industry}
        - حجم الشركة: {$companySize}
        - الدرجة الإجمالية: {$overallScore}%

        **السياق التاريخي والأنماط:**
        {$historicalContext}

        **التنبؤات المبنية على التعلم الآلي:**
        {$predictionsContext}

        **المطلوب - رد JSON دقيق بهذا التنسيق:**
        {
          \"strategic_insights\": {
            \"primary_opportunity\": \"أكبر فرصة للنمو\",
            \"critical_risk\": \"أخطر تهديد يجب معالجته\",
            \"competitive_advantage\": \"الميزة التنافسية الممكنة\",
            \"hidden_potential\": \"إمكانيات مخفية غير مستغلة\"
          },
          \"data_driven_recommendations\": [
            {
              \"priority\": \"عالي/متوسط/منخفض\",
              \"action\": \"الإجراء المحدد\",
              \"expected_impact\": \"الأثر المتوقع بالأرقام\",
              \"timeline\": \"الإطار الزمني\",
              \"resources_needed\": \"الموارد المطلوبة\"
            }
          ],
          \"predictive_metrics\": {
            \"growth_potential_12m\": \"نسبة النمو المتوقعة خلال 12 شهر\",
            \"break_even_timeline\": \"متى يتوقع التعادل\",
            \"roi_estimate\": \"العائد المتوقع على الاستثمار\"
          },
          \"learning_insights\": {
            \"pattern_identified\": \"النمط المكتشف من البيانات\",
            \"confidence_level\": \"مستوى الثقة (0-100)\",
            \"data_quality\": \"جودة البيانات المستخدمة\"
          }
        }

        **ملاحظة:** استخدم البيانات التاريخية والأنماط المتعلمة لتقديم توصيات دقيقة ومبنية على الواقع.
        ";
    }

    /**
     * توليد توصيات ذكية بناءً على التعلم السابق
     */
    private function generateSmartRecommendations($data, $aiAnalysis) {
        $learningData = $this->loadLearningData();
        $recommendations = [];

        // التوصيات المبنية على التعلم الآلي
        if (!empty($learningData['learned_patterns'])) {
            $successfulPatterns = array_filter($learningData['learned_patterns'], function($p) {
                return ($p['success_rate'] ?? 0) > 0.7;
            });

            foreach ($successfulPatterns as $pattern) {
                if ($this->isPatternApplicable($data, $pattern)) {
                    $recommendations[] = [
                        'type' => 'learned',
                        'recommendation' => $pattern['recommendation'],
                        'confidence' => $pattern['confidence'],
                        'based_on' => 'historical_success',
                        'success_rate' => $pattern['success_rate']
                    ];
                }
            }
        }

        // التوصيات من تحليل الذكاء الاصطناعي
        if (isset($aiAnalysis['data_driven_recommendations'])) {
            foreach ($aiAnalysis['data_driven_recommendations'] as $rec) {
                $recommendations[] = [
                    'type' => 'ai_generated',
                    'recommendation' => $rec,
                    'confidence' => ($aiAnalysis['learning_insights']['confidence_level'] ?? 75) / 100,
                    'based_on' => 'ai_analysis'
                ];
            }
        }

        // ترتيب التوصيات حسب الأولوية والثقة
        usort($recommendations, function($a, $b) {
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        return array_slice($recommendations, 0, 10); // أفضل 10 توصيات
    }

    /**
     * حساب مستوى الثقة في التحليل
     */
    private function calculateConfidenceScore($aiAnalysis) {
        $factors = [
            'data_quality' => 0.3,
            'historical_match' => 0.25,
            'ai_confidence' => 0.25,
            'pattern_strength' => 0.2
        ];

        $score = 0;

        // جودة البيانات
        if (isset($aiAnalysis['learning_insights']['data_quality'])) {
            $qualityMap = ['high' => 1, 'medium' => 0.7, 'low' => 0.4];
            $score += ($qualityMap[$aiAnalysis['learning_insights']['data_quality']] ?? 0.5) * $factors['data_quality'];
        }

        // ثقة الذكاء الاصطناعي
        if (isset($aiAnalysis['learning_insights']['confidence_level'])) {
            $score += ($aiAnalysis['learning_insights']['confidence_level'] / 100) * $factors['ai_confidence'];
        }

        // التطابق التاريخي
        $learningData = $this->loadLearningData();
        if (!empty($learningData['learned_patterns'])) {
            $score += 0.8 * $factors['historical_match'];
        }

        // قوة النمط
        if (isset($aiAnalysis['learning_insights']['pattern_identified'])) {
            $score += 0.9 * $factors['pattern_strength'];
        }

        return round($score * 100, 2);
    }

    /**
     * التعلم من التحليل الحالي وتحديث قاعدة المعرفة
     */
    private function learnFromAnalysis($data, $aiAnalysis, $predictions) {
        $learningData = $this->loadLearningData();

        // استخراج الأنماط الجديدة
        $newPattern = [
            'id' => uniqid('pattern_'),
            'timestamp' => date('Y-m-d H:i:s'),
            'industry' => $data['industry'] ?? 'unknown',
            'company_size' => $data['company_size'] ?? 'unknown',
            'score_range' => $this->getScoreRange($data['overall_score'] ?? 0),
            'insights' => $aiAnalysis['strategic_insights'] ?? [],
            'recommendations' => array_slice($aiAnalysis['data_driven_recommendations'] ?? [], 0, 3),
            'confidence' => ($aiAnalysis['learning_insights']['confidence_level'] ?? 75) / 100,
            'success_rate' => 0, // سيتم تحديثه لاحقاً بناءً على النتائج الفعلية
            'focus_areas' => $this->extractFocusAreas($aiAnalysis),
            'usage_count' => 1
        ];

        // إضافة النمط الجديد
        $learningData['learned_patterns'][] = $newPattern;

        // تحديث بيانات التعلم من API
        if (!empty($aiAnalysis)) {
            $learningData['api_training_data'][] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'input_data' => array_intersect_key($data, array_flip(['industry', 'company_size', 'overall_score'])),
                'ai_output' => $aiAnalysis,
                'quality_score' => $this->assessOutputQuality($aiAnalysis)
            ];
        }

        // الحفاظ على آخر 500 نمط فقط
        if (count($learningData['learned_patterns']) > 500) {
            // إزالة الأنماط الأقل استخداماً
            usort($learningData['learned_patterns'], function($a, $b) {
                return ($b['usage_count'] ?? 0) <=> ($a['usage_count'] ?? 0);
            });
            $learningData['learned_patterns'] = array_slice($learningData['learned_patterns'], 0, 500);
        }

        // تحديث العدادات
        $learningData['total_learning_cycles']++;
        $learningData['last_update'] = date('Y-m-d H:i:s');

        // حفظ البيانات المحدثة
        $this->saveLearningData($learningData);

        // تحديث مقاييس الأداء
        $this->updatePerformanceMetrics($data, $aiAnalysis);

        // محاولة تحسين قاعدة المعرفة تلقائياً
        $this->attemptKnowledgeBaseOptimization();
    }

    /**
     * تحسين قاعدة المعرفة تلقائياً بناءً على التعلم
     */
    private function attemptKnowledgeBaseOptimization() {
        $learningData = $this->loadLearningData();

        // كل 50 دورة تعلم، حاول تحسين قاعدة المعرفة
        if ($learningData['total_learning_cycles'] % 50 !== 0) {
            return;
        }

        $optimizationPrompt = $this->buildOptimizationPrompt($learningData);

        try {
            $response = $this->llm->generateNarrative([], [], $optimizationPrompt);

            // محاولة استخراج التحسينات المقترحة
            $jsonMatch = [];
            if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
                $improvements = json_decode($jsonMatch[0], true);

                if ($improvements && is_array($improvements)) {
                    // حفظ التحسينات المقترحة للمراجعة
                    $this->saveOptimizationProposal($improvements);

                    // تطبيق التحسينات ذات الثقة العالية تلقائياً
                    $this->applyHighConfidenceImprovements($improvements);
                }
            }
        } catch (Exception $e) {
            error_log("Knowledge Base Optimization Error: " . $e->getMessage());
        }
    }

    /**
     * بناء prompt لتحسين قاعدة المعرفة
     */
    private function buildOptimizationPrompt($learningData) {
        $recentPatterns = array_slice($learningData['learned_patterns'], -20);
        $patternsJson = json_encode($recentPatterns, JSON_UNESCAPED_UNICODE);

        $currentKB = json_decode(file_get_contents($this->knowledgeBasePath), true);
        $currentRules = json_encode($currentKB['recommendations_engine'] ?? [], JSON_UNESCAPED_UNICODE);

        return "
        أنت نظام تحسين ذكي لقاعدة المعرفة.

        **المهمة:** تحليل الأنماط المتعلمة واقتراح تحسينات على قاعدة المعرفة.

        **الأنماط المتعلمة حديثاً:**
        {$patternsJson}

        **القواعد الحالية:**
        {$currentRules}

        **المطلوب - رد JSON:**
        {
          \"proposed_improvements\": [
            {
              \"type\": \"new_rule/update_rule/remove_rule\",
              \"confidence\": 0.9,
              \"rule\": {
                \"condition\": \"الشرط\",
                \"recommendation\": \"التوصية\",
                \"priority\": \"high/medium/low\"
              },
              \"reasoning\": \"السبب بناءً على البيانات المتعلمة\"
            }
          ],
          \"insights\": {
            \"patterns_identified\": \"الأنماط المكتشفة\",
            \"improvement_areas\": \"مجالات التحسين\",
            \"confidence\": 0.85
          }
        }

        اقترح فقط التحسينات المبنية على بيانات قوية وثقة عالية (>0.8).
        ";
    }

    /**
     * حفظ مقترحات التحسين للمراجعة
     */
    private function saveOptimizationProposal($improvements) {
        $proposal = [
            'timestamp' => date('Y-m-d H:i:s'),
            'improvements' => $improvements,
            'status' => 'pending_review',
            'auto_applied' => []
        ];

        try {
            $this->db->insert('kb_ai_proposals', [
                'proposed_at' => date('Y-m-d H:i:s'),
                'suggestion_type' => 'knowledge_base_optimization',
                'suggested_rule' => json_encode($improvements),
                'confidence_score' => $improvements['insights']['confidence'] ?? 0,
                'status' => 'pending'
            ]);
        } catch (Exception $e) {
            error_log("Error saving optimization proposal: " . $e->getMessage());
        }
    }

    /**
     * تطبيق التحسينات ذات الثقة العالية
     */
    private function applyHighConfidenceImprovements($improvements) {
        if (!isset($improvements['proposed_improvements'])) {
            return;
        }

        $applied = [];

        foreach ($improvements['proposed_improvements'] as $improvement) {
            // تطبيق فقط التحسينات بثقة > 0.9
            if (($improvement['confidence'] ?? 0) > 0.9) {
                try {
                    $this->applyKnowledgeBaseImprovement($improvement);
                    $applied[] = $improvement;
                } catch (Exception $e) {
                    error_log("Error applying improvement: " . $e->getMessage());
                }
            }
        }

        if (!empty($applied)) {
            $learningData = $this->loadLearningData();
            $learningData['optimization_history'][] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'applied_improvements' => $applied
            ];
            $this->saveLearningData($learningData);
        }
    }

    /**
     * تطبيق تحسين على قاعدة المعرفة
     */
    private function applyKnowledgeBaseImprovement($improvement) {
        $kb = json_decode(file_get_contents($this->knowledgeBasePath), true);

        switch ($improvement['type']) {
            case 'new_rule':
                if (!isset($kb['recommendations_engine'])) {
                    $kb['recommendations_engine'] = [];
                }
                $kb['recommendations_engine'][] = $improvement['rule'];
                break;

            case 'update_rule':
                // تحديث قاعدة موجودة
                // سيتم تطويرها لاحقاً
                break;

            case 'remove_rule':
                // إزالة قاعدة
                // سيتم تطويرها لاحقاً
                break;
        }

        // حفظ قاعدة المعرفة المحدثة
        file_put_contents(
            $this->knowledgeBasePath,
            json_encode($kb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // حفظ نسخة احتياطية
        $backupPath = str_replace('.json', '_backup_' . date('Ymd_His') . '.json', $this->knowledgeBasePath);
        file_put_contents($backupPath, json_encode($kb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * تحديث مقاييس الأداء
     */
    private function updatePerformanceMetrics($data, $aiAnalysis) {
        $metrics = json_decode(file_get_contents($this->performanceMetricsPath), true);

        $metrics['total_analyzed_cases']++;
        $metrics['learning_iterations']++;

        // تحديث معدل الدقة بناءً على ثقة التحليل
        if (isset($aiAnalysis['learning_insights']['confidence_level'])) {
            $currentAccuracy = $metrics['accuracy_rate'];
            $newAccuracy = $aiAnalysis['learning_insights']['confidence_level'];
            $metrics['accuracy_rate'] = ($currentAccuracy * 0.9) + ($newAccuracy * 0.1);
        }

        $metrics['last_update'] = date('Y-m-d H:i:s');

        file_put_contents(
            $this->performanceMetricsPath,
            json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * جلب حالات مشابهة من التاريخ
     */
    private function fetchSimilarCases($currentData) {
        try {
            $industry = $currentData['industry'] ?? '';
            $companySize = $currentData['company_size'] ?? '';

            $query = "
                SELECT * FROM diagnostic_results
                WHERE industry = ? OR company_size = ?
                ORDER BY created_at DESC
                LIMIT 100
            ";

            return $this->db->fetchAll($query, [$industry, $companySize]);
        } catch (Exception $e) {
            error_log("Error fetching similar cases: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تحليل اتجاهات القطاع
     */
    private function analyzeIndustryTrends($historicalData, $currentData) {
        $industry = $currentData['industry'] ?? '';
        $industryData = array_filter($historicalData, function($d) use ($industry) {
            return ($d['industry'] ?? '') === $industry;
        });

        if (empty($industryData)) {
            return ['trend' => 'insufficient_data'];
        }

        $scores = array_map(function($d) {
            return $d['overall_score'] ?? 0;
        }, $industryData);

        return [
            'average_score' => round(array_sum($scores) / count($scores), 2),
            'max_score' => max($scores),
            'min_score' => min($scores),
            'trend' => $this->calculateTrend($scores),
            'sample_size' => count($industryData)
        ];
    }

    /**
     * تحليل أنماط الدرجات
     */
    private function analyzeScorePatterns($historicalData) {
        $patterns = [
            'high_performers' => [],
            'average_performers' => [],
            'low_performers' => []
        ];

        foreach ($historicalData as $case) {
            $score = $case['overall_score'] ?? 0;

            if ($score >= 70) {
                $patterns['high_performers'][] = $case;
            } elseif ($score >= 40) {
                $patterns['average_performers'][] = $case;
            } else {
                $patterns['low_performers'][] = $case;
            }
        }

        return [
            'high_performers_count' => count($patterns['high_performers']),
            'average_performers_count' => count($patterns['average_performers']),
            'low_performers_count' => count($patterns['low_performers']),
            'distribution' => [
                'high' => round(count($patterns['high_performers']) / max(count($historicalData), 1) * 100, 2),
                'average' => round(count($patterns['average_performers']) / max(count($historicalData), 1) * 100, 2),
                'low' => round(count($patterns['low_performers']) / max(count($historicalData), 1) * 100, 2)
            ]
        ];
    }

    /**
     * تحديد مؤشرات النجاح
     */
    private function identifySuccessIndicators($historicalData) {
        // تحليل الحالات الناجحة (درجات عالية)
        $successfulCases = array_filter($historicalData, function($d) {
            return ($d['overall_score'] ?? 0) >= 70;
        });

        if (empty($successfulCases)) {
            return [];
        }

        $indicators = [];

        // تحليل الركائز في الحالات الناجحة
        foreach ($successfulCases as $case) {
            $pillarsData = json_decode($case['pillars_data'] ?? '{}', true);
            foreach ($pillarsData as $pillar => $score) {
                if (!isset($indicators[$pillar])) {
                    $indicators[$pillar] = [];
                }
                $indicators[$pillar][] = $score;
            }
        }

        // حساب المتوسطات
        $avgIndicators = [];
        foreach ($indicators as $pillar => $scores) {
            $avgIndicators[$pillar] = round(array_sum($scores) / count($scores), 2);
        }

        return $avgIndicators;
    }

    /**
     * تحديد تحذيرات الفشل
     */
    private function identifyFailureWarnings($historicalData) {
        // تحليل الحالات الفاشلة (درجات منخفضة)
        $failedCases = array_filter($historicalData, function($d) {
            return ($d['overall_score'] ?? 0) < 40;
        });

        if (empty($failedCases)) {
            return [];
        }

        $warnings = [];

        foreach ($failedCases as $case) {
            $pillarsData = json_decode($case['pillars_data'] ?? '{}', true);
            foreach ($pillarsData as $pillar => $score) {
                if ($score < 30) {
                    if (!isset($warnings[$pillar])) {
                        $warnings[$pillar] = 0;
                    }
                    $warnings[$pillar]++;
                }
            }
        }

        return $warnings;
    }

    /**
     * استخراج المسارات الشائعة
     */
    private function extractCommonPathways($historicalData) {
        // تحليل مسارات التطور الشائعة
        $pathways = [];

        // هذا يتطلب بيانات تاريخية متعددة لنفس العميل
        // سيتم تطويره لاحقاً

        return $pathways;
    }

    /**
     * التنبؤ باستخدام الذكاء الاصطناعي
     */
    private function getAIPrediction($data, $behavioralInsights) {
        $prompt = "
        بناءً على البيانات التالية، قدم تنبؤات دقيقة:

        البيانات الحالية: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "
        الأنماط السلوكية: " . json_encode($behavioralInsights, JSON_UNESCAPED_UNICODE) . "

        المطلوب - JSON فقط:
        {
          \"optimal_strategy\": \"الاستراتيجية الأمثل\",
          \"expected_roi\": رقم,
          \"risk_level\": \"high/medium/low\"
        }
        ";

        try {
            $response = $this->llm->generateNarrative([], $data, $prompt);
            $jsonMatch = [];
            if (preg_match('/\{.*\}/s', $response, $jsonMatch)) {
                return json_decode($jsonMatch[0], true) ?? [];
            }
        } catch (Exception $e) {
            error_log("AI Prediction Error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * إيجاد الأنماط المطابقة
     */
    private function findMatchingPatterns($data, $patterns) {
        $matches = [];

        foreach ($patterns as $pattern) {
            $matchScore = 0;

            if (($pattern['industry'] ?? '') === ($data['industry'] ?? '')) {
                $matchScore += 0.4;
            }

            if (($pattern['company_size'] ?? '') === ($data['company_size'] ?? '')) {
                $matchScore += 0.3;
            }

            $dataScore = $data['overall_score'] ?? 0;
            $patternScore = $this->getScoreFromRange($pattern['score_range'] ?? '');
            if (abs($dataScore - $patternScore) < 15) {
                $matchScore += 0.3;
            }

            if ($matchScore >= 0.6) {
                $pattern['match_score'] = $matchScore;
                $matches[] = $pattern;
            }
        }

        return $matches;
    }

    /**
     * التحليل العميق الاحتياطي
     */
    private function fallbackDeepAnalysis($data, $behavioralInsights) {
        return [
            'analysis_type' => 'fallback',
            'strategic_insights' => [
                'primary_opportunity' => 'تحسين العمليات الرقمية',
                'critical_risk' => 'ضعف التكامل التقني',
                'competitive_advantage' => 'السرعة في التنفيذ',
                'hidden_potential' => 'الاستفادة من البيانات'
            ],
            'predictive_metrics' => [
                'growth_potential_12m' => '15-25%',
                'break_even_timeline' => '6-8 أشهر',
                'roi_estimate' => '150-200%'
            ]
        ];
    }

    /**
     * استخراج مجالات التركيز
     */
    private function extractFocusAreas($aiAnalysis) {
        $areas = [];

        if (isset($aiAnalysis['data_driven_recommendations'])) {
            foreach ($aiAnalysis['data_driven_recommendations'] as $rec) {
                if (isset($rec['action'])) {
                    $areas[] = $rec['action'];
                }
            }
        }

        return array_slice($areas, 0, 5);
    }

    /**
     * تقييم جودة المخرجات
     */
    private function assessOutputQuality($aiAnalysis) {
        $score = 0;

        if (isset($aiAnalysis['strategic_insights']) && is_array($aiAnalysis['strategic_insights'])) {
            $score += 25;
        }

        if (isset($aiAnalysis['data_driven_recommendations']) && is_array($aiAnalysis['data_driven_recommendations'])) {
            $score += 25;
        }

        if (isset($aiAnalysis['predictive_metrics']) && is_array($aiAnalysis['predictive_metrics'])) {
            $score += 25;
        }

        if (isset($aiAnalysis['learning_insights']['confidence_level']) && $aiAnalysis['learning_insights']['confidence_level'] > 70) {
            $score += 25;
        }

        return $score;
    }

    /**
     * الحصول على نطاق الدرجة
     */
    private function getScoreRange($score) {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    /**
     * الحصول على درجة من النطاق
     */
    private function getScoreFromRange($range) {
        $rangeMap = ['high' => 80, 'medium' => 55, 'low' => 25];
        return $rangeMap[$range] ?? 50;
    }

    /**
     * حساب الاتجاه
     */
    private function calculateTrend($scores) {
        if (count($scores) < 2) {
            return 'neutral';
        }

        $recent = array_slice($scores, -10);
        $older = array_slice($scores, 0, -10);

        if (empty($older)) {
            return 'neutral';
        }

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $diff = $recentAvg - $olderAvg;

        if ($diff > 5) return 'improving';
        if ($diff < -5) return 'declining';
        return 'stable';
    }

    /**
     * التحقق من قابلية تطبيق النمط
     */
    private function isPatternApplicable($data, $pattern) {
        $matchScore = 0;

        if (($pattern['industry'] ?? '') === ($data['industry'] ?? '')) {
            $matchScore += 0.5;
        }

        if (($pattern['company_size'] ?? '') === ($data['company_size'] ?? '')) {
            $matchScore += 0.5;
        }

        return $matchScore >= 0.5;
    }

    /**
     * تحميل بيانات التعلم
     */
    private function loadLearningData() {
        if (file_exists($this->learningDataPath)) {
            $data = json_decode(file_get_contents($this->learningDataPath), true);
            return $data ?? [];
        }
        return [];
    }

    /**
     * حفظ بيانات التعلم
     */
    private function saveLearningData($data) {
        file_put_contents(
            $this->learningDataPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * تحميل الأوزان التكيفية
     */
    private function loadAdaptiveWeights() {
        $learningData = $this->loadLearningData();
        $this->adaptiveWeights = $learningData['adaptive_weights'] ?? [
            'strategy' => 0.25,
            'marketing' => 0.25,
            'tech' => 0.25,
            'operations' => 0.25
        ];
    }

    /**
     * الحصول على مقاييس الأداء
     */
    public function getPerformanceMetrics() {
        if (file_exists($this->performanceMetricsPath)) {
            return json_decode(file_get_contents($this->performanceMetricsPath), true);
        }
        return [];
    }

    /**
     * الحصول على إحصائيات التعلم
     */
    public function getLearningStats() {
        $learningData = $this->loadLearningData();

        return [
            'total_learning_cycles' => $learningData['total_learning_cycles'] ?? 0,
            'learned_patterns_count' => count($learningData['learned_patterns'] ?? []),
            'api_training_data_count' => count($learningData['api_training_data'] ?? []),
            'optimization_history_count' => count($learningData['optimization_history'] ?? []),
            'last_update' => $learningData['last_update'] ?? 'N/A',
            'version' => $learningData['version'] ?? '7.0'
        ];
    }
}
