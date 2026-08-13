<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Modules\Shared\I18n\TranslatedConfig;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function profile(): View
    {
        return view('site.pages.profile', ['brand' => TranslatedConfig::get('brand')]);
    }

    public function services(): View
    {
        return $this->page(
            'services',
            __('المشكلات والمخرجات'),
            __('ما الذي أبحث عنه في مشروعك، وما الذي تستلمه بعد التشخيص؟'),
            __('نبدأ من المشكلة التي تراها، ثم نصل إلى الفجوة التي يمكن قياسها وترتيبها وتحويلها إلى خطوة.'),
            [
                ['title' => __('المشكلات التي نبدأ منها'), 'items' => TranslatedConfig::get('brand.problems')],
                ['title' => __('ما تخرج به'), 'items' => TranslatedConfig::get('brand.services')],
            ],
        );
    }

    public function methodology(): View
    {
        return $this->page(
            'methodology',
            __('منهجية العمل'),
            __('من السؤال الأول إلى خطوة قابلة للتنفيذ'),
            __('لا أزيد الأدوات قبل فهم السبب. أسمع وصفك، وأفصل العرض عن جذره، ثم أرتّب الفجوات على الأثر والجهد.'),
            [['title' => __('المراحل الأربع'), 'items' => TranslatedConfig::get('brand.method'), 'ordered' => true]],
        );
    }

    public function principles(): View
    {
        return $this->page(
            'principles',
            __('مبادئ العمل'),
            __('كيف نحافظ على وضوح النتيجة وحدودها؟'),
            __('الثقة هنا لا تأتي من ادعاء نتيجة، بل من معرفة ما قيس، وما استنتج، وما يمكن تنفيذه ومراجعته.'),
            [['title' => __('المبادئ التي تحكم التشخيص'), 'items' => TranslatedConfig::get('brand.principles')]],
        );
    }

    public function knowledge(): View
    {
        return $this->page(
            'knowledge',
            __('المعرفة والمحتوى'),
            __('محتوى عملي يساعدك على فهم التسويق وتطبيقه'),
            __('مقالات ودروس ونشرة تعليمية تربط الفكرة بمثال وخطوة، من دون تغيير النص الأصلي للمادة المنشورة.'),
            [['title' => __('الموضوعات الأساسية'), 'items' => TranslatedConfig::get('brand.knowledge')]],
            Content::query()->published()->orderByDesc('published_at')->limit(6)->get(),
        );
    }

    public function faq(): View
    {
        return $this->page(
            'faq',
            __('الأسئلة الشائعة'),
            __('إجابات واضحة قبل أن تبدأ'),
            __('تعرف كيف يعمل التشخيص، وكيف تحفظ البيانات، وما الذي يمكن مشاركته مع فريقك أو وكالتك.'),
            [['title' => __('ما الذي تريد معرفته؟'), 'items' => TranslatedConfig::get('brand.faqs'), 'faq' => true]],
        );
    }

    public function sampleReport(): View
    {
        return $this->page(
            'sample-report',
            __('نموذج النتيجة'),
            __('هكذا تساعدك النتيجة على اتخاذ القرار'),
            __('هذا مثال توضيحي غير منسوب إلى عميل. يبين شكل الدرجة والفجوات والخطوة التالية فقط.'),
            [[
                'title' => __('تقرير تشخيص توضيحي'),
                'sample' => true,
                /*
                 * كل بند يحمل مستوى دليله (§٤.١).
                 *
                 * **سبب وجوده:** هذه الصفحة تُري الزائر شكل المخرج، فما تحذفه
                 * منها يتعلّمه أنه ليس جزءًا من المنتج. كانت البنود الثلاثة
                 * جملًا سببية بصيغة الجزم بلا وسم واحد — وهو ما يمنعه §٤.١
                 * صراحةً، ويُفوّت في الوقت نفسه أوضح فرق بيننا وبين مولّد نصوص.
                 * والعيّنة تُري التدرّج كاملًا: ما رُصد وما استُنتج جنبًا إلى جنب.
                 */
                'items' => [
                    ['title' => __('سبب الشراء منك غير مكتوب'), 'description' => __('أثر مرتفع · جهد منخفض'), 'evidence' => 'inferred'],
                    ['title' => __('لا توجد صفحة تنقل الزائر إلى الشراء'), 'description' => __('أثر مرتفع · جهد متوسط'), 'evidence' => 'measured'],
                    ['title' => __('لا تعرف من أين جاءك العميل'), 'description' => __('أثر متوسط · جهد منخفض'), 'evidence' => 'inferred'],
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
            'brand' => TranslatedConfig::get('brand'),
            'pageKey' => $key,
            'label' => $label,
            'title' => $title,
            'description' => $description,
            'collections' => $collections,
            'latestContent' => $latestContent ?? collect(),
        ]);
    }
}
