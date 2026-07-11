<?php

namespace App\Http\Requests\Web;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'official_social_links_json' => $this->normalizeList($this->input('official_social_links_json', $this->input('official_social_links'))),
            'verified_social_profiles_json' => $this->normalizeVerifiedSocialProfiles($this->input('verified_social_profiles_json', [])),
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
        /** @var Workspace|null $workspace */
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0)
                ),
            ],
            'stage' => ['required', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', Rule::in(['active', 'paused', 'completed', 'archived'])],
            'sector' => ['required', 'string', Rule::in(['general_business', 'ecommerce', 'clinic', 'restaurant', 'b2b_services', 'education', 'saas'])],
            'market_country' => ['nullable', 'string', 'max:120'],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'official_social_links_json' => ['nullable', 'array'],
            'official_social_links_json.*' => ['string', 'max:255'],
            'verified_social_profiles_json' => ['nullable', 'array'],
            'verified_social_profiles_json.*.network' => ['nullable', 'string', 'max:80'],
            'verified_social_profiles_json.*.url' => ['nullable', 'string', 'max:255'],
            'verified_social_profiles_json.*.handle' => ['nullable', 'string', 'max:120'],
            'verified_social_profiles_json.*.title' => ['nullable', 'string', 'max:255'],
            'verified_social_profiles_json.*.description' => ['nullable', 'string', 'max:500'],
            'verified_social_profiles_json.*.primary_cta' => ['nullable', 'string', 'max:255'],
            'verified_social_profiles_json.*.links_back_to_site' => ['nullable', 'boolean'],
            'verified_social_profiles_json.*.verification_notes' => ['nullable', 'string', 'max:500'],
            'competitors_json' => ['nullable', 'array'],
            'competitors_json.*.label' => ['nullable', 'string', 'max:255'],
            'competitors_json.*.domain' => ['nullable', 'string', 'max:255'],
            'competitors_json.*.social_links' => ['nullable', 'array'],
            'competitors_json.*.social_links.*' => ['string', 'max:255'],
            'analysis_goals_json' => ['nullable', 'array'],
            'analysis_goals_json.*' => ['string', 'max:255'],
            'monitoring_enabled' => ['nullable', 'boolean'],
        ];
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

        return preg_split('/\r\n|\r|\n|,/', $text) === false
            ? []
            : collect(preg_split('/\r\n|\r|\n|,/', $text) ?: [])->map(fn (string $item): string => trim($item))->filter()->values()->all();
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeVerifiedSocialProfiles(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $profile = [
                    'network' => trim((string) ($item['network'] ?? '')),
                    'url' => trim((string) ($item['url'] ?? '')),
                    'handle' => trim((string) ($item['handle'] ?? '')),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? '')),
                    'primary_cta' => trim((string) ($item['primary_cta'] ?? '')),
                    'links_back_to_site' => filter_var($item['links_back_to_site'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'verification_notes' => trim((string) ($item['verification_notes'] ?? '')),
                ];

                $hasContent = collect($profile)
                    ->except('links_back_to_site')
                    ->contains(fn (mixed $field): bool => is_string($field) && $field !== '');

                return $hasContent ? $profile : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
