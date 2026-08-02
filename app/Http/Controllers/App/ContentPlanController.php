<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContentPlan;
use App\Models\ContentPost;
use App\Models\Project;
use App\Services\Content\ContentPlanDocxImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentPlanController extends Controller
{
    public function __construct(private readonly ContentPlanDocxImporter $importer) {}

    public function index(Request $request): View
    {
        $projects = $request->user()->primaryWorkspace()->projects()->orderBy('name')->get();
        $status = $request->query('status') === ContentPlan::STATUS_ARCHIVED
            ? ContentPlan::STATUS_ARCHIVED
            : ContentPlan::STATUS_ACTIVE;
        $plans = ContentPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('status', $status)
            ->with(['project', 'posts'])
            ->latest('month')
            ->get();

        return view('app.content-plans.index', compact('projects', 'plans', 'status'));
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer',
            'document' => 'required|file|mimes:docx|max:5120',
        ], [
            'document.mimes' => 'ارفع ملف Word بصيغة DOCX.',
            'document.max' => 'حجم ملف الخطة يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $project = $this->ownedProject($request, (int) $validated['project_id']);
        $file = $request->file('document');
        $plan = $this->importer->import($file->getRealPath(), $project, $request->user());
        $plan->update(['source_filename' => $file->getClientOriginalName()]);

        return redirect()->route('app.content-plans.show', $plan)
            ->with('status', "استُوردت الخطة وبطاقاتها وعددها {$plan->posts->count()}.");
    }

    public function show(Request $request, ContentPlan $contentPlan): View
    {
        $plan = $this->ownedPlan($request, $contentPlan)->load(['project', 'posts']);
        $posts = $plan->posts;
        $activePosts = $posts->whereNull('archived_at')->values();
        $pillars = $activePosts->pluck('pillar')->filter()->unique()->sort()->values();
        $calendar = $activePosts->groupBy(fn (ContentPost $post) => $post->publish_at->day);
        $stages = $activePosts->groupBy(fn (ContentPost $post) => $post->workflowStage());

        $metrics = [
            'total' => $activePosts->count(),
            'awaiting_design' => $activePosts->where('requires_design', true)->whereNull('designed_at')->count(),
            'ready' => $activePosts->whereNotNull('reviewed_at')
                ->whereNull('x_published_at')->whereNull('linkedin_published_at')->count(),
            'published' => $activePosts->filter(
                fn (ContentPost $post) => $post->x_published_at !== null || $post->linkedin_published_at !== null,
            )->count(),
            'progress' => $plan->progressPercent(),
        ];

        return view('app.content-plans.show', compact(
            'plan', 'posts', 'activePosts', 'pillars', 'calendar', 'stages', 'metrics',
        ));
    }

    public function storePost(Request $request, ContentPlan $contentPlan): RedirectResponse
    {
        $plan = $this->ownedPlan($request, $contentPlan);
        $data = $this->postData($request);
        $data['position'] = ((int) $plan->posts()->max('position')) + 1;
        $plan->posts()->create($data);

        return back()->with('status', 'أُضيف المنشور إلى الخطة.');
    }

    public function updatePost(Request $request, ContentPost $contentPost): RedirectResponse
    {
        $post = $this->ownedPost($request, $contentPost);
        $post->update($this->postData($request));

        return back()->with('status', 'حُفظت تعديلات المنشور.');
    }

    public function workflow(Request $request, ContentPost $contentPost): RedirectResponse
    {
        $post = $this->ownedPost($request, $contentPost);
        $validated = $request->validate([
            'step' => 'required|in:designed,reviewed,x_published,linkedin_published',
            'completed' => 'required|boolean',
        ]);
        $step = $validated['step'];
        $completed = (bool) $validated['completed'];

        if ($completed && $step === 'reviewed' && $post->requires_design && $post->designed_at === null) {
            throw ValidationException::withMessages(['workflow' => 'أكمل التصميم قبل اعتماد المراجعة.']);
        }

        if ($completed && in_array($step, ['x_published', 'linkedin_published'], true) && $post->reviewed_at === null) {
            throw ValidationException::withMessages(['workflow' => 'اعتمد المراجعة قبل تسجيل النشر.']);
        }

        $column = $step.'_at';
        $updates = [$column => $completed ? now() : null];

        if (! $completed && $step === 'designed') {
            $updates = array_merge($updates, [
                'reviewed_at' => null,
                'x_published_at' => null,
                'linkedin_published_at' => null,
            ]);
        } elseif (! $completed && $step === 'reviewed') {
            $updates = array_merge($updates, [
                'x_published_at' => null,
                'linkedin_published_at' => null,
            ]);
        }

        $post->update($updates);

        return back()->with('status', 'تحدّثت حالة التنفيذ.');
    }

    public function metrics(Request $request, ContentPost $contentPost): RedirectResponse
    {
        $post = $this->ownedPost($request, $contentPost);

        if ($post->x_published_at === null && $post->linkedin_published_at === null) {
            throw ValidationException::withMessages([
                'metrics' => 'سجّل نشر المنشور أولًا قبل إضافة أرقام الأداء.',
            ]);
        }

        $validated = $request->validate([
            'x_reach' => 'nullable|integer|min:0',
            'x_engagement' => 'nullable|integer|min:0',
            'linkedin_reach' => 'nullable|integer|min:0',
            'linkedin_engagement' => 'nullable|integer|min:0',
        ]);
        $post->update($validated + ['measured_at' => now()]);

        return back()->with('status', 'حُفظت أرقام الأداء.');
    }

    public function archivePost(Request $request, ContentPost $contentPost): RedirectResponse
    {
        $post = $this->ownedPost($request, $contentPost);
        $post->update(['archived_at' => $post->archived_at === null ? now() : null]);

        return back()->with('status', $post->archived_at === null ? 'استُعيد المنشور.' : 'أُرشف المنشور.');
    }

    public function archivePlan(Request $request, ContentPlan $contentPlan): RedirectResponse
    {
        $plan = $this->ownedPlan($request, $contentPlan);
        $plan->update([
            'status' => $plan->status === ContentPlan::STATUS_ACTIVE
                ? ContentPlan::STATUS_ARCHIVED
                : ContentPlan::STATUS_ACTIVE,
        ]);

        return redirect()->route('app.content-plans.index')
            ->with('status', $plan->status === ContentPlan::STATUS_ARCHIVED ? 'أُرشفت الخطة.' : 'استُعيدت الخطة.');
    }

    /** @return array<string, mixed> */
    private function postData(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:220',
            'publish_at' => 'required|date',
            'pillar' => 'required|string|max:140',
            'x_content' => 'required|string|min:10|max:5000',
            'linkedin_content' => 'required|string|min:10|max:15000',
            'design_brief' => 'nullable|string|max:15000',
            'publishing_notes' => 'nullable|string|max:10000',
            'alt_text' => 'nullable|string|max:2000',
            'hashtags_text' => 'nullable|string|max:1000',
            'requires_design' => 'nullable|boolean',
        ]);
        $hashtags = [];
        preg_match_all('/#[\p{L}\p{N}_]+/u', (string) ($validated['hashtags_text'] ?? ''), $matches);
        $hashtags = array_values(array_unique($matches[0] ?? []));

        return Arr::except($validated, ['hashtags_text']) + [
            'hashtags' => $hashtags,
            'requires_design' => (bool) ($validated['requires_design'] ?? false),
        ];
    }

    private function ownedProject(Request $request, int $id): Project
    {
        return Project::query()
            ->whereKey($id)
            ->whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->firstOrFail();
    }

    private function ownedPlan(Request $request, ContentPlan $plan): ContentPlan
    {
        return ContentPlan::query()
            ->whereKey($plan->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function ownedPost(Request $request, ContentPost $post): ContentPost
    {
        return ContentPost::query()
            ->whereKey($post->id)
            ->whereHas('plan', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }
}
