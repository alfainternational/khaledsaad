<?php
/**
 * Diagnostic AI Engine (PHP Based) - v4.0
 * محرك تحليل استراتيجي يعتمد على قواعد بيانات معرفية متطورة
 */

class DiagnosticAI {
    
    private $knowledgeBase;
    private $profile = [];
    private $scores = [];

    public function __construct() {
        $this->loadKnowledgeBase();
    }

    private function loadKnowledgeBase() {
        $jsonPath = __DIR__ . '/knowledge_base.json';
        if (file_exists($jsonPath)) {
            $json = file_get_contents($jsonPath);
            $this->knowledgeBase = json_decode($json, true);
        } else {
            // بيانات افتراضية في حال فقدان الملف
            $this->knowledgeBase = [
                'benchmarks' => ['other' => 50],
                'revenue_multipliers' => ['solo' => 50000],
                'maturity_labels' => [
                    'critical' => ['min' => 0, 'label' => 'الحاجة لتدخل عاجل']
                ]
            ];
        }
    }

    public function analyze($data) {
        $this->profile = $data;
        
        // 1. حساب النتائج الأولية للركائز
        $this->scores = $this->calculateScores();
        $overall = $this->calculateOverall($this->scores);
        $this->profile['overall_score'] = $overall; // للتسهيل في الشروط

        $result = [];
        $result['scores'] = $this->scores;
        $result['overall'] = $overall;
        
        // 2. حالة النضج
        $result['maturity'] = $this->determineMaturity($overall);
        
        // 3. المقارنة المرجعية والهدر
        $result['benchmark'] = $this->getBenchmark();
        $result['leakage'] = $this->estimateLeakage($overall);
        
        // 4. توليد خارطة الطريق الذكية
        $result['roadmap'] = $this->generateSmartRoadmap();
        
        // 5. محرك الاستنتاج العرضي (Correlations)
        $result['insights'] = $this->generateCorrelations();

        // 6. تحليل SWOT التفصيلي للركائز (جديد v5.0)
        $result['swot_analysis'] = $this->generateDetailedSWOT();

        // 7. تقرير التوجيه الاستشاري للأدمن
        $result['consultant_report'] = $this->generateConsultantDirectives();

        // 8. تحليل الهدر المالي (جديد v5.5)
        $result['financial_analysis'] = $this->generateFinancialImpact();
        
        // 9. تقييم جودة العميل (Lead Scoring)
        $result['lead_quality'] = $this->calculateLeadQuality($overall);

        // 10. توليد السرد النصي الذكي (LLM Support)
        require_once __DIR__ . '/diagnostic_llm.php';
        $llm = new DiagnosticLLM();
        $result['narrative'] = $llm->generateNarrative($result, $this->profile);
        
        // 11. نصيحة المصدر وتوكن التقرير
        $result['source_tip'] = $this->getSourceSpecificTip();
        $result['report_token'] = bin2hex(random_bytes(16));

        return $result;
    }

    private function generateDetailedSWOT() {
        $swot = [];
        $rules = $this->knowledgeBase['pillar_swot_rules'] ?? [];
        
        foreach (['strategy', 'marketing', 'tech', 'operations'] as $pillar) {
            $score = $this->scores[$pillar] ?? 0;
            $level = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
            
            // جلب القواعد مع دعم التوريث للمستويات المفقودة
            $pillar_rules = $rules[$pillar][$level] ?? ($rules[$pillar]['low'] ?? null);
            
            if ($pillar_rules) {
                $swot[$pillar] = $pillar_rules;
            } else {
                $swot[$pillar] = ['strengths' => [], 'weaknesses' => [], 'pros' => [], 'cons' => []];
            }
        }
        return $swot;
    }

    private function generateConsultantDirectives() {
        $directives = [];
        $rules = $this->knowledgeBase['consultant_directives'] ?? [];
        
        foreach ($rules as $r) {
            if ($this->evaluateCondition($r['condition'])) {
                $directives[] = $r['directive'];
            }
        }
        return $directives;
    }

    private function generateFinancialImpact() {
        $impacts = [];
        $logic = $this->knowledgeBase['financial_leakage_logic'] ?? [];
        
        foreach ($logic as $key => $data) {
            if ($this->evaluateCondition($data['condition'])) {
                $impacts[] = [
                    'category' => $data['label'],
                    'impact' => $data['impact'],
                    'description' => $data['description']
                ];
            }
        }
        return $impacts;
    }

    private function generateCorrelations() {
        $foundInsights = [];
        $correlations = $this->knowledgeBase['complex_correlations'] ?? [];
        
        foreach ($correlations as $c) {
            if ($this->evaluateCondition($c['condition'])) {
                $foundInsights[] = [
                    'title' => 'تحليل ارتباطي ذكي',
                    'insight' => $c['insight'],
                    'action' => $c['action']
                ];
            }
        }
        return $foundInsights;
    }

    private function calculateScores() {
        $scores = ['strategy' => 0, 'marketing' => 0, 'tech' => 0, 'operations' => 0];
        $counts = ['strategy' => 0, 'marketing' => 0, 'tech' => 0, 'operations' => 0];
        
        if (isset($this->profile['answers']) && is_array($this->profile['answers'])) {
            foreach ($this->profile['answers'] as $ans) {
                if (isset($ans['pillar']) && isset($scores[$ans['pillar']])) {
                    $scores[$ans['pillar']] += (int)$ans['score'];
                    $counts[$ans['pillar']]++;
                }
            }
        }

        return [
            'strategy' => $counts['strategy'] ? round($scores['strategy'] / $counts['strategy']) : 0,
            'marketing' => $counts['marketing'] ? round($scores['marketing'] / $counts['marketing']) : 0,
            'tech' => $counts['tech'] ? round($scores['tech'] / $counts['tech']) : 0,
            'operations' => $counts['operations'] ? round($scores['operations'] / $counts['operations']) : 0
        ];
    }

    private function calculateOverall($scores) {
        return round(array_sum($scores) / count($scores));
    }

    private function determineMaturity($score) {
        if (!isset($this->knowledgeBase['maturity_labels'])) return ['label' => 'N/A'];
        
        // ترتيب التسميات تنازلياً حسب الحد الأدنى
        $labels = $this->knowledgeBase['maturity_labels'];
        uasort($labels, function($a, $b) { return $b['min'] - $a['min']; });

        foreach ($labels as $level) {
            if ($score >= $level['min']) {
                return $level;
            }
        }
        return ['label' => 'Critical'];
    }

    private function getBenchmark() {
        $ind = $this->profile['industry'] ?? 'other';
        return $this->knowledgeBase['benchmarks'][$ind] ?? 50;
    }

    private function estimateLeakage($overall) {
        $size = $this->profile['company_size'] ?? 'solo';
        $mult = $this->knowledgeBase['revenue_multipliers'][$size] ?? 50000;
        $gap = 100 - $overall;
        return round(($gap / 100) * $mult);
    }

    private function generateSmartRoadmap() {
        $roadmap = ['p1' => [], 'p2' => [], 'p3' => []];
        $engine = $this->knowledgeBase['recommendations_engine'] ?? [];

        foreach ($engine as $rule) {
            if ($this->evaluateCondition($rule['condition'])) {
                if (isset($rule['phase_1'])) $roadmap['p1'] = array_merge($roadmap['p1'], $rule['phase_1']);
                if (isset($rule['phase_2'])) $roadmap['p2'] = array_merge($roadmap['p2'], $rule['phase_2']);
            }
        }

        // إضافة توصيات عامة إذا كانت المراحل فارغة
        if (empty($roadmap['p1'])) $roadmap['p1'][] = ["title" => "تحليل أداء المبيعات", "advice" => "ابدأ بتتبع أدق لمصادر عملائك الحاليين."];
        if (empty($roadmap['p2'])) $roadmap['p2'][] = ["title" => "رقمنة العمليات", "advice" => "استهدف أتمتة مهمة واحدة تكرارية هذا الأسبوع."];
        if (empty($roadmap['p3'])) $roadmap['p3'][] = ["title" => "التوسع المدروس", "advice" => "خطط لزيادة ميزانية التسويق بعد استقرار الأنظمة."];

        return $roadmap;
    }

    private function calculateLeadQuality($overall) {
        $score = 0;
        $rules = $this->knowledgeBase['lead_scoring_rules'] ?? [];
        
        foreach ($rules as $group) {
            foreach ($group as $rule) {
                if ($this->evaluateCondition($rule['condition'])) {
                    $score += $rule['points'];
                }
            }
        }
        
        return min(100, $score);
    }

    private function getSourceSpecificTip() {
        $src = $this->profile['lead_source'] ?? 'other';
        return $this->knowledgeBase['source_specific_tips'][$src] ?? "توصية: استمر في تحسين قنوات وصولك.";
    }

    private function evaluateCondition($condition) {
        // تجهيز القيم للمقارنة
        $data = [
            'overall' => $this->profile['overall_score'] ?? 0,
            'strategy' => $this->scores['strategy'] ?? 0,
            'marketing' => $this->scores['marketing'] ?? 0,
            'tech' => $this->scores['tech'] ?? 0,
            'operations' => $this->scores['operations'] ?? 0,
            'industry' => $this->profile['industry'] ?? '',
            'company_size' => $this->profile['company_size'] ?? '',
            'lead_source' => $this->profile['lead_source'] ?? ''
        ];

        // دعم الشروط المركبة ||
        if (strpos($condition, '||') !== false) {
            foreach (explode('||', $condition) as $part) {
                if ($this->evaluateCondition(trim($part))) return true;
            }
            return false;
        }

        // دعم الشروط المركبة &&
        if (strpos($condition, '&&') !== false) {
            foreach (explode('&&', $condition) as $part) {
                if (!$this->evaluateCondition(trim($part))) return false;
            }
            return true;
        }

        // التحقق من العمليات الرياضية والمنطقية البسيطة
        if (preg_match('/(\w+)\s*([<>!=]=?|==)\s*(.*)/', $condition, $matches)) {
            $var = trim($matches[1]);
            $op = trim($matches[2]);
            $val = trim($matches[3], " '\"");

            if (!isset($data[$var])) return false;
            $currentVal = $data[$var];

            // تحويل القيم الرقمية
            if (is_numeric($val)) $val = (float)$val;

            switch ($op) {
                case '>':  return $currentVal > $val;
                case '<':  return $currentVal < $val;
                case '>=': return $currentVal >= $val;
                case '<=': return $currentVal <= $val;
                case '==': return $currentVal == $val;
                case '!=': return $currentVal != $val;
            }
        }

        return false;
    }
}