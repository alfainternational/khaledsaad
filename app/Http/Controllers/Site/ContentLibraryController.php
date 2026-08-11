<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Modules\Learning\MarketingCourseGalleryPresenter;
use App\Modules\Shared\I18n\TranslatedConfig;
use App\Services\Content\ContentAccessService;
use App\Support\Content\ContentStructuredData;
use App\Support\Content\LearningPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentLibraryController extends Controller
{
    public function __construct(
        private readonly ContentAccessService $access,
        private readonly LearningPresenter $learningPresenter,
        private readonly ContentStructuredData $structuredData,
        private readonly MarketingCourseGalleryPresenter $marketingCourseGallery,
    ) {}

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), Content::types(), true)
            ? $request->query('type')
            : null;
        $search = Str::limit(trim((string) $request->query('q')), 100, '');
        $category = ContentCategory::query()
            ->active()
            ->where('slug', $request->query('category'))
            ->first();

        $contentsQuery = Content::query()
            ->with(['category', 'translations' => fn ($query) => $query->where('locale', app()->getLocale())])
            ->published()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($category?->slug === 'تعلم-التسويق', fn ($query) => $query->where('source_key', 'like', 'marketing-course-%'))
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                /*
                 * البحث يشمل الترجمة لا الأصل وحده: قارئ الواجهة
                 * الإنجليزية يكتب مصطلحه بالإنجليزية، وحصر البحث في
                 * العمود العربي يعطيه «لا نتائج» عن درس مترجم أمامه.
                 */
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term)
                    ->orWhereHas('translations', fn ($translated) => $translated
                        ->where('locale', app()->getLocale())
                        ->where(fn ($field) => $field
                            ->where('title', 'like', $term)
                            ->orWhere('excerpt', 'like', $term))));
            });

        if ($category?->slug === 'تعلم-التسويق') {
            $contentsQuery
                ->orderByRaw('CASE WHEN learning_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('learning_order');
        } else {
            $contentsQuery->orderByDesc('published_at');
        }

        $contents = $contentsQuery
            ->paginate($category?->slug === 'تعلم-التسويق' ? 24 : 12)
            ->withQueryString();

        // تركيب الترجمة بعد الترقيم: العناوين والمقتطفات في البطاقات
        // تتبع لغة الواجهة، وما لا ترجمة له يبقى بأصله معلنًا عنه.
        $contents->getCollection()->each->localize();

        $categories = ContentCategory::query()
            ->active()
            ->withCount(['contents as published_contents_count' => fn ($query) => $query->published()])
            ->ordered()
            ->get();
        $typeCounts = Content::query()
            ->published()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count) => (int) $count);
        $totalCount = $typeCounts->sum();

        $brand = TranslatedConfig::get('brand');

        return view('site.content.index', compact(
            'contents',
            'type',
            'search',
            'category',
            'categories',
            'typeCounts',
            'totalCount',
            'brand',
        ));
    }

    public function show(Request $request, Content $content): View
    {
        abort_unless($content->isPublished(), 404);

        $content->load([
            'category',
            'sections.items',
            'resources.media',
            'translations' => fn ($query) => $query->where('locale', app()->getLocale()),
        ]);

        /*
         * الترجمة تُركَّب قبل العارضين والبيانات المنظَّمة لا بعدهم:
         * `ContentStructuredData` يبني `schema.org` من العنوان والوصف،
         * وبناؤه من الأصل العربي في صفحة إنجليزية يعطي محركات البحث
         * لغةً غير التي يراها الزائر.
         */
        $content->localize();

        $unlocked = $this->access->canView($content, $this->access->tokenFrom($request));
        $learning = $this->learningPresenter->present($content);
        $learningGallery = $content->source_key === 'marketing-course-20'
            ? $this->marketingCourseGallery->present($request->user())
            : ['enabled' => false];

        $relatedContents = Content::query()
            ->with(['category', 'translations' => fn ($query) => $query->where('locale', app()->getLocale())])
            ->published()
            ->whereKeyNot($content->getKey())
            ->when(
                str_starts_with((string) $content->source_key, 'marketing-course-'),
                fn ($query) => $query->where('source_key', 'like', 'marketing-course-%'),
                fn ($query) => $query->when(
                    $content->category_id,
                    fn ($query) => $query->where('category_id', $content->category_id),
                    fn ($query) => $query->where('type', $content->type),
                ),
            )
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->each->localize();

        $brand = TranslatedConfig::get('brand');
        $structuredData = $this->structuredData->forContent($content, $learning, $unlocked);

        return view('site.content.show', compact('content', 'unlocked', 'relatedContents', 'brand', 'learning', 'learningGallery', 'structuredData'));
    }
}
