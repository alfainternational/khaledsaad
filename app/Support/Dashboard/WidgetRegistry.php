<?php

namespace App\Support\Dashboard;

class WidgetRegistry
{
    /**
     * @param  array<string, int>  $metrics
     * @return array<int, array<string, string|int>>
     */
    public function personaWidgets(string $persona, array $metrics, string $awareness = 'guided'): array
    {
        $awarenessCaption = match ($awareness) {
            'expert' => 'بصيغة عميقة وسريعة للقرار.',
            'structured' => 'بصيغة مرتبة قابلة للتنفيذ.',
            default => 'بصيغة مبسطة وواضحة.',
        };

        $widgets = match ($persona) {
            'freelancer' => [
                ['title' => 'العملاء النشطون', 'value' => $metrics['clients'], 'caption' => 'حسابات تحتاج متابعة وتنفيذ.'],
                ['title' => 'المخرجات الجاهزة', 'value' => $metrics['tool_runs'], 'caption' => 'نتائج قابلة للتحويل إلى عروض ورسائل.'],
                ['title' => 'جاهزية التنفيذ', 'value' => $metrics['active_projects'], 'caption' => 'مشاريع مفتوحة حالياً أمامك.'],
            ],
            'business' => [
                ['title' => 'المشاريع قيد التطوير', 'value' => $metrics['projects'], 'caption' => 'مبادرات تحتاج متابعة واستقرار.'],
                ['title' => 'تشغيلات التحليل', 'value' => $metrics['tool_runs'], 'caption' => 'مرات استخدام الأدوات لصنع قرارات أفضل.'],
                ['title' => 'الموافقات المفتوحة', 'value' => $metrics['pending_approvals'], 'caption' => 'عناصر تحتاج قراراً أو مراجعة.'],
            ],
            'team' => [
                ['title' => 'الأعضاء النشطون', 'value' => $metrics['members'], 'caption' => 'توزيع القوة التنفيذية داخل المساحة.'],
                ['title' => 'المشاريع المتقدمة', 'value' => $metrics['advanced_projects'], 'caption' => 'مشاريع في المرحلتين 4 و5.'],
                ['title' => 'الموافقات المعلقة', 'value' => $metrics['pending_approvals'], 'caption' => 'عناصر تنتظر مراجعة الفريق.'],
            ],
            'agency' => [
                ['title' => 'ملفات العملاء', 'value' => $metrics['clients'], 'caption' => 'عدد العملاء الذين تديرهم الوكالة الآن.'],
                ['title' => 'المشاريع المتعددة', 'value' => $metrics['projects'], 'caption' => 'مشاريع موزعة على العملاء والمراحل.'],
                ['title' => 'الاعتمادات المطلوبة', 'value' => $metrics['pending_approvals'], 'caption' => 'نقاط تحتاج قراراً من الفريق أو العميل.'],
            ],
            default => [
                ['title' => 'المشاريع الحالية', 'value' => $metrics['projects'], 'caption' => 'المساحة جاهزة للبدء والتنفيذ.'],
                ['title' => 'المخرجات المحفوظة', 'value' => $metrics['tool_runs'], 'caption' => 'نتائج الأدوات المتراكمة داخل العمل.'],
                ['title' => 'المراحل المكتملة', 'value' => $metrics['advanced_projects'], 'caption' => 'مشاريع تجاوزت مرحلة التأسيس الأولية.'],
            ],
        };

        return collect($widgets)
            ->map(function (array $widget) use ($awarenessCaption): array {
                $widget['caption'] .= ' '.$awarenessCaption;

                return $widget;
            })
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function roleWidgets(string $role): array
    {
        return match ($role) {
            'owner' => [
                ['title' => 'تحكم كامل', 'body' => 'أنت مسؤول عن الفوترة والأعضاء والصلاحيات وحدود الخطة.'],
                ['title' => 'مركز القرار', 'body' => 'راجع التقارير والموافقات وحدد أولويات التنفيذ والموارد.'],
            ],
            'admin' => [
                ['title' => 'تشغيل وإشراف', 'body' => 'لديك صلاحيات إدارة المحتوى والأعضاء دون طبقة الفوترة.'],
                ['title' => 'متابعة يومية', 'body' => 'استخدم لوحة العمل لضبط التنفيذ والتأكد من سير المشاريع.'],
            ],
            'editor' => [
                ['title' => 'تنفيذ وتطوير', 'body' => 'مساحتك الأساسية هي الأدوات والمشاريع والقوالب والمخرجات.'],
                ['title' => 'حافظ على التدفق', 'body' => 'راجع النتائج الأخيرة وحوّلها إلى مخرجات جاهزة أو مهام تالية.'],
            ],
            'contributor' => [
                ['title' => 'مساهمة حسب التكليف', 'body' => 'أدخل البيانات والمحتوى المطلوب منك دون إدارة كاملة للمساحة.'],
                ['title' => 'حدود واضحة', 'body' => 'استخدم المشاريع والأدوات المرتبطة بتكليفك الحالي فقط.'],
            ],
            'client' => [
                ['title' => 'بوابة مراجعة', 'body' => 'راجع التسليمات وأضف ملاحظاتك واعتمد ما أصبح جاهزاً.'],
                ['title' => 'رؤية مقيّدة', 'body' => 'لن ترى إلا ما يخصك من مشاريع ومخرجات وتقارير.'],
            ],
            default => [
                ['title' => 'متابعة فقط', 'body' => 'تستطيع قراءة الحالة العامة والتقارير دون تعديل تنفيذي.'],
                ['title' => 'نظرة واضحة', 'body' => 'استخدم التقارير والمخرجات لفهم ما تحقق وما يحتاج قراراً.'],
            ],
        };
    }
}
