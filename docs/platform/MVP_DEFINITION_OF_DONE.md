# تعريف الإنجاز (Definition of Done) — MVP مقابل الكود الحالي

مرجع الوثيقة: [CLAUDE.md](../../CLAUDE.md) الأقسام 35 و 36 و 37.

## سلسلة الوصول (كل طلب)

| المتطلب | التحقق |
|---------|--------|
| Authentication | جلسة Laravel + Sanctum للـ API العام |
| Workspace context | Middleware [ResolveWorkspaceContext](app/Http/Middleware/ResolveWorkspaceContext.php) على مجموعة `web` |
| Membership / Role / Policy | Policies مسجّلة في [AppServiceProvider](app/Providers/AppServiceProvider.php) |
| Entitlement | [EntitlementResolver](app/Domain/Entitlement/Services/EntitlementResolver.php) + مساعد `entitlement()` |
| Feature flag | [FeatureFlagService](app/Domain/FeatureFlag/Services/FeatureFlagService.php) + `@feature` |
| Audit للإجراءات الحساسة | [AuditLogger](app/Domain/Audit/Services/AuditLogger.php) حيث يُستدعى |

## MVP المنتج (طبقة الأدوات)

| المتطلب في CLAUDE.md | الحالة العملية |
|----------------------|----------------|
| 6 أدوات بالوضع السريع كحد أدنى منتجي | البذرة تحتوي **26 أداة**؛ التحقق الوظيفي يتم عبر تشغيل أداة ومسار `RunToolAction` |
| قالب أداة موحّد | واجهات الأدوات عبر [ToolController](app/Http/Controllers/Web/ToolController.php) / تجربة الخبرة |
| `tool_runs` + `workspace_data` | [RunToolAction](app/Application/Tooling/RunToolAction.php) يكتب السجلات والمفاتيح `tools.{code}` و `tool.summary.{code}` |

## MVP السحابي (طبقة المنصة)

| المتطلب | الحالة |
|---------|--------|
| Auth + Workspace شخصي + خطط | مسارات التسجيل، لوحة، فوترة |
| Entitlements + Feature flags | بذور + لوحة إدارة |
| لوحة إدارة v0 | CRUD للمستخدمين، الخطط، الأعلام، إلخ |
| توليد AI عبر Queue | تدفق التوليد في التطبيق (مراجعة Jobs ذات الصلة) |

## Non-Goals (لا تُعد «فجوة MVP»)

مذكورة صراحة في CLAUDE.md: تطبيقات موبايل أصلية، CRM كامل، GraphQL، إلخ.
