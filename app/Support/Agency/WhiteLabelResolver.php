<?php

namespace App\Support\Agency;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Workspace\Models\Workspace;

/**
 * Resolves the effective white-label branding for a workspace (Phase د).
 * Branding only applies when the plan grants `white_label` AND the agency turned it on;
 * otherwise it falls back to the platform's own identity.
 */
class WhiteLabelResolver
{
    public const DEFAULT_NAME = 'منصة التسويق الاستراتيجي';
    public const DEFAULT_COLOR = '#6366f1';

    public function __construct(private readonly EntitlementResolver $entitlements) {}

    /**
     * @return array{enabled: bool, entitled: bool, name: string, color: string, logo_url: ?string}
     */
    public function for(Workspace $workspace): array
    {
        $entitled = $this->entitlements->boolean('white_label', $workspace);
        $branding = is_array($workspace->branding_json) ? $workspace->branding_json : [];
        $on = $entitled && (bool) ($branding['enabled'] ?? false);

        return [
            'enabled' => $on,
            'entitled' => $entitled,
            'name' => $on ? (string) ($branding['name'] ?? $workspace->name) : self::DEFAULT_NAME,
            'color' => $on ? (string) ($branding['color'] ?? self::DEFAULT_COLOR) : self::DEFAULT_COLOR,
            'logo_url' => $on ? ($branding['logo_url'] ?? null) : null,
        ];
    }
}
