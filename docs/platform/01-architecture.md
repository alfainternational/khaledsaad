# 01 — المعمار

## 1. المبدأ

معمار طبقي داخل Laravel (ليس microservices، ولا حاجة إليها بهذا الحجم).
الهدف الوحيد من الطبقات هنا: **منع تكرار الحقيقة**، وهو سبب معظم أعطال التدقيق.

```
app/
├── Domain/                      ← منطق الأعمال. لا يعرف HTTP ولا Blade.
│   ├── Credits/                 ← CreditWallet, CreditEntry, Hold, Preflight
│   ├── Scoring/                 ← ScoreCalculator, ProjectScore, FormulaVersion
│   ├── Facts/                   ← FactStore, Fact, FactKey, Confidence
│   ├── Diagnostics/             ← Tool, Run, RunOrchestrator, Consultation
│   ├── Reports/                 ← Report, Recommendation, Evidence
│   ├── Tasks/                   ← Task, MaterializeTasksFromReport
│   └── Shared/                  ← HasLabel, Money, Percentage, Enums
├── Application/                 ← حالات الاستخدام. تنسّق النطاق، بلا منطق أعمال.
│   └── UseCases/
├── Infrastructure/              ← ما يمكن استبداله دون تغيير النطاق.
│   ├── Ai/                      ← AiProvider (واجهة) + مزوّدون + CircuitBreaker
│   ├── Persistence/
│   └── Notifications/
├── Http/
│   ├── Controllers/Web/         ← يرجع Blade
│   ├── Controllers/Api/V1/      ← يرجع JSON — يستهلكه Flutter والـ PWA
│   ├── Resources/               ← تحويل النطاق إلى JSON (مصدر عقد الـ API)
│   └── ViewModels/              ← تحويل النطاق إلى ما يعرضه Blade
└── Support/
    └── Navigation/              ← سجل الملاحة (INV-6)
```

## 2. قواعد الاتجاه (تُفرض باختبار معماري)

- `Domain` لا يعتمد على شيء خارجه — لا Facades، لا `request()`، لا `auth()`.
- `Http` لا يستدعي `Infrastructure` مباشرة، بل عبر `Application` أو `Domain`.
- `Infrastructure` ينفّذ واجهات معرَّفة في `Domain` (Dependency Inversion).
- Blade و Dart **يعرضان فقط**. أي حساب فيهما خطأ يجب رفضه في المراجعة.

## 3. مصادر الحقيقة — الجدول الحاكم

هذا أهم جدول في الوثيقة كلها. كل عطل من فئة A في `00-context.md` سببه خرق سطر منه.

| الحقيقة | المصدر الوحيد | مسار القراءة الوحيد | ممنوع |
|---|---|---|---|
| رصيد المستخدم | `credit_entries` (ledger) | `CreditWallet::available()` | قراءة عمود، جمع يدوي |
| حصة الخطة | `subscriptions` + `plan_quotas` | `QuotaService::remaining()` | استنتاجها من الرصيد |
| قدرة مزوّد الذكاء | `AiProvider::health()` | `Preflight::gate()` | خلطها برصيد المستخدم |
| درجة المشروع | `project_scores` | `ScoreReader::for($project)` | حسابها في view |
| درجة التقرير | `reports.score` | `$report->score` | إعادة حسابها |
| نص أي قيمة نطاق | ملفات `lang/` عبر `HasLabel` | `<x-value>` / `.label()` | طباعة القيمة الخام |
| بنية الملاحة | `Support\Navigation\NavRegistry` | `Nav::items()` | مصفوفة في Blade |
| توكنز التصميم | `design/tokens.json` | مُولَّدات البناء | لون صريح |

## 4. الأحداث (Events) — تصميم الحلقة

```
RunCompleted        → GenerateReport
ReportGenerated     → MaterializeTasksFromReport   ← يغلق الحلقة (B3)
ReportGenerated     → RecalculateProjectScore
FactChanged         → InvalidateAffectedReports    → NotifyUserIfMaterial
TaskCompleted       → RecalculateProjectScore
                    → PromptMetricUpdate
ProviderDegraded    → PauseRunIntake → QueueRuns → NotifyOps
```

قاعدة: كل مستمع **idempotent** ويعمل عبر الطابور، ومفتاحه `(event_id, listener)`.

## 5. دليل «أين أضع هذا؟»

| السؤال | الجواب |
|---|---|
| قاعدة عمل جديدة | `Domain/<Context>/` مع اختبار وحدة |
| تنسيق عرض | `Http/ViewModels/` أو مكوّن Blade |
| استدعاء مزوّد خارجي | `Infrastructure/Ai/` خلف واجهة في `Domain` |
| حقل جديد يسأل عنه المستخدم | `FactKey` جديد في `Domain/Facts` — **لا سؤال في أداة** |
| شاشة جديدة في الجوال | `07-flutter-app.md` + endpoint في `06-api-contract.md` |
| نص جديد | `lang/{ar,en,fr}/` — ثلاثتها في نفس الـ PR |
| قرار معماري | `docs/adr/NNNN-*.md` |

## 6. ترتيب إعادة الهيكلة (لا تفعلها دفعة واحدة)

المستودع يعمل ويخدم مستخدمين. الاستراتيجية **Strangler Fig**:

1. أنشئ `Domain/<Context>` جديدًا مع اختباراته.
2. اجعل الكود القديم يستدعيه (لا تحذف القديم بعد).
3. أضف الاختبار المعماري الذي يمنع المسار القديم.
4. احذف القديم عندما يصبح الاختبار أخضر بلا استثناءات.

ممنوع فتح PR يعيد هيكلة سياقين في وقت واحد.
