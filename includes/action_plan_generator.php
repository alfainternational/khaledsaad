<?php
/**
 * Action Plan Generator - مولد خطط العمل الاستراتيجية
 * v1.0 - Professional Treatment Plans (90 Day, Quarterly, Annual)
 *
 * نظام متقدم لتوليد خطط عمل احترافية وقابلة للتنفيذ
 */

class ActionPlanGenerator {

    private $diagnosticData;
    private $industry;
    private $companySize;
    private $scores;
    private $weakPoints = [];
    private $strongPoints = [];
    private $opportunities = [];

    public function __construct($diagnosticData) {
        $this->diagnosticData = $diagnosticData;
        $this->industry = $diagnosticData['industry'] ?? 'other';
        $this->companySize = $diagnosticData['company_size'] ?? 'solo';
        $this->scores = json_decode($diagnosticData['pillars_data'] ?? '{}', true);

        $this->analyzeStrengthsAndWeaknesses();
    }

    /**
     * تحليل نقاط القوة والضعف
     */
    private function analyzeStrengthsAndWeaknesses() {
        foreach ($this->scores as $pillar => $score) {
            if ($score < 40) {
                $this->weakPoints[] = ['pillar' => $pillar, 'score' => $score, 'severity' => 'critical'];
            } elseif ($score < 60) {
                $this->weakPoints[] = ['pillar' => $pillar, 'score' => $score, 'severity' => 'high'];
            } elseif ($score < 75) {
                $this->weakPoints[] = ['pillar' => $pillar, 'score' => $score, 'severity' => 'medium'];
            } else {
                $this->strongPoints[] = ['pillar' => $pillar, 'score' => $score];
            }
        }

        // ترتيب حسب الأولوية (الأضعف أولاً)
        usort($this->weakPoints, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });
    }

    /**
     * توليد خطة الـ 90 يوم (Quick Wins)
     */
    public function generate90DayPlan() {
        $plan = [
            'title' => 'خطة الـ 90 يوم الأولى - التحسينات السريعة',
            'objective' => 'تحقيق نتائج ملموسة وسريعة خلال 3 أشهر',
            'target_improvement' => '30-50% تحسن في المقاييس الرئيسية',
            'phases' => []
        ];

        // المرحلة 1: الأسابيع 1-2 (التقييم والإيقاف الفوري للهدر)
        $plan['phases']['week_1_2'] = [
            'title' => 'الأسابيع 1-2: التقييم العميق وإيقاف الهدر',
            'focus' => 'Quick Wins وإيقاف النزيف المالي',
            'tasks' => $this->getWeek1_2Tasks(),
            'kpis' => [
                'cost_reduction' => 'توفير 15-20% من التكاليف',
                'efficiency' => 'زيادة الكفاءة 10%',
                'waste_elimination' => 'إيقاف 80% من الهدر الواضح'
            ],
            'deliverables' => [
                'تقرير تحليل التكاليف التفصيلي',
                'قائمة الهدر المالي والإجراءات',
                'خطة تحسين فورية'
            ]
        ];

        // المرحلة 2: الأسابيع 3-4 (التحسينات السريعة)
        $plan['phases']['week_3_4'] = [
            'title' => 'الأسابيع 3-4: التحسينات السريعة والأثر الفوري',
            'focus' => 'تحسين معدل التحويل والإيرادات',
            'tasks' => $this->getWeek3_4Tasks(),
            'kpis' => [
                'conversion_rate' => 'زيادة معدل التحويل 20-30%',
                'revenue' => 'زيادة الإيرادات 10-15%',
                'customer_acquisition' => 'اكتساب 20-30 عميل جديد'
            ],
            'deliverables' => [
                'صفحات هبوط محسّنة',
                'حملات تسويقية جديدة',
                'نظام متابعة آلي'
            ]
        ];

        // الشهر 2: بناء الأنظمة الأساسية
        $plan['phases']['month_2'] = [
            'title' => 'الشهر الثاني: بناء الأنظمة والعمليات',
            'focus' => 'أتمتة وتحسين العمليات',
            'tasks' => $this->getMonth2Tasks(),
            'kpis' => [
                'automation' => 'أتمتة 40% من العمليات اليدوية',
                'time_saved' => 'توفير 20 ساعة عمل أسبوعياً',
                'quality' => 'تحسين الجودة 25%'
            ],
            'deliverables' => [
                'قمع مبيعات متكامل',
                'نظام CRM مفعّل',
                'أنظمة أتمتة تسويقية'
            ]
        ];

        // الشهر 3: القياس والتحسين
        $plan['phases']['month_3'] = [
            'title' => 'الشهر الثالث: القياس والتحسين المستمر',
            'focus' => 'قياس النتائج وتحسين الاستراتيجية',
            'tasks' => $this->getMonth3Tasks(),
            'kpis' => [
                'overall_improvement' => 'تحسن 40-50% في المقاييس الرئيسية',
                'roi' => 'ROI إيجابي 150-200%',
                'satisfaction' => 'رضا العملاء 85%+'
            ],
            'deliverables' => [
                'تقرير النتائج النهائي',
                'خطة التحسين المستمر',
                'استراتيجية الربع القادم'
            ]
        ];

        // الموارد المطلوبة
        $plan['resources'] = $this->get90DayResources();

        // نقاط التفتيش
        $plan['checkpoints'] = [
            ['week' => 2, 'type' => 'review', 'duration' => '30 min'],
            ['week' => 4, 'type' => 'review', 'duration' => '30 min'],
            ['week' => 6, 'type' => 'review', 'duration' => '45 min'],
            ['week' => 8, 'type' => 'review', 'duration' => '45 min'],
            ['week' => 10, 'type' => 'review', 'duration' => '45 min'],
            ['week' => 12, 'type' => 'comprehensive', 'duration' => '2 hours']
        ];

        return $plan;
    }

    /**
     * توليد خطة ربع سنوية
     */
    public function generateQuarterlyPlan($quarter = 1) {
        $quarters = [
            1 => [
                'title' => 'الربع الأول: بناء الأساسات',
                'months' => 'يناير - مارس',
                'objective' => 'بناء أساسات قوية للنمو',
                'focus_areas' => ['استراتيجية', 'أنظمة أساسية', 'بناء الفريق']
            ],
            2 => [
                'title' => 'الربع الثاني: النمو والتحسين',
                'months' => 'أبريل - يونيو',
                'objective' => 'تسريع النمو وتحسين الكفاءة',
                'focus_areas' => ['توسيع القنوات', 'أتمتة متقدمة', 'تحسين الجودة']
            ],
            3 => [
                'title' => 'الربع الثالث: التوسع المدروس',
                'months' => 'يوليو - سبتمبر',
                'objective' => 'التوسع في أسواق ومنتجات جديدة',
                'focus_areas' => ['أسواق جديدة', 'منتجات جديدة', 'شراكات']
            ],
            4 => [
                'title' => 'الربع الرابع: التحسين والاستعداد',
                'months' => 'أكتوبر - ديسمبر',
                'objective' => 'تحسين الأنظمة والاستعداد للعام القادم',
                'focus_areas' => ['تحسين الربحية', 'كفاءة الفريق', 'التخطيط']
            ]
        ];

        $quarterData = $quarters[$quarter];

        $plan = [
            'quarter' => $quarter,
            'title' => $quarterData['title'],
            'months' => $quarterData['months'],
            'objective' => $quarterData['objective'],
            'focus_areas' => $quarterData['focus_areas'],
            'monthly_breakdown' => $this->getQuarterlyMonthlyBreakdown($quarter),
            'kpis' => $this->getQuarterlyKPIs($quarter),
            'milestones' => $this->getQuarterlyMilestones($quarter),
            'budget' => $this->getQuarterlyBudget($quarter),
            'risks' => $this->getQuarterlyRisks($quarter)
        ];

        return $plan;
    }

    /**
     * توليد خطة سنوية استراتيجية
     */
    public function generateAnnualPlan() {
        $currentScore = $this->diagnosticData['overall_score'] ?? 50;
        $targetScore = min(95, $currentScore + 40);

        $plan = [
            'title' => 'الخطة الاستراتيجية السنوية',
            'vision' => $this->getAnnualVision(),
            'current_state' => [
                'overall_score' => $currentScore,
                'maturity_level' => $this->getMaturityLabel($currentScore),
                'strengths' => count($this->strongPoints),
                'weaknesses' => count($this->weakPoints)
            ],
            'target_state' => [
                'overall_score' => $targetScore,
                'maturity_level' => $this->getMaturityLabel($targetScore),
                'expected_improvement' => $targetScore - $currentScore . '%'
            ],
            'strategic_pillars' => $this->getStrategicPillars(),
            'quarterly_roadmap' => [
                'q1' => $this->generateQuarterlyPlan(1),
                'q2' => $this->generateQuarterlyPlan(2),
                'q3' => $this->generateQuarterlyPlan(3),
                'q4' => $this->generateQuarterlyPlan(4)
            ],
            'annual_kpis' => $this->getAnnualKPIs(),
            'investment_required' => $this->calculateAnnualInvestment(),
            'expected_roi' => $this->calculateExpectedROI(),
            'success_metrics' => $this->getSuccessMetrics()
        ];

        return $plan;
    }

    /**
     * مهام الأسابيع 1-2
     */
    private function getWeek1_2Tasks() {
        $tasks = [
            [
                'id' => 'W1_1',
                'title' => 'تحليل شامل للتكاليف الحالية',
                'description' => 'مراجعة جميع بنود الإنفاق وتحديد نقاط الهدر',
                'priority' => 'urgent',
                'duration' => '2 days',
                'responsible' => 'المدير المالي',
                'tools' => ['Excel', 'QuickBooks', 'تقرير البنك']
            ],
            [
                'id' => 'W1_2',
                'title' => 'تحديد أكبر 5 نقاط نزيف مالي',
                'description' => 'تحليل الهدر الأكبر (إعلانات غير فعالة، اشتراكات غير مستخدمة، إلخ)',
                'priority' => 'urgent',
                'duration' => '1 day',
                'responsible' => 'فريق التحليل',
                'expected_savings' => '10,000-30,000 ريال/شهر'
            ],
            [
                'id' => 'W1_3',
                'title' => 'إيقاف الهدر الفوري',
                'description' => 'إلغاء الاشتراكات غير المستخدمة، إيقاف الإعلانات الخاسرة',
                'priority' => 'urgent',
                'duration' => '1 day',
                'responsible' => 'مدير العمليات',
                'quick_wins': true
            ]
        ];

        // إضافة مهام مخصصة حسب نقاط الضعف
        foreach ($this->weakPoints as $weakness) {
            if ($weakness['severity'] === 'critical') {
                $tasks[] = $this->getUrgentTaskForPillar($weakness['pillar']);
            }
        }

        return $tasks;
    }

    /**
     * مهام الأسابيع 3-4
     */
    private function getWeek3_4Tasks() {
        $tasks = [
            [
                'id' => 'W3_1',
                'title' => 'تحسين صفحة الهبوط الرئيسية',
                'description' => 'A/B Testing، تحسين النصوص، تحسين CTA',
                'priority' => 'high',
                'duration' => '3 days',
                'expected_impact' => 'زيادة التحويل 20-30%',
                'tools' => ['Google Optimize', 'Hotjar', 'Google Analytics']
            ],
            [
                'id' => 'W3_2',
                'title' => 'إطلاق حملة إعادة استهداف',
                'description' => 'استهداف الزوار السابقين بعروض مخصصة',
                'priority' => 'high',
                'duration' => '2 days',
                'budget' => '5,000 ريال',
                'expected_roi' => '300-500%'
            ],
            [
                'id' => 'W3_3',
                'title' => 'تحسين سرعة الموقع',
                'description' => 'تحسين الأداء، ضغط الصور، CDN',
                'priority' => 'medium',
                'duration' => '2 days',
                'expected_impact' => 'تحسين معدل الارتداد 15%'
            ],
            [
                'id' => 'W4_1',
                'title' => 'إعداد نظام متابعة آلي',
                'description' => 'Email automation، WhatsApp automation',
                'priority' => 'high',
                'duration' => '4 days',
                'tools' => ['Mailchimp', 'Zapier', 'WhatsApp API'],
                'expected_impact' => 'زيادة التحويل 25%'
            ]
        ];

        return $tasks;
    }

    /**
     * مهام الشهر الثاني
     */
    private function getMonth2Tasks() {
        return [
            [
                'id' => 'M2_1',
                'title' => 'بناء قمع مبيعات متكامل',
                'description' => 'Awareness → Interest → Decision → Action',
                'priority' => 'high',
                'duration' => '1 week',
                'components' => ['Landing Pages', 'Email Sequences', 'Retargeting', 'Sales Follow-up']
            ],
            [
                'id' => 'M2_2',
                'title' => 'تفعيل نظام CRM',
                'description' => 'تتبع العملاء المحتملين، أتمتة المتابعة',
                'priority' => 'high',
                'duration' => '1 week',
                'tools' => ['HubSpot', 'Pipedrive', 'Zoho CRM']
            ],
            [
                'id' => 'M2_3',
                'title' => 'تطوير محتوى تسويقي',
                'description' => 'مقالات، فيديوهات، دراسات حالة',
                'priority' => 'medium',
                'duration' => '2 weeks',
                'deliverables' => '10 مقالات + 5 فيديوهات + 3 دراسات حالة'
            ],
            [
                'id' => 'M2_4',
                'title' => 'تحسين تجربة العميل',
                'description' => 'User journey mapping، Pain points resolution',
                'priority' => 'high',
                'duration' => '1 week',
                'expected_impact' => 'زيادة الرضا 30%'
            ]
        ];
    }

    /**
     * مهام الشهر الثالث
     */
    private function getMonth3Tasks() {
        return [
            [
                'id' => 'M3_1',
                'title' => 'قياس وتحليل النتائج',
                'description' => 'مراجعة جميع المقاييس وتحليل الأداء',
                'priority' => 'urgent',
                'duration' => '3 days',
                'deliverables' => 'تقرير شامل بالنتائج'
            ],
            [
                'id' => 'M3_2',
                'title' => 'تعديل الاستراتيجية',
                'description' => 'تحسين بناءً على البيانات الفعلية',
                'priority' => 'high',
                'duration' => '2 days',
                'deliverables' => 'استراتيجية محسّنة'
            ],
            [
                'id' => 'M3_3',
                'title' => 'إعداد خطة الربع الثاني',
                'description' => 'التخطيط للمرحلة القادمة',
                'priority' => 'high',
                'duration' => '1 week',
                'deliverables' => 'خطة ربع سنوية تفصيلية'
            ],
            [
                'id' => 'M3_4',
                'title' => 'احتفال بالإنجازات',
                'description' => 'الاعتراف بالنجاحات وتحفيز الفريق',
                'priority' => 'medium',
                'duration' => '1 day',
                'budget' => '2,000-5,000 ريال'
            ]
        ];
    }

    /**
     * الموارد المطلوبة للـ 90 يوم
     */
    private function get90DayResources() {
        $baseResources = [
            'human_resources' => [
                'required_roles' => ['مدير مشروع', 'مسوق رقمي', 'مطور', 'محلل بيانات'],
                'time_commitment' => '40-60 ساعة/أسبوع إجمالي'
            ],
            'tools' => [
                'marketing' => ['Google Ads', 'Facebook Ads', 'Mailchimp'],
                'analytics' => ['Google Analytics', 'Hotjar', 'Mixpanel'],
                'automation' => ['Zapier', 'IFTTT', 'n8n'],
                'crm' => ['HubSpot', 'Pipedrive']
            ],
            'budget' => [
                'ads_budget' => '10,000-20,000 ريال',
                'tools_subscriptions' => '2,000-3,000 ريال',
                'content_creation' => '5,000-10,000 ريال',
                'consulting' => '15,000-30,000 ريال',
                'total' => '32,000-63,000 ريال'
            ]
        ];

        return $baseResources;
    }

    /**
     * مهمة عاجلة حسب الركيزة
     */
    private function getUrgentTaskForPillar($pillar) {
        $tasks = [
            'strategy' => [
                'id' => 'URG_STR',
                'title' => 'إعادة صياغة الاستراتيجية',
                'description' => 'ورشة عمل استراتيجية لتحديد الرؤية والأهداف',
                'priority' => 'critical',
                'duration' => '2 days',
                'responsible' => 'المؤسس/المدير التنفيذي'
            ],
            'marketing' => [
                'id' => 'URG_MKT',
                'title' => 'إيقاف الإعلانات الخاسرة فوراً',
                'description' => 'مراجعة جميع الحملات وإيقاف ROI السلبي',
                'priority' => 'critical',
                'duration' => '1 day',
                'expected_savings' => '5,000-15,000 ريال/شهر'
            ],
            'tech' => [
                'id' => 'URG_TCH',
                'title' => 'إصلاح المشاكل التقنية الحرجة',
                'description' => 'إصلاح الأخطاء التي تؤثر على التحويل',
                'priority' => 'critical',
                'duration' => '2-3 days',
                'expected_impact' => 'زيادة التحويل 20-40%'
            ],
            'operations' => [
                'id' => 'URG_OPS',
                'title' => 'تحسين العمليات الأساسية',
                'description' => 'تبسيط العمليات وإزالة الاختناقات',
                'priority' => 'critical',
                'duration' => '1 week',
                'expected_impact' => 'زيادة الكفاءة 30%'
            ]
        ];

        return $tasks[$pillar] ?? $tasks['strategy'];
    }

    /**
     * الحصول على KPIs ربع سنوية
     */
    private function getQuarterlyKPIs($quarter) {
        $baseKPIs = [
            1 => [
                'revenue_growth' => '+25%',
                'customer_acquisition' => '+30%',
                'efficiency' => '+15%',
                'customer_satisfaction' => '75%+'
            ],
            2 => [
                'revenue_growth' => '+40%',
                'customer_retention' => '85%+',
                'nps' => '50+',
                'profit_margin' => '+10%'
            ],
            3 => [
                'revenue_growth' => '+60%',
                'market_share' => '+25%',
                'new_products' => '2-3 منتجات',
                'partnerships' => '3-5 شراكات'
            ],
            4 => [
                'annual_growth' => '100%+',
                'customer_ltv' => '+50%',
                'team_efficiency' => '+40%',
                'profitability' => '+20%'
            ]
        ];

        return $baseKPIs[$quarter];
    }

    /**
     * معالم ربع سنوية
     */
    private function getQuarterlyMilestones($quarter) {
        // معالم مخصصة لكل ربع
        $milestones = [
            1 => [
                ['month' => 1, 'milestone' => 'إطلاق الاستراتيجية الجديدة'],
                ['month' => 2, 'milestone' => 'تفعيل الأنظمة الأساسية'],
                ['month' => 3, 'milestone' => 'تحقيق أول نتائج ملموسة']
            ],
            2 => [
                ['month' => 4, 'milestone' => 'مضاعفة معدل الاكتساب'],
                ['month' => 5, 'milestone' => 'إطلاق برنامج الولاء'],
                ['month' => 6, 'milestone' => 'تحقيق الربحية المستدامة']
            ],
            3 => [
                ['month' => 7, 'milestone' => 'دخول سوق جديد'],
                ['month' => 8, 'milestone' => 'إطلاق منتج/خدمة جديدة'],
                ['month' => 9, 'milestone' => 'تحقيق 3x growth']
            ],
            4 => [
                ['month' => 10, 'milestone' => 'تحسين الأنظمة والعمليات'],
                ['month' => 11, 'milestone' => 'تحقيق أهداف السنة'],
                ['month' => 12, 'milestone' => 'إعداد استراتيجية العام القادم']
            ]
        ];

        return $milestones[$quarter];
    }

    /**
     * ميزانية ربع سنوية
     */
    private function getQuarterlyBudget($quarter) {
        $multiplier = [
            'solo' => 1,
            'small' => 2,
            'medium' => 4,
            'large' => 8
        ][$this->companySize] ?? 1;

        $baseBudget = 50000 * $multiplier;

        return [
            'total' => $baseBudget,
            'breakdown' => [
                'marketing' => $baseBudget * 0.4,
                'technology' => $baseBudget * 0.25,
                'operations' => $baseBudget * 0.2,
                'consulting' => $baseBudget * 0.15
            ]
        ];
    }

    /**
     * مخاطر ربع سنوية
     */
    private function getQuarterlyRisks($quarter) {
        return [
            [
                'risk' => 'تأخر في التنفيذ',
                'probability' => 'medium',
                'impact' => 'high',
                'mitigation' => 'متابعة أسبوعية صارمة'
            ],
            [
                'risk' => 'نقص الموارد',
                'probability' => 'high',
                'impact' => 'medium',
                'mitigation' => 'الاستعانة بمصادر خارجية عند الحاجة'
            ],
            [
                'risk' => 'تغيرات السوق',
                'probability' => 'low',
                'impact' => 'high',
                'mitigation' => 'مراقبة مستمرة ومرونة في التكيف'
            ]
        ];
    }

    /**
     * الرؤية السنوية
     */
    private function getAnnualVision() {
        $currentScore = $this->diagnosticData['overall_score'] ?? 50;

        if ($currentScore < 40) {
            return 'من مرحلة الحاجة العاجلة إلى مؤسسة مستقرة ومربحة';
        } elseif ($currentScore < 65) {
            return 'من مرحلة التأسيس إلى النمو المتسارع والتوسع';
        } else {
            return 'من الريادة المحلية إلى الهيمنة على السوق';
        }
    }

    /**
     * الركائز الاستراتيجية السنوية
     */
    private function getStrategicPillars() {
        return [
            [
                'title' => 'التميز التشغيلي',
                'description' => 'بناء أنظمة وعمليات قوية',
                'key_initiatives' => ['أتمتة شاملة', 'تحسين الجودة', 'كفاءة الفريق']
            ],
            [
                'title' => 'النمو المستدام',
                'description' => 'زيادة الإيرادات والربحية',
                'key_initiatives' => ['توسيع القنوات', 'منتجات جديدة', 'أسواق جديدة']
            ],
            [
                'title' => 'تجربة العميل',
                'description' => 'رضا وولاء استثنائي',
                'key_initiatives' => ['خدمة متميزة', 'تخصيص الخدمات', 'برنامج ولاء']
            ],
            [
                'title' => 'الابتكار والتطوير',
                'description' => 'البقاء في المقدمة',
                'key_initiatives' => ['R&D', 'تقنيات جديدة', 'نماذج عمل مبتكرة']
            ]
        ];
    }

    /**
     * مقاييس النجاح السنوية
     */
    private function getAnnualKPIs() {
        return [
            'revenue' => [
                'current' => 'X',
                'target' => '3X-5X',
                'strategy' => 'زيادة العملاء + زيادة القيمة'
            ],
            'customers' => [
                'current' => 'Y',
                'target' => '10Y',
                'strategy' => 'قنوات متعددة + تحسين التحويل'
            ],
            'profitability' => [
                'current' => 'خسارة/تعادل',
                'target' => 'ربح 20-30%',
                'strategy' => 'خفض التكاليف + زيادة الأسعار'
            ],
            'team' => [
                'current' => 'Z',
                'target' => '2Z-3Z',
                'strategy' => 'توظيف استراتيجي + تطوير'
            ],
            'valuation' => [
                'current' => 'V',
                'target' => '5V-10V',
                'strategy' => 'نمو + ربحية + أنظمة'
            ]
        ];
    }

    /**
     * حساب الاستثمار السنوي المطلوب
     */
    private function calculateAnnualInvestment() {
        $multiplier = [
            'solo' => 1,
            'small' => 2,
            'medium' => 5,
            'large' => 10
        ][$this->companySize] ?? 1;

        $baseInvestment = 200000 * $multiplier;

        return [
            'total' => $baseInvestment,
            'breakdown' => [
                'marketing_sales' => $baseInvestment * 0.40,
                'technology' => $baseInvestment * 0.25,
                'team' => $baseInvestment * 0.20,
                'operations' => $baseInvestment * 0.10,
                'consulting' => $baseInvestment * 0.05
            ],
            'funding_sources' => [
                'revenue' => 60,
                'investment' => 30,
                'debt' => 10
            ]
        ];
    }

    /**
     * حساب العائد المتوقع
     */
    private function calculateExpectedROI() {
        return [
            'conservative' => '300%',
            'realistic' => '500%',
            'optimistic' => '1000%+',
            'timeframe' => '12 months',
            'payback_period' => '3-4 months'
        ];
    }

    /**
     * مقاييس النجاح
     */
    private function getSuccessMetrics() {
        return [
            'financial' => [
                'revenue_growth' => '300-500%',
                'profit_margin' => '20-30%',
                'customer_ltv' => '+100%',
                'cac_reduction' => '-40%'
            ],
            'operational' => [
                'efficiency' => '+50%',
                'automation' => '70%+',
                'quality_score' => '90%+',
                'time_to_market' => '-50%'
            ],
            'customer' => [
                'satisfaction' => '90%+',
                'retention' => '85%+',
                'nps' => '60+',
                'referral_rate' => '40%+'
            ],
            'team' => [
                'engagement' => '85%+',
                'productivity' => '+60%',
                'turnover' => '<10%',
                'skill_level' => '+40%'
            ]
        ];
    }

    /**
     * تقسيم شهري للربع
     */
    private function getQuarterlyMonthlyBreakdown($quarter) {
        // سيتم تطويره حسب الحاجة
        return [];
    }

    /**
     * تسمية مستوى النضج
     */
    private function getMaturityLabel($score) {
        if ($score >= 85) return 'ريادي';
        if ($score >= 65) return 'متقدم';
        if ($score >= 40) return 'تأسيسي';
        return 'حرج';
    }
}
