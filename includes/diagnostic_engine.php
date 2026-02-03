<?php
/**
 * Diagnostic Analysis Engine (v3.1)
 * يقوم بمعالجة البيانات وتوليد التوصيات استراتيجياً في الباك إند
 */

class DiagnosticEngine {
    
    public static function getMaturityLabel($score) {
        if($score >= 85) return 'الريادة الاستراتيجية';
        if($score >= 65) return 'النضج التشغيلي المتقدم';
        if($score >= 40) return 'مرحلة التأسيس الرقمي';
        return 'الحاجة لتدخل استراتيجي عاجل';
    }

    public static function getIndustryName($val) {
        $industries = [
            'ecommerce'   => 'التجارة الإلكترونية',
            'services'    => 'الخدمات المهنية',
            'tech'        => 'التقنية والناشئة',
            'realestate'  => 'العقارات والمقاولات',
            'fnb'         => 'الأغذية والمشروبات',
            'retail'      => 'تجارة التجزئة',
            'industry'    => 'التصنيع والإنتاج',
            'education'   => 'التعليم والتدريب',
            'healthcare'  => 'الرعاية الصحية',
            'other'       => 'قطاعات أخرى'
        ];
        return $industries[$val] ?? $val;
    }

    public static function generateSmartSummary($score, $pillars, $industry, $benchmark) {
        $indName = self::getIndustryName($industry);
        $intro = "التحليل الاستراتيجي لمنشأتكم في قطاع **{$indName}** يظهر تحقيق نتيجة إجمالية قدرها **{$score}%**. ";
        
        if($score > $benchmark) {
            $intro .= "تتفوق المنشأة حالياً على متوسط المنافسين في القطاع، مما يشير إلى وجود ميزة تنافسية يمكن استثمارها للتوسع. ";
        } else {
            $intro .= "النتيجة الحالية أقل من متوسط القطاع (البالغ {$benchmark}%)، مما يشير إلى وجود فجوات تشغيلية تتطلب معالجة فورية لاستعادة التنافسية. ";
        }

        $insights = "<br><br><strong>تحليل الفجوات والفرص:</strong><br>";
        
        // البحث عن أعلى وأقل ركيزة
        asort($pillars);
        $keys = array_keys($pillars);
        $minPillar = $keys[0];
        $maxPillar = $keys[count($keys)-1];

        $pnames = [
            'strategy'   => 'القيادة والاستراتيجية',
            'marketing'  => 'النمو والتسويق',
            'tech'       => 'التكنولوجيا والبيانات',
            'operations' => 'الجودة والعمليات'
        ];

        if($pillars[$maxPillar] > 70) {
            $insights .= "✅ نقطة القوة الرئيسية تكمن في **" . $pnames[$maxPillar] . "**، وهي المحرك الذي يجب الاعتماد عليه لدعم باقي الأقسام. ";
        }

        if($pillars[$minPillar] < 50) {
            $insights .= "⚠️ تم رصد فجوة حرجة في **" . $pnames[$minPillar] . "**، وهي تشكل حالياً عنق زجاجة يعيق نمو المنشأة ويستنزف الموارد.";
        }

        return $intro . $insights;
    }

    public static function generate3TierRoadmap($answers, $industry, $size, $pillars) {
        $p1 = []; $p2 = []; $p3 = [];

        // تحويل المصفوفة للتسهيل إذا كانت JSON
        if (is_string($answers)) $answers = json_decode($answers, true);

        // تصنيف المشاكل
        $critical = [];
        foreach($answers as $a) {
            if(isset($a['score']) && $a['score'] < 40) $critical[] = $a;
        }

        // المرحلة 1: التدفق النقدي
        if($industry === 'ecommerce' || $industry === 'retail') {
            $p1[] = ['title' => 'استعادة المبيعات المفقودة', 'advice' => 'تفعيل أتمتة السلال المتروكة فوراً، فهي المصدر الأسرع للسيولة المفقودة.'];
        } else {
            $p1[] = ['title' => 'نظام الإحالات (Referrals)', 'advice' => 'تحفيز العملاء الحاليين لجلب عملاء جدد مقابل قيمة مضافة لخفض تكلفة الاستحواذ.'];
        }

        if(!empty($critical)) {
            $p1[] = ['title' => 'معالجة فجوة: ' . $critical[0]['q'], 'advice' => $critical[0]['rec']];
        }

        // المرحلة 2: الأنظمة
        if($pillars['marketing'] < 70) {
            $p2[] = ['title' => 'تفعيل CRM مركزي', 'advice' => 'التوقف عن إدارة العملاء عبر الواتساب الفردي وجمع البيانات في نظام موحد.'];
        }
        $p2[] = ['title' => 'أتمتة صناعة المحتوى', 'advice' => 'استخدام الأنظمة الرقمية لإنتاج محتوى تسويقي مستدام يقلل الجهد البشري بنسبة 60%.'];

        // المرحلة 3: الريادة
        $p3[] = ['title' => 'سلطة المحتوى (Authority Building)', 'advice' => 'بناء براند شخصي أو مؤسسي يضعكم كمرجع أول في قطاعكم لتسهيل جذب العملاء النوعيين.'];
        $p3[] = ['title' => 'التحليلات التنبؤية المتقدمة', 'advice' => 'استخدام البيانات لنتنبؤ بسلوك العميل المستقبلي وتقديم عروض مخصصة قبل أن يطلبها.'];

        return ['p1' => $p1, 'p2' => $p2, 'p3' => $p3];
    }
}
