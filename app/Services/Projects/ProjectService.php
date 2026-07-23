<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use App\Services\Tools\ProjectAnswerMemory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(
        private readonly ProjectAnswerMemory $memory,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Project
    {
        $workspace = $user->primaryWorkspace();

        // حد المشاريع من خطة الاشتراك. الخطة المجانية تُضمَن هنا لأول مستخدم.
        if (! $this->subscriptions->canCreateProject($workspace)) {
            $limit = $this->subscriptions->projectLimit($workspace);

            throw ValidationException::withMessages([
                'name' => "بلغت حد مشاريع خطتك ({$limit}). رقِّ خطتك لإضافة مشاريع أكثر.",
            ]);
        }

        $project = $workspace->projects()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($workspace->id, $data['name']),
            'industry' => $data['industry'] ?? null,
            'stage' => $data['stage'] ?? 'growth',
        ]);

        $project->profile()->create([
            'business_model' => $data['business_model'] ?? null,
            'description' => $data['description'] ?? null,
            'geography' => $data['geography'] ?? null,
            'website' => $data['website'] ?? null,
            'monthly_budget' => $data['monthly_budget'] ?? null,
            'primary_goal' => $data['primary_goal'] ?? null,
            'value_proposition' => $data['value_proposition'] ?? null,
        ]);

        // ما يُكتب في ملف المشروع تعرفه الأدوات فورًا فلا تسأل عنه مرة أخرى.
        $this->memory->rememberProfile($project, $data);

        return $project->load('profile');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(Project $project, array $data): Project
    {
        $project->update(array_filter([
            'name' => $data['name'] ?? null,
            'industry' => $data['industry'] ?? null,
            'stage' => $data['stage'] ?? null,
        ], fn ($value) => $value !== null));

        $project->profile()->updateOrCreate([], array_intersect_key($data, array_flip([
            'business_model', 'description', 'geography', 'website',
            'monthly_budget', 'primary_goal', 'value_proposition',
        ])));

        $this->memory->rememberProfile($project, $data);

        return $project->refresh()->load('profile');
    }

    private function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while (Project::where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(bool $creating = true): array
    {
        return [
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:120',
            'industry' => 'nullable|string|max:120',
            'stage' => 'nullable|in:idea,launch,growth,scale',
            'business_model' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:2000',
            'geography' => 'nullable|string|max:120',
            'website' => 'nullable|url|max:255',
            'monthly_budget' => 'nullable|integer|min:0',
            'primary_goal' => 'nullable|string|max:60',
            'value_proposition' => 'nullable|string|max:1000',
        ];
    }
}
