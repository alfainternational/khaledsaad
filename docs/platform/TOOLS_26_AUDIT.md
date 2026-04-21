# تدقيق الأدوات الـ26 — محرك موحّد ومفاتيح البيانات

## المحرك

- **تشغيل:** [RunToolAction](app/Application/Tooling/RunToolAction.php)
- **بناء المخرجات:** [BuildToolPayloadAction](app/Application/Tooling/BuildToolPayloadAction.php)
- **أوضاع التشغيل (ليست quick/advanced في الكود):** `guided`، `structured`، `expert` — انظر [ToolModePolicy](app/Support/Tooling/ToolModePolicy.php) والحقول `has_*_mode` في جدول الأدوات.

## مفاتيح `workspace_data` بعد كل تشغيل ناجح

لكل أداة بكود `{code}`:

| المفتاح | المحتوى تقريباً |
|---------|-----------------|
| `tools.{code}` | ملخص تشغيل، آخر run، درجة الاكتمال |
| `tool.summary.{code}` | كائن `summary` كاملاً |

## جدول الأدوات (26)

| # | code | مرحلة | ملاحظة |
|---|------|--------|--------|
| 1 | diagnosis | 1 | |
| 2 | idea-clarity | 1 | |
| 3 | swot-analysis | 1 | |
| 4 | goal-definition | 1 | |
| 5 | problem-definition | 1 | |
| 6 | tagline-builder | 2 | |
| 7 | ideal-customer | 2 | |
| 8 | positioning | 2 | |
| 9 | market-analysis | 2 | |
| 10 | competitor-analysis | 2 | |
| 11 | offer-builder | 3 | |
| 12 | pricing-strategy | 3 | |
| 13 | value-ladder | 3 | |
| 14 | package-builder | 3 | |
| 15 | promise-builder | 3 | |
| 16 | funnel-builder | 4 | |
| 17 | customer-journey | 4 | |
| 18 | marketing-plan | 4 | |
| 19 | content-plan | 4 | |
| 20 | campaign-builder | 4 | |
| 21 | follow-up-sequence | 4 | |
| 22 | kpi-tracker | 5 | |
| 23 | execution-plan | 5 | |
| 24 | performance-review | 5 | |
| 25 | smart-recommendations | 5 | |
| 26 | growth-priorities | 5 | |

## اختبارات

- اختبارات تكامل للأدوات: راجع `tests/Feature/App/ToolRunApiTest.php` ومسارات المشروع.
- لزيادة التغطية: اختبار معلّم لكل `code` حرج حسب الأولوية المنتجية.
