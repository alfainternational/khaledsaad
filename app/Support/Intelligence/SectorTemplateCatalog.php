<?php

namespace App\Support\Intelligence;

class SectorTemplateCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'general_business' => [
                'label' => 'نشاط عام',
                'weights' => [
                    'website' => 0.16,
                    'social' => 0.10,
                    'seo' => 0.12,
                    'trust' => 0.12,
                    'conversion' => 0.15,
                    'ads_readiness' => 0.10,
                    'ai_visibility' => 0.08,
                    'competition' => 0.08,
                    'lead_readiness' => 0.09,
                ],
            ],
            'ecommerce' => [
                'label' => 'متجر إلكتروني',
                'weights' => [
                    'website' => 0.15,
                    'social' => 0.10,
                    'seo' => 0.11,
                    'trust' => 0.13,
                    'conversion' => 0.19,
                    'ads_readiness' => 0.12,
                    'ai_visibility' => 0.05,
                    'competition' => 0.07,
                    'lead_readiness' => 0.08,
                ],
            ],
            'clinic' => [
                'label' => 'عيادة',
                'weights' => [
                    'website' => 0.14,
                    'social' => 0.10,
                    'seo' => 0.11,
                    'trust' => 0.17,
                    'conversion' => 0.16,
                    'ads_readiness' => 0.08,
                    'ai_visibility' => 0.07,
                    'competition' => 0.07,
                    'lead_readiness' => 0.10,
                ],
            ],
            'restaurant' => [
                'label' => 'مطعم',
                'weights' => [
                    'website' => 0.12,
                    'social' => 0.15,
                    'seo' => 0.08,
                    'trust' => 0.12,
                    'conversion' => 0.17,
                    'ads_readiness' => 0.11,
                    'ai_visibility' => 0.05,
                    'competition' => 0.10,
                    'lead_readiness' => 0.10,
                ],
            ],
            'b2b_services' => [
                'label' => 'خدمات B2B',
                'weights' => [
                    'website' => 0.15,
                    'social' => 0.07,
                    'seo' => 0.13,
                    'trust' => 0.14,
                    'conversion' => 0.16,
                    'ads_readiness' => 0.08,
                    'ai_visibility' => 0.10,
                    'competition' => 0.07,
                    'lead_readiness' => 0.10,
                ],
            ],
            'education' => [
                'label' => 'تعليم',
                'weights' => [
                    'website' => 0.14,
                    'social' => 0.12,
                    'seo' => 0.11,
                    'trust' => 0.14,
                    'conversion' => 0.15,
                    'ads_readiness' => 0.09,
                    'ai_visibility' => 0.08,
                    'competition' => 0.08,
                    'lead_readiness' => 0.09,
                ],
            ],
            'saas' => [
                'label' => 'SaaS',
                'weights' => [
                    'website' => 0.16,
                    'social' => 0.07,
                    'seo' => 0.14,
                    'trust' => 0.11,
                    'conversion' => 0.17,
                    'ads_readiness' => 0.10,
                    'ai_visibility' => 0.09,
                    'competition' => 0.08,
                    'lead_readiness' => 0.08,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function for(?string $sector): array
    {
        return $this->all()[$sector ?: 'general_business'] ?? $this->all()['general_business'];
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $template, string $key): array => [$key => (string) $template['label']])
            ->all();
    }
}
