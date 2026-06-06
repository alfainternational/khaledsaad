<?php

namespace App\Http\Controllers\Web;

use App\Domain\Billing\Models\Plan;
use App\Enums\PlanStatus;
use App\Domain\Marketing\Models\BlogPost;
use App\Domain\Marketing\Models\CaseStudy;
use App\Domain\Marketing\Models\CmsPage;
use App\Domain\Marketing\Models\CommunityPost;
use App\Domain\Marketing\Models\MarketingTemplateHighlight;
use App\Domain\Marketing\Models\Partner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingWebsiteController extends Controller
{
    public function pricing(): View
    {
        $page = CmsPage::publishedBySlug('pricing');
        $plans = Plan::query()
            ->where('status', PlanStatus::Active)
            ->orderBy('monthly_price')
            ->get();

        return view('pages.marketing.pricing', [
            'page' => $page,
            'plans' => $plans,
            'title' => $page?->title ?? 'التسعير',
            'description' => $page?->meta_description ?? 'باقات وخطط المنصة الاستراتيجية.',
        ]);
    }

    public function blogIndex(Request $request): View
    {
        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.marketing.blog.index', [
            'posts' => $posts,
            'title' => 'المدونة',
            'description' => 'مقالات وتحديثات حول التسويق الاستراتيجي والمنصة.',
        ]);
    }

    public function blogShow(string $slug): View
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        // increment view count
        $post->incrementViews();

        $related = $post->related(3);

        // breadcrumbs
        $breadcrumbs = [
            ['label' => 'الرئيسية', 'url' => url('/')],
            ['label' => 'المدونة',  'url' => route('blog.index')],
            ['label' => $post->title, 'url' => null],
        ];

        return view('pages.marketing.blog.show', [
            'post'        => $post,
            'related'     => $related,
            'breadcrumbs' => $breadcrumbs,
            'title'       => $post->title,
            'description' => $post->meta_description ?? $post->excerpt,
        ]);
    }

    public function caseStudiesIndex(Request $request): View
    {
        $items = CaseStudy::query()
            ->published()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.marketing.case-studies.index', [
            'caseStudies' => $items,
            'title' => 'دراسات الحالة',
            'description' => 'قصص نجاح وتطبيقات عملية من منهجية المنصة.',
        ]);
    }

    public function caseStudyShow(string $slug): View
    {
        $study = CaseStudy::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('pages.marketing.case-studies.show', [
            'study' => $study,
            'title' => $study->title,
            'description' => $study->summary,
        ]);
    }

    public function communityIndex(Request $request): View
    {
        $posts = CommunityPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.marketing.community.index', [
            'posts' => $posts,
            'title' => 'المجتمع',
            'description' => 'نقاشات ومواضيع من مجتمع المنصة.',
        ]);
    }

    public function communityShow(string $slug): View
    {
        $post = CommunityPost::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('pages.marketing.community.show', [
            'post' => $post,
            'title' => $post->title,
            'description' => $post->excerpt ?: $post->title,
        ]);
    }

    public function contact(): View
    {
        $page = CmsPage::publishedBySlug('contact');

        return view('pages.marketing.contact', [
            'page' => $page,
            'title' => $page?->title ?? 'تواصل معنا',
            'description' => $page?->meta_description ?? 'تواصل مع فريق المنصة.',
        ]);
    }

    public function privacy(): View
    {
        $page = CmsPage::publishedBySlug('privacy');
        abort_if(! $page, 404);

        return view('pages.marketing.cms-legal', [
            'page' => $page,
            'title' => $page->title,
            'description' => $page->meta_description ?? $page->title,
        ]);
    }

    public function partnerships(): View
    {
        $page = CmsPage::publishedBySlug('partnerships');
        $partners = Partner::query()->published()->orderBy('sort_order')->orderBy('name')->get();

        return view('pages.marketing.partnerships', [
            'page' => $page,
            'partners' => $partners,
            'title' => $page?->title ?? 'الشراكات',
            'description' => $page?->meta_description ?? 'شركاؤنا في بناء المنظومة التسويقية.',
        ]);
    }

    public function terms(): View
    {
        $page = CmsPage::publishedBySlug('terms');
        abort_if(! $page, 404);

        return view('pages.marketing.cms-legal', [
            'page' => $page,
            'title' => $page->title,
            'description' => $page->meta_description ?? $page->title,
        ]);
    }

    public function guestTemplates(): View
    {
        $page = CmsPage::publishedBySlug('templates');
        $highlights = MarketingTemplateHighlight::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('pages.marketing.templates', [
            'page' => $page,
            'highlights' => $highlights,
            'title' => $page?->title ?? 'القوالب',
            'description' => $page?->meta_description ?? 'قوالب جاهزة لتسريع التنفيذ التسويقي.',
        ]);
    }

    public function guestStudio(): View
    {
        $page = CmsPage::publishedBySlug('studio');

        return view('pages.marketing.studio', [
            'page' => $page,
            'title' => $page?->title ?? 'الاستوديو الذكي',
            'description' => $page?->meta_description ?? 'حوّل سياق مشروعك إلى مخرجات تسويقية جاهزة.',
        ]);
    }
}
