<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Billing\Models\Plan;
use App\Domain\Marketing\Models\BlogPost;
use App\Domain\Marketing\Models\CaseStudy;
use App\Domain\Tool\Models\Tool;
use App\Enums\PlanStatus;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\StageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * المحتوى العام للتطبيق (تجربة الضيف قبل تسجيل الدخول) — نفس ما تعرضه صفحات
 * الويب العامة: المسارات، المراحل والأدوات، القوالب، الباقات، وأحدث المحتوى.
 * بلا مصادقة، مُكاش 10 دقائق، قراءة فقط، ولا يكشف أي بيانات مستخدمين.
 */
class PublicContentController
{
    public function overview(): JsonResponse
    {
        $data = Cache::remember('public_content:overview:v1', now()->addMinutes(10), function (): array {
            $tools = Tool::query()
                ->whereIn('status', ['published', 'beta'])
                ->orderBy('stage')
                ->orderBy('sort_order')
                ->get(['code', 'name', 'description', 'stage', 'estimated_minutes', 'output_type']);

            $stages = collect(StageCatalog::all())
                ->map(fn (array $stage, int $number): array => [
                    'number' => $number,
                    'label' => (string) ($stage['label'] ?? ''),
                    'description' => (string) ($stage['description'] ?? ''),
                    'tools' => $tools
                        ->where('stage', $number)
                        ->map(fn (Tool $tool): array => [
                            'code' => $tool->code,
                            'name' => $tool->name,
                            'description' => (string) $tool->description,
                            'estimated_minutes' => (int) ($tool->estimated_minutes ?? 0),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();

            $paths = collect(PathCatalog::all())
                ->map(fn (array $path, string $key): array => [
                    'key' => $key,
                    'label' => (string) ($path['label'] ?? ''),
                    'description' => (string) ($path['description'] ?? ''),
                    'stage_focus' => array_values((array) ($path['stage_focus'] ?? [])),
                ])
                ->values()
                ->all();

            $templates = AITemplate::query()
                ->where('status', 'published')
                ->orderBy('module')
                ->orderBy('name')
                ->get(['name', 'description', 'domain', 'credit_cost'])
                ->map(fn (AITemplate $template): array => [
                    'name' => $template->name,
                    'description' => (string) $template->description,
                    'domain' => (string) ($template->domain ?? ''),
                    'credit_cost' => (int) $template->credit_cost,
                ])
                ->all();

            $plans = Plan::query()
                ->where('status', PlanStatus::Active)
                ->orderBy('monthly_price')
                ->get(['code', 'name_ar', 'monthly_price', 'annual_price', 'features_json'])
                ->map(fn (Plan $plan): array => [
                    'code' => $plan->code,
                    'name' => (string) $plan->name_ar,
                    'monthly_price' => (float) $plan->monthly_price,
                    'annual_price' => (float) $plan->annual_price,
                    'features' => array_values(array_filter(array_map(
                        fn ($f): string => is_string($f) ? trim($f) : '',
                        (array) ($plan->features_json['highlights'] ?? $plan->features_json ?? []),
                    ))),
                ])
                ->all();

            $blog = BlogPost::query()
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(['title', 'slug', 'excerpt', 'category', 'reading_time_minutes', 'published_at'])
                ->map(fn (BlogPost $post): array => [
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => (string) $post->excerpt,
                    'category' => (string) ($post->category ?? ''),
                    'reading_time_minutes' => (int) ($post->reading_time_minutes ?? 0),
                    'published_at' => optional($post->published_at)->toDateString(),
                    'url' => url('/blog/'.$post->slug),
                ])
                ->all();

            $caseStudies = CaseStudy::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->limit(5)
                ->get(['title', 'slug', 'client_name', 'industry', 'summary'])
                ->map(fn (CaseStudy $case): array => [
                    'title' => $case->title,
                    'client_name' => (string) ($case->client_name ?? ''),
                    'industry' => (string) ($case->industry ?? ''),
                    'summary' => (string) ($case->summary ?? ''),
                    'url' => url('/case-studies/'.$case->slug),
                ])
                ->all();

            return [
                'hero' => [
                    'title' => 'منصة تزيد وضوح التسويق وتنقله للتنفيذ',
                    'subtitle' => 'ابدأ بمشروعك، اعرف الخلل الحقيقي، ثم حوّل التشخيص إلى أدوات وقرارات ومخرجات قابلة للقياس.',
                ],
                'paths' => $paths,
                'stages' => $stages,
                'templates' => $templates,
                'plans' => $plans,
                'blog' => $blog,
                'case_studies' => $caseStudies,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
