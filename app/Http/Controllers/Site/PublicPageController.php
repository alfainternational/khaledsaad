<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function profile(): View
    {
        return view('site.pages.profile', ['brand' => config('brand')]);
    }

    public function services(): View
    {
        return $this->page(
            'services',
            'المشكلات والمخرجات',
            'ما الذي نبحث عنه في مشروعك، وما الذي تستلمه بعد التشخيص؟',
            'نبدأ من المشكلة التي تراها، ثم نصل إلى الفجوة التي يمكن قياسها وترتيبها وتحويلها إلى خطوة.',
            [
                ['title' => 'المشكلات التي نبدأ منها', 'items' => config('brand.problems')],
                ['title' => 'ما تخرج به', 'items' => config('brand.services')],
            ],
        );
    }

    public function methodology(): View
    {
        return $this->page(
            'methodology',
            'منهجية العمل',
            'من السؤال الأول إلى خطوة قابلة للتنفيذ',
            'لا نزيد الأدوات قبل فهم السبب. نسمع وصفك، ونفصل العرض عن جذره، ثم نرتب الفجوات على الأثر والجهد.',
            [['title' => 'المراحل الأربع', 'items' => config('brand.method'), 'ordered' => true]],
        );
    }

    public function principles(): View
    {
        return $this->page(
            'principles',
            'مبادئ العمل',
            'كيف نحافظ على وضوح النتيجة وحدودها؟',
            'الثقة هنا لا تأتي من ادعاء نتيجة، بل من معرفة ما قيس، وما استنتج، وما يمكن تنفيذه ومراجعته.',
            [['title' => 'المبادئ التي تحكم التشخيص', 'items' => config('brand.principles')]],
        );
    }

    public function knowledge(): View
    {
        return $this->page(
            'knowledge',
            'المعرفة والمحتوى',
            'محتوى عملي يساعدك على فهم التسويق وتطبيقه',
            'مقالات ودروس ونشرة تعليمية تربط الفكرة بمثال وخطوة، من دون تغيير النص الأصلي للمادة المنشورة.',
            [['title' => 'الموضوعات الأساسية', 'items' => config('brand.knowledge')]],
            Content::query()->published()->orderByDesc('published_at')->limit(6)->get(),
        );
    }

    public function faq(): View
    {
        return $this->page(
            'faq',
            'الأسئلة الشائعة',
            'إجابات واضحة قبل أن تبدأ',
            'تعرف كيف يعمل التشخيص، وكيف تحفظ البيانات، وما الذي يمكن مشاركته مع فريقك أو وكالتك.',
            [['title' => 'ما الذي تريد معرفته؟', 'items' => config('brand.faqs'), 'faq' => true]],
        );
    }

    public function sampleReport(): View
    {
        return $this->page(
            'sample-report',
            'نموذج النتيجة',
            'هكذا تساعدك النتيجة على اتخاذ القرار',
            'هذا مثال توضيحي غير منسوب إلى عميل. يبين شكل الدرجة والفجوات والخطوة التالية فقط.',
            [[
                'title' => 'تقرير تشخيص توضيحي',
                'sample' => true,
                'items' => [
                    ['title' => 'سبب الشراء منك غير مكتوب', 'description' => 'أثر مرتفع · جهد منخفض'],
                    ['title' => 'لا توجد صفحة تنقل الزائر إلى الشراء', 'description' => 'أثر مرتفع · جهد متوسط'],
                    ['title' => 'لا تعرف من أين جاءك العميل', 'description' => 'أثر متوسط · جهد منخفض'],
                ],
            ]],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $collections
     */
    private function page(
        string $key,
        string $label,
        string $title,
        string $description,
        array $collections,
        mixed $latestContent = null,
    ): View {
        return view('site.pages.show', [
            'brand' => config('brand'),
            'pageKey' => $key,
            'label' => $label,
            'title' => $title,
            'description' => $description,
            'collections' => $collections,
            'latestContent' => $latestContent ?? collect(),
        ]);
    }
}
