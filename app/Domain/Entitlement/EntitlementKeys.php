<?php

namespace App\Domain\Entitlement;

/**
 * Canonical entitlement keys — single source of truth for gating.
 *
 * Business rule (Phase 0 of the rebuild plan):
 *   التسجيل يفتح القراءة، والاشتراك يفتح أخذ القيمة خارج المنصة (نسخ/طباعة/تصدير).
 *
 * Enforcement lives in the CheckEntitlement middleware + per-plan seeded entitlements
 * (see PlatformBootstrapSeeder). Never hardcode plan names to check access — ask the
 * entitlement key, per CLAUDE.md §29.
 */
final class EntitlementKeys
{
    /** يفتح تصدير/نسخ/طباعة المخرجات خارج المنصة (مشترك فقط). */
    public const OUTPUTS_CAN_EXPORT = 'outputs.can_export';

    /** يفتح وحدة الاستوديو الذكي (توليد المحتوى عبر AI). */
    public const MODULES_AI_STUDIO = 'modules.ai_studio';

    private function __construct()
    {
    }
}
