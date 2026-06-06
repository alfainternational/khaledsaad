<?php

namespace App\Http\Requests\Web;

use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $persona = $this->normalizePersona($this->input('persona'), $this->input('workspace_type'));
        $awarenessLevel = $this->normalizeAwarenessLevel($this->input('awareness_level'), $persona);
        $primaryGoal = $this->normalizePrimaryGoal($this->input('primary_goal'), $persona);

        $this->merge([
            'workspace_name' => $this->input('workspace_name') ?: $this->input('_workspace_name_display'),
            'workspace_type' => $this->normalizeWorkspaceType($this->input('workspace_type'), $persona),
            'persona' => $persona,
            'awareness_level' => $awarenessLevel,
            'primary_goal' => $primaryGoal,
            'recommended_path' => $this->normalizeRecommendedPath(
                $this->input('recommended_path'),
                $persona,
                $primaryGoal,
                $awarenessLevel,
            ),
            'audience' => $this->filled('audience') ? $this->input('audience') : 'عملاء مناسبون لخدمتي',
            'content_locale' => ContentLocaleCatalog::exists($this->input('content_locale'))
                ? $this->input('content_locale')
                : 'ar_modern_fusha',
            'current_challenge' => $this->filled('current_challenge')
                ? $this->input('current_challenge')
                : $this->challengeDescription($this->input('_challenge_hint')),
            'official_social_links_json' => $this->normalizeList($this->input('official_social_links_json', $this->input('official_social_links'))),
            'competitors_json' => $this->normalizeCompetitors($this->input('competitors_json', $this->input('competitors'))),
            'analysis_goals_json' => $this->normalizeList($this->input('analysis_goals_json', $this->input('analysis_goals'))),
            'monitoring_enabled' => $this->boolean('monitoring_enabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', Rule::in(['personal', 'team', 'agency'])],
            'persona' => ['required', 'string', Rule::in(array_keys(PersonaCatalog::all()))],
            'awareness_level' => ['required', 'string', Rule::in(array_keys(AwarenessCatalog::all()))],
            'primary_goal' => ['required', 'string', Rule::in(array_keys(GoalCatalog::all()))],
            'recommended_path' => ['nullable', 'string', Rule::in(array_keys(PathCatalog::all()))],
            'audience' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'content_locale' => ['required', 'string', Rule::in(array_keys(ContentLocaleCatalog::all()))],
            'current_challenge' => ['nullable', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'project_name' => ['required', 'string', 'max:255'],
            'project_stage' => ['required', 'integer', 'min:1', 'max:5'],
            'sector' => ['nullable', 'string', Rule::in(['general_business', 'ecommerce', 'clinic', 'restaurant', 'b2b_services', 'education', 'saas'])],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'official_social_links_json' => ['nullable', 'array'],
            'official_social_links_json.*' => ['string', 'max:255'],
            'competitors_json' => ['nullable', 'array'],
            'competitors_json.*.label' => ['nullable', 'string', 'max:255'],
            'competitors_json.*.domain' => ['nullable', 'string', 'max:255'],
            'competitors_json.*.social_links' => ['nullable', 'array'],
            'competitors_json.*.social_links.*' => ['string', 'max:255'],
            'analysis_goals_json' => ['nullable', 'array'],
            'analysis_goals_json.*' => ['string', 'max:255'],
            'monitoring_enabled' => ['nullable', 'boolean'],
            'brief_business_summary' => ['nullable', 'string', 'max:1200'],
            'brief_offer' => ['nullable', 'string', 'max:1000'],
            'brief_ideal_customer' => ['nullable', 'string', 'max:1000'],
            'brief_primary_goal' => ['nullable', 'string', 'max:255'],
            'brief_success_metric' => ['nullable', 'string', 'max:255'],
            'brief_current_channels' => ['nullable', 'string', 'max:500'],
            'brief_priority' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function normalizePersona(?string $persona, ?string $workspaceType): string
    {
        if (PersonaCatalog::exists($persona)) {
            return $persona;
        }

        return PersonaCatalog::inferFromWorkspaceType($workspaceType);
    }

    private function normalizeWorkspaceType(?string $workspaceType, string $persona): string
    {
        if (in_array($workspaceType, ['personal', 'team', 'agency'], true)) {
            return $workspaceType;
        }

        return match ($persona) {
            'team' => 'team',
            'agency' => 'agency',
            default => 'personal',
        };
    }

    private function normalizeAwarenessLevel(?string $awarenessLevel, string $persona): string
    {
        if (AwarenessCatalog::exists($awarenessLevel)) {
            return $awarenessLevel;
        }

        return PersonaCatalog::defaultAwareness($persona);
    }

    private function normalizePrimaryGoal(?string $primaryGoal, string $persona): string
    {
        $legacyMap = [
            'clarify_message' => 'clarify_idea',
            'define_audience' => 'improve_marketing',
            'build_strategy' => 'build_90_day_plan',
            'increase_sales' => 'get_first_customers',
            'manage_clients' => 'build_90_day_plan',
        ];

        $normalizedGoal = $legacyMap[$primaryGoal] ?? $primaryGoal;

        if (GoalCatalog::exists($normalizedGoal)) {
            return $normalizedGoal;
        }

        return match ($persona) {
            'freelancer' => 'build_offer',
            'business' => 'improve_marketing',
            'team', 'agency' => 'build_90_day_plan',
            default => 'clarify_idea',
        };
    }

    private function normalizeRecommendedPath(
        ?string $recommendedPath,
        string $persona,
        string $primaryGoal,
        string $awarenessLevel,
    ): ?string {
        if ($recommendedPath === null || $recommendedPath === '') {
            return null;
        }

        if (PathCatalog::exists($recommendedPath)) {
            return $recommendedPath;
        }

        return PathCatalog::recommend($persona, $primaryGoal, $awarenessLevel);
    }

    private function challengeDescription(?string $challengeHint): ?string
    {
        return match ($challengeHint) {
            'no_message' => 'الرسالة التسويقية غير واضحة',
            'no_audience' => 'الجمهور المستهدف غير محدد بدقة',
            'no_plan' => 'لا توجد خطة أو مسار واضح',
            'no_conversion' => 'معدل التحويل ضعيف رغم وجود زيارات',
            'agency_manage' => 'إدارة عملاء متعددين وتنظيم العمل',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')->map(fn (string $item): string => trim($item))->values()->all();
        }

        $text = is_string($value) ? trim($value) : '';
        if ($text === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n|,/', $text) ?: [])->map(fn (string $item): string => trim($item))->filter()->values()->all();
    }

    /**
     * @return array<int, array{label: string, domain: string, social_links: array<int, string>}>
     */
    private function normalizeCompetitors(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    $item = trim($item);

                    return $item !== '' ? ['label' => $item, 'domain' => $item, 'social_links' => []] : null;
                }

                if (! is_array($item)) {
                    return null;
                }

                $label = trim((string) ($item['label'] ?? $item['domain'] ?? ''));
                $domain = trim((string) ($item['domain'] ?? ''));
                if ($label === '' && $domain === '') {
                    return null;
                }

                return [
                    'label' => $label !== '' ? $label : $domain,
                    'domain' => $domain,
                    'social_links' => $this->normalizeList($item['social_links'] ?? []),
                ];
            })->filter()->values()->all();
        }

        $text = is_string($value) ? trim($value) : '';
        if ($text === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->map(fn (string $item): array => ['label' => $item, 'domain' => $item, 'social_links' => []])
            ->values()
            ->all();
    }
}
