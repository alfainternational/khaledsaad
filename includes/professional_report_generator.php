<?php
/**
 * Professional Report Generator - مولد التقارير الاحترافية
 * v1.0 - Generate comprehensive PDF reports for clients
 *
 * نظام توليد تقارير احترافية شاملة (15-20 صفحة)
 */

require_once __DIR__ . '/action_plan_generator.php';

class ProfessionalReportGenerator {

    private $diagnosticData;
    private $actionPlan;
    private $companyName;
    private $industry;
    private $overallScore;

    public function __construct($diagnosticId) {
        $this->loadDiagnosticData($diagnosticId);
        $this->actionPlan = new ActionPlanGenerator($this->diagnosticData);
    }

    /**
     * تحميل بيانات التشخيص
     */
    private function loadDiagnosticData($diagnosticId) {
        $this->diagnosticData = db()->fetchOne(
            "SELECT * FROM diagnostic_results WHERE id = ?",
            [$diagnosticId]
        );

        if (!$this->diagnosticData) {
            throw new Exception('Diagnostic not found');
        }

        $this->companyName = $this->diagnosticData['company_name'] ?? 'العميل';
        $this->industry = $this->diagnosticData['industry'] ?? 'other';
        $this->overallScore = $this->diagnosticData['overall_score'] ?? 0;
    }

    /**
     * توليد التقرير الكامل
     */
    public function generateFullReport() {
        $report = [
            'metadata' => $this->generateMetadata(),
            'cover_page' => $this->generateCoverPage(),
            'executive_summary' => $this->generateExecutiveSummary(),
            'detailed_analysis' => $this->generateDetailedAnalysis(),
            'action_plan_90' => $this->actionPlan->generate90DayPlan(),
            'quarterly_roadmap' => $this->generateQuarterlyRoadmap(),
            'annual_strategy' => $this->actionPlan->generateAnnualPlan(),
            'tools_resources' => $this->generateToolsAndResources(),
            'implementation_guide' => $this->generateImplementationGuide(),
            'appendix' => $this->generateAppendix()
        ];

        return $report;
    }

    /**
     * Metadata للتقرير
     */
    private function generateMetadata() {
        return [
            'title' => 'تقرير التحليل الاستراتيجي الشامل',
            'client' => $this->companyName,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => 'منصة خالد سعد الاستشارية',
            'version' => '1.0',
            'confidentiality' => 'سري - للاستخدام الداخلي فقط',
            'valid_until' => date('Y-m-d', strtotime('+1 year'))
        ];
    }

    /**
     * صفحة الغلاف
     */
    private function generateCoverPage() {
        return [
            'main_title' => 'تقرير التحليل الاستراتيجي الشامل',
            'subtitle' => 'خارطة طريق متكاملة للتحول والنمو',
            'client_name' => $this->companyName,
            'industry' => $this->translateIndustry($this->industry),
            'overall_score' => $this->overallScore,
            'maturity_level' => $this->getMaturityLabel($this->overallScore),
            'date' => date('d F Y', strtotime('now')),
            'consultant' => [
                'name' => 'خالد سعد',
                'title' => 'خبير التسويق والتحول الرقمي',
                'credentials' => 'مستشار استراتيجي معتمد'
            ],
            'logo_url' => '/assets/images/logo.png',
            'branding_color' => '#1e40af'
        ];
    }

    /**
     * الملخص التنفيذي (Executive Summary)
     */
    private function generateExecutiveSummary() {
        $scores = json_decode($this->diagnosticData['pillars_data'] ?? '{}', true);
        $swot = json_decode($this->diagnosticData['swot_analysis'] ?? '{}', true);
        $financial = json_decode($this->diagnosticData['financial_analysis'] ?? '{}', true);

        // تحديد أكبر 3 فرص
        $topOpportunities = $this->identifyTopOpportunities($scores, $swot);

        // تحديد أخطر 3 تهديدات
        $criticalThreats = $this->identifyCriticalThreats($scores, $swot);

        return [
            'current_state' => [
                'overall_score' => $this->overallScore,
                'maturity_level' => $this->getMaturityLabel($this->overallScore),
                'industry_benchmark' => $this->diagnosticData['benchmark_score'] ?? 50,
                'gap' => ($this->diagnosticData['benchmark_score'] ?? 50) - $this->overallScore,
                'summary' => $this->generateCurrentStateSummary()
            ],
            'key_findings' => [
                'strengths' => $this->extractTopStrengths($scores),
                'weaknesses' => $this->extractTopWeaknesses($scores),
                'opportunities' => $topOpportunities,
                'threats' => $criticalThreats
            ],
            'financial_impact' => [
                'estimated_leakage' => $financial['total_leakage'] ?? 0,
                'potential_savings' => $financial['total_leakage'] ?? 0,
                'revenue_opportunity' => $this->calculateRevenueOpportunity(),
                'roi_forecast' => [
                    '3_months' => '30-50%',
                    '6_months' => '100-150%',
                    '12_months' => '300-500%'
                ]
            ],
            'strategic_recommendations' => $this->generateStrategicRecommendations(),
            'next_steps' => [
                'immediate' => 'بدء تنفيذ خطة الـ 90 يوم',
                'short_term' => 'تحقيق Quick Wins خلال أول شهرين',
                'medium_term' => 'بناء الأنظمة والعمليات (الشهور 3-6)',
                'long_term' => 'التوسع والنمو المستدام (الشهور 7-12)'
            ]
        ];
    }

    /**
     * التحليل التفصيلي
     */
    private function generateDetailedAnalysis() {
        $scores = json_decode($this->diagnosticData['pillars_data'] ?? '{}', true);
        $swot = json_decode($this->diagnosticData['swot_analysis'] ?? '{}', true);

        $analysis = [
            'title' => 'التحليل التفصيلي للركائز الأربعة',
            'pillars' => []
        ];

        foreach ($scores as $pillar => $score) {
            $analysis['pillars'][$pillar] = $this->analyzePillarInDetail($pillar, $score, $swot);
        }

        return $analysis;
    }

    /**
     * تحليل تفصيلي لركيزة واحدة
     */
    private function analyzePillarInDetail($pillar, $score, $swot) {
        return [
            'name' => $this->translatePillar($pillar),
            'score' => $score,
            'level' => $this->getScoreLevel($score),
            'benchmark' => $this->getPillarBenchmark($pillar),
            'gap' => $this->getPillarBenchmark($pillar) - $score,
            'description' => $this->getPillarDescription($pillar),
            'current_state' => $this->describePillarState($pillar, $score),
            'strengths' => $swot[$pillar]['strengths'] ?? [],
            'weaknesses' => $swot[$pillar]['weaknesses'] ?? [],
            'opportunities' => $swot[$pillar]['opportunities'] ?? [],
            'threats' => $swot[$pillar]['threats'] ?? [],
            'improvement_areas' => $this->getImprovementAreas($pillar, $score),
            'quick_wins' => $this->getQuickWins($pillar),
            'long_term_goals' => $this->getLongTermGoals($pillar),
            'kpis' => $this->getPillarKPIs($pillar),
            'action_items' => $this->getPillarActionItems($pillar)
        ];
    }

    /**
     * خارطة الطريق الربع سنوية
     */
    private function generateQuarterlyRoadmap() {
        return [
            'title' => 'خارطة الطريق الربع سنوية',
            'overview' => 'خطة تنفيذية مفصلة للأرباع الأربعة',
            'quarters' => [
                'q1' => $this->actionPlan->generateQuarterlyPlan(1),
                'q2' => $this->actionPlan->generateQuarterlyPlan(2),
                'q3' => $this->actionPlan->generateQuarterlyPlan(3),
                'q4' => $this->actionPlan->generateQuarterlyPlan(4)
            ],
            'visual' => [
                'timeline' => $this->generateTimelineVisualization(),
                'milestones' => $this->generateMilestonesChart()
            ]
        ];
    }

    /**
     * الأدوات والموارد
     */
    private function generateToolsAndResources() {
        return [
            'title' => 'الأدوات والموارد الموصى بها',
            'categories' => [
                'marketing' => [
                    'name' => 'أدوات التسويق',
                    'tools' => [
                        ['name' => 'Google Analytics', 'purpose' => 'تحليل الزوار والسلوك', 'price' => 'مجاني', 'priority' => 'essential'],
                        ['name' => 'Mailchimp', 'purpose' => 'التسويق بالبريد الإلكتروني', 'price' => 'من $13/شهر', 'priority' => 'high'],
                        ['name' => 'Hootsuite', 'purpose' => 'إدارة السوشيال ميديا', 'price' => 'من $49/شهر', 'priority' => 'medium'],
                        ['name' => 'SEMrush', 'purpose' => 'SEO والمنافسين', 'price' => 'من $119/شهر', 'priority' => 'medium']
                    ]
                ],
                'operations' => [
                    'name' => 'أدوات العمليات',
                    'tools' => [
                        ['name' => 'Asana', 'purpose' => 'إدارة المشاريع', 'price' => 'من $10/شهر', 'priority' => 'essential'],
                        ['name' => 'Slack', 'purpose' => 'التواصل الداخلي', 'price' => 'من $7/شهر', 'priority' => 'high'],
                        ['name' => 'Zapier', 'purpose' => 'الأتمتة', 'price' => 'من $20/شهر', 'priority' => 'high'],
                        ['name' => 'QuickBooks', 'purpose' => 'المحاسبة', 'price' => 'من $25/شهر', 'priority' => 'essential']
                    ]
                ],
                'technology' => [
                    'name' => 'أدوات التقنية',
                    'tools' => [
                        ['name' => 'WordPress', 'purpose' => 'إدارة المحتوى', 'price' => 'مجاني', 'priority' => 'essential'],
                        ['name' => 'Shopify', 'purpose' => 'التجارة الإلكترونية', 'price' => 'من $29/شهر', 'priority' => 'high'],
                        ['name' => 'HubSpot CRM', 'purpose' => 'إدارة العملاء', 'price' => 'مجاني', 'priority' => 'essential'],
                        ['name' => 'Google Workspace', 'purpose' => 'الإنتاجية', 'price' => 'من $6/شهر', 'priority' => 'essential']
                    ]
                ],
                'analytics' => [
                    'name' => 'أدوات التحليل',
                    'tools' => [
                        ['name' => 'Hotjar', 'purpose' => 'تحليل السلوك والتسجيلات', 'price' => 'من $39/شهر', 'priority' => 'high'],
                        ['name' => 'Mixpanel', 'purpose' => 'تحليل المنتج', 'price' => 'من $25/شهر', 'priority' => 'medium'],
                        ['name' => 'Google Data Studio', 'purpose' => 'التقارير المرئية', 'price' => 'مجاني', 'priority' => 'high'],
                        ['name' => 'Tableau', 'purpose' => 'Business Intelligence', 'price' => 'من $70/شهر', 'priority' => 'medium']
                    ]
                ]
            ],
            'templates' => [
                'marketing_plan' => 'قالب خطة تسويقية',
                'sales_funnel' => 'قالب قمع مبيعات',
                'content_calendar' => 'قالب تقويم المحتوى',
                'financial_tracker' => 'قالب تتبع مالي',
                'project_tracker' => 'قالب تتبع المشاريع',
                'kpi_dashboard' => 'قالب لوحة مقاييس'
            ],
            'learning_resources' => [
                'courses' => [
                    'Google Digital Garage',
                    'HubSpot Academy',
                    'Coursera Business Strategy'
                ],
                'books' => [
                    'Traction - Gabriel Weinberg',
                    'The Lean Startup - Eric Ries',
                    'Zero to One - Peter Thiel'
                ],
                'communities' => [
                    'مجتمع رواد الأعمال السعوديين',
                    'Startup Grind',
                    'Product Hunt'
                ]
            ]
        ];
    }

    /**
     * دليل التنفيذ
     */
    private function generateImplementationGuide() {
        return [
            'title' => 'دليل التنفيذ الشامل',
            'introduction' => 'كيف تبدأ رحلة التحول؟',
            'getting_started' => [
                'step_1' => [
                    'title' => 'التحضير (الأسبوع 0)',
                    'actions' => [
                        'مراجعة التقرير بالكامل',
                        'تحديد الفريق المسؤول',
                        'تخصيص الموازنة',
                        'حجز الاجتماعات الأسبوعية'
                    ]
                ],
                'step_2' => [
                    'title' => 'البداية السريعة (الأسبوع 1)',
                    'actions' => [
                        'تنفيذ أول 3 إجراءات عاجلة',
                        'إيقاف الهدر المالي الواضح',
                        'بدء قياس المقاييس الأساسية',
                        'إعداد أدوات التتبع'
                    ]
                ],
                'step_3' => [
                    'title' => 'البناء (الأسابيع 2-12)',
                    'actions' => [
                        'تنفيذ خطة الـ 90 يوم',
                        'اجتماعات متابعة أسبوعية',
                        'قياس ومراجعة مستمرة',
                        'تعديل الخطة حسب النتائج'
                    ]
                ]
            ],
            'success_factors' => [
                'commitment' => 'الالتزام من القيادة',
                'resources' => 'تخصيص الموارد الكافية',
                'measurement' => 'قياس مستمر للنتائج',
                'flexibility' => 'المرونة في التكيف',
                'persistence' => 'الاستمرارية والصبر'
            ],
            'common_pitfalls' => [
                'تأجيل البدء',
                'محاولة عمل كل شيء مرة واحدة',
                'عدم قياس النتائج',
                'الاستسلام المبكر',
                'تجاهل التغذية الراجعة'
            ],
            'support_options' => [
                'self_guided' => 'التنفيذ الذاتي (باستخدام هذا التقرير)',
                'consulting' => 'استشارات شهرية (متابعة ودعم)',
                'done_for_you' => 'التنفيذ الكامل (نقوم بالتنفيذ لك)',
                'hybrid' => 'نموذج هجين (تنفيذ مشترك)'
            ]
        ];
    }

    /**
     * الملحق
     */
    private function generateAppendix() {
        return [
            'title' => 'الملحق',
            'sections' => [
                'methodology' => [
                    'title' => 'المنهجية المستخدمة',
                    'description' => 'شرح كيفية تحليل البيانات وتوليد التوصيات',
                    'frameworks' => ['SWOT', 'Porter\'s Five Forces', 'Business Model Canvas', 'OKRs']
                ],
                'glossary' => $this->generateGlossary(),
                'references' => [
                    'Harvard Business Review',
                    'McKinsey Insights',
                    'Gartner Research',
                    'Boston Consulting Group'
                ],
                'contact' => [
                    'name' => 'خالد سعد',
                    'email' => 'info@khaledsa.com',
                    'phone' => '+966 XX XXX XXXX',
                    'website' => 'https://khaledsa.com',
                    'social' => [
                        'twitter' => '@khaledsaad',
                        'linkedin' => 'khaledsaad'
                    ]
                ],
                'next_review' => date('Y-m-d', strtotime('+90 days')),
                'disclaimer' => 'هذا التقرير مبني على البيانات المقدمة وقت التشخيص. النتائج الفعلية قد تختلف بناءً على التنفيذ والظروف الخارجية.'
            ]
        ];
    }

    // ==================== Helper Functions ====================

    private function generateCurrentStateSummary() {
        $score = $this->overallScore;

        if ($score < 40) {
            return "منشأتك في مرحلة حرجة تحتاج لتدخل استراتيجي عاجل. هناك فجوات كبيرة في الركائز الأساسية تؤثر على الاستدامة والنمو.";
        } elseif ($score < 65) {
            return "منشأتك في مرحلة التأسيس الرقمي. هناك أساسات جيدة لكن تحتاج لتطوير الأنظمة والعمليات لتحقيق النمو المستدام.";
        } elseif ($score < 85) {
            return "منشأتك في مرحلة النضج التشغيلي المتقدم. لديك أنظمة جيدة وتحتاج للتحسين المستمر والتوسع.";
        } else {
            return "منشأتك في مرحلة الريادة الاستراتيجية. أنت في المقدمة وتحتاج للحفاظ على هذا التميز والابتكار المستمر.";
        }
    }

    private function identifyTopOpportunities($scores, $swot) {
        return [
            [
                'title' => 'تحسين معدل التحويل',
                'impact' => 'عالي جداً',
                'effort' => 'متوسط',
                'timeframe' => '1-2 شهر',
                'potential_value' => '200,000+ ريال/سنة'
            ],
            [
                'title' => 'أتمتة العمليات التسويقية',
                'impact' => 'عالي',
                'effort' => 'متوسط',
                'timeframe' => '2-3 أشهر',
                'potential_value' => '150,000+ ريال/سنة'
            ],
            [
                'title' => 'إطلاق منتج/خدمة جديدة',
                'impact' => 'عالي جداً',
                'effort' => 'عالي',
                'timeframe' => '3-6 أشهر',
                'potential_value' => '500,000+ ريال/سنة'
            ]
        ];
    }

    private function identifyCriticalThreats($scores, $swot) {
        $threats = [];

        if (($scores['marketing'] ?? 0) < 40) {
            $threats[] = [
                'threat' => 'ضعف التسويق الرقمي',
                'severity' => 'حرج',
                'impact' => 'فقدان حصة سوقية كبيرة',
                'mitigation' => 'إعادة بناء استراتيجية التسويق فوراً'
            ];
        }

        if (($scores['tech'] ?? 0) < 40) {
            $threats[] = [
                'threat' => 'تخلف تقني',
                'severity' => 'عالي',
                'impact' => 'خسارة العملاء للمنافسين',
                'mitigation' => 'الاستثمار العاجل في التقنية'
            ];
        }

        if (count($threats) < 3) {
            $threats[] = [
                'threat' => 'عدم التكيف مع تغيرات السوق',
                'severity' => 'متوسط',
                'impact' => 'تباطؤ النمو',
                'mitigation' => 'مراقبة مستمرة ومرونة عالية'
            ];
        }

        return array_slice($threats, 0, 3);
    }

    private function extractTopStrengths($scores) {
        arsort($scores);
        $top = array_slice($scores, 0, 3, true);

        $strengths = [];
        foreach ($top as $pillar => $score) {
            if ($score >= 65) {
                $strengths[] = [
                    'pillar' => $this->translatePillar($pillar),
                    'score' => $score,
                    'description' => "أداء قوي في " . $this->translatePillar($pillar)
                ];
            }
        }

        return $strengths;
    }

    private function extractTopWeaknesses($scores) {
        asort($scores);
        $bottom = array_slice($scores, 0, 3, true);

        $weaknesses = [];
        foreach ($bottom as $pillar => $score) {
            if ($score < 65) {
                $weaknesses[] = [
                    'pillar' => $this->translatePillar($pillar),
                    'score' => $score,
                    'severity' => $score < 40 ? 'حرج' : ($score < 55 ? 'عالي' : 'متوسط'),
                    'description' => "يحتاج لتحسين عاجل"
                ];
            }
        }

        return $weaknesses;
    }

    private function calculateRevenueOpportunity() {
        // حساب الفرصة الإيرادية بناءً على التحسينات
        $currentScore = $this->overallScore;
        $improvement = (100 - $currentScore) * 0.6; // 60% من الفجوة قابلة للتحسين

        $baseRevenue = $this->estimateCurrentRevenue();
        $potentialIncrease = $baseRevenue * ($improvement / 100) * 3; // 3x multiplier

        return [
            'current_estimated' => $baseRevenue,
            'potential_increase' => $potentialIncrease,
            'total_potential' => $baseRevenue + $potentialIncrease,
            'growth_percentage' => round(($potentialIncrease / $baseRevenue) * 100) . '%'
        ];
    }

    private function estimateCurrentRevenue() {
        $multipliers = [
            'solo' => 200000,
            'small' => 1000000,
            'medium' => 5000000,
            'large' => 20000000
        ];

        $size = $this->diagnosticData['company_size'] ?? 'solo';
        return $multipliers[$size] ?? 500000;
    }

    private function generateStrategicRecommendations() {
        return [
            [
                'priority' => 1,
                'title' => 'إيقاف الهدر المالي فوراً',
                'description' => 'مراجعة جميع النفقات وإيقاف الإنفاق غير المجدي',
                'impact' => 'عالي جداً',
                'timeframe' => 'فوري (خلال 7 أيام)',
                'expected_saving' => '15-25% من التكاليف'
            ],
            [
                'priority' => 2,
                'title' => 'تحسين معدل التحويل',
                'description' => 'تحسين صفحات الهبوط وتجربة المستخدم',
                'impact' => 'عالي',
                'timeframe' => 'قصير (2-4 أسابيع)',
                'expected_impact' => 'زيادة التحويل 25-40%'
            ],
            [
                'priority' => 3,
                'title' => 'بناء أنظمة أتمتة',
                'description' => 'أتمتة التسويق، المبيعات، والعمليات',
                'impact' => 'عالي',
                'timeframe' => 'متوسط (1-3 أشهر)',
                'expected_impact' => 'توفير 30-50 ساعة/أسبوع'
            ]
        ];
    }

    private function getMaturityLabel($score) {
        if ($score >= 85) return 'الريادة الاستراتيجية';
        if ($score >= 65) return 'النضج التشغيلي المتقدم';
        if ($score >= 40) return 'مرحلة التأسيس الرقمي';
        return 'الحاجة لتدخل استراتيجي عاجل';
    }

    private function translateIndustry($industry) {
        $map = [
            'ecommerce' => 'التجارة الإلكترونية',
            'services' => 'الخدمات المهنية',
            'tech' => 'التقنية',
            'realestate' => 'العقارات',
            'fnb' => 'الأغذية والمشروبات',
            'retail' => 'التجزئة',
            'industry' => 'الصناعة',
            'education' => 'التعليم',
            'healthcare' => 'الرعاية الصحية'
        ];

        return $map[$industry] ?? 'قطاعات أخرى';
    }

    private function translatePillar($pillar) {
        $map = [
            'strategy' => 'الاستراتيجية والقيادة',
            'marketing' => 'النمو والتسويق',
            'tech' => 'التكنولوجيا والبيانات',
            'operations' => 'الجودة والعمليات'
        ];

        return $map[$pillar] ?? $pillar;
    }

    private function getScoreLevel($score) {
        if ($score >= 85) return 'ممتاز';
        if ($score >= 65) return 'جيد جداً';
        if ($score >= 40) return 'جيد';
        return 'يحتاج تحسين';
    }

    private function getPillarBenchmark($pillar) {
        // معايير حسب القطاع
        return 70; // افتراضي
    }

    private function getPillarDescription($pillar) {
        $descriptions = [
            'strategy' => 'الرؤية، الأهداف، والتخطيط الاستراتيجي',
            'marketing' => 'الوصول، الاكتساب، والنمو',
            'tech' => 'الأنظمة، البيانات، والأتمتة',
            'operations' => 'العمليات، الجودة، والكفاءة'
        ];

        return $descriptions[$pillar] ?? '';
    }

    private function describePillarState($pillar, $score) {
        if ($score < 40) {
            return "وضع حرج - يحتاج لتدخل فوري";
        } elseif ($score < 65) {
            return "وضع جيد لكن يحتاج لتطوير";
        } else {
            return "وضع قوي - الحفاظ والتحسين المستمر";
        }
    }

    private function getImprovementAreas($pillar, $score) {
        // مجالات التحسين حسب الركيزة
        return [
            'المجال 1: ...',
            'المجال 2: ...',
            'المجال 3: ...'
        ];
    }

    private function getQuickWins($pillar) {
        return [
            'إجراء سريع 1',
            'إجراء سريع 2',
            'إجراء سريع 3'
        ];
    }

    private function getLongTermGoals($pillar) {
        return [
            'هدف استراتيجي 1',
            'هدف استراتيجي 2',
            'هدف استراتيجي 3'
        ];
    }

    private function getPillarKPIs($pillar) {
        return [
            ['name' => 'المقياس 1', 'current' => 0, 'target' => 100, 'unit' => '%'],
            ['name' => 'المقياس 2', 'current' => 0, 'target' => 100, 'unit' => '%']
        ];
    }

    private function getPillarActionItems($pillar) {
        return [
            ['title' => 'الإجراء 1', 'priority' => 'high', 'timeframe' => '1-2 weeks'],
            ['title' => 'الإجراء 2', 'priority' => 'medium', 'timeframe' => '2-4 weeks']
        ];
    }

    private function generateTimelineVisualization() {
        return [
            'type' => 'gantt_chart',
            'quarters' => 4,
            'milestones' => 12
        ];
    }

    private function generateMilestonesChart() {
        return [
            'type' => 'milestone_timeline',
            'format' => 'visual'
        ];
    }

    private function generateGlossary() {
        return [
            'KPI' => 'مؤشر الأداء الرئيسي (Key Performance Indicator)',
            'ROI' => 'العائد على الاستثمار (Return on Investment)',
            'SWOT' => 'نقاط القوة والضعف والفرص والتهديدات',
            'CRM' => 'إدارة علاقات العملاء (Customer Relationship Management)',
            'CAC' => 'تكلفة اكتساب العميل (Customer Acquisition Cost)',
            'LTV' => 'القيمة الدائمة للعميل (Lifetime Value)',
            'NPS' => 'صافي نقاط الترويج (Net Promoter Score)',
            'MVP' => 'الحد الأدنى من المنتج القابل للتطبيق (Minimum Viable Product)'
        ];
    }
}
