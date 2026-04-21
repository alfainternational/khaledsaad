# CLAUDE.md — دليل مشروع المنصة التسويقية

> هذا الملف هو المرجع الأساسي لأي وكيل ذكي (Claude) أو مطور يعمل على هذه المنصة. يجب قراءته كاملاً قبل إجراء أي تعديل على الكود أو البنية.
>
> **الوثيقة تتكون من ثلاث طبقات:**
> - **الطبقة الأولى (Product Experience & UX Blueprint):** كيف يتحرك المستخدم داخل المنتج.
> - **الطبقة الثانية (SaaS Architecture & Engineering Plan):** كيف يُبنى النظام تقنياً وتجارياً من الداخل.
> - **الطبقة الثالثة (Laravel Implementation Architecture):** كيف يُنفَّذ كل ما سبق داخل Laravel بشكل ثابت ومتين.
>
> الفلسفة الحاكمة: **"Build broad, expose selectively"** — نبني شاملاً من الداخل، ونُتيح مرحلياً من خلال لوحة الآدمن والـ Feature Flags.

---

# الجزء الأول: Product Experience & UX Blueprint

## 1. نظرة عامة على المشروع

**الاسم:** منصة التسويق الاستراتيجي (khaledsaad)
**المسار:** `D:\xampp\htdocs\khaledsaad`
**البيئة:** XAMPP (PHP / MySQL) — تطوير محلي
**اللغة الأساسية للواجهة:** العربية (RTL)
**اللغة الثانوية:** الإنجليزية (اختياري)
**نوع المنتج:** SaaS متعدد الشرائح، متعدد الـ Workspaces، قابل للتوسع من فرد إلى وكالة.

### الوصف
منصة استراتيجية عربية تقود المستخدم من الفكرة إلى التنفيذ خطوة بخطوة، عبر مسارات ومراحل وأدوات موجهة حسب مرحلة المشروع وهدف المستخدم. الهدف تحويل المنصة من "مجموعة أدوات متفرقة" إلى "نظام يقود المستخدم" حسب نضج مشروعه — مع بنية SaaS كاملة تدعم الباقات والصلاحيات والوكالات.

### الشرائح المستهدفة (Segments)
- أصحاب الأفكار (Solo — Idea Stage)
- مقدمو الخدمات (Freelancers / Service Providers)
- أصحاب المشاريع القائمة (SMBs)
- مدراء الشركات والفرق (Teams)
- الوكالات (Agencies — multi-client)
- المستخدم الاحترافي المتقدم (Pro / Advanced)

---



---

## 3. البنية المعمارية للمنتج (UX)

ثلاث طبقات متداخلة:

1. **الطبقة الأولى — المسار:** نوع المستخدم أو حالته (5 مسارات).
2. **الطبقة الثانية — المرحلة:** ما يريد إنجازه الآن (5 مراحل).
3. **الطبقة الثالثة — الأداة:** المهمة الدقيقة داخل المرحلة (20 أداة).

### المسارات الخمسة
- مسار البداية (عندي فكرة)
- مسار مقدم الخدمة
- مسار المشروع القائم
- مسار الشركة أو الفريق
- المسار الاحترافي

### المراحل الخمس
1. **اكتشف مشروعك** — تشخيص، SWOT، وضوح الفكرة.
2. **ابنِ أساسك التسويقي** — الهوية، الجملة التعريفية، العميل المثالي، تحليل السوق، المنافسين.
3. **ابنِ عرضك** — بناء العرض، التسعير، سلم القيمة.
4. **اجذب وحوّل** — القمع، رحلة العميل، الخطة التسويقية، المحتوى، الحملات، المتابعة.
5. **قِس ووسّع** — KPIs، الخطة التنفيذية، التوصيات الذكية.

---

## 4. خريطة الموقع (Site Map)

```
/                             → الرئيسية
/paths                        → المسارات
/tools                        → الأدوات (مجمّعة حسب المرحلة)
/studio                       → الاستوديو الذكي (AI)
/templates                    → القوالب
/reports                      → التقارير
/projects                     → مشاريعي (Workspaces / Clients)
/account                      → الحساب والإعدادات
/admin                        → لوحة الآدمن (Feature controls, Plans, Users)
/agency                       → وضع الوكالة (multi-client)

/onboarding/context           → جمع السياق المرن *(حالياً إعادة توجيه إلى `/onboarding`)*
/onboarding/who-are-you       → شاشة تحديد نوع المستخدم (اختيارية) *(إعادة توجيه)*
/onboarding/your-goal         → شاشة تحديد الهدف (اختيارية) *(إعادة توجيه)*
/onboarding/suggested-path    → اقتراح المسار *(إعادة توجيه)*

/tools/diagnosis ... /tools/recommendations  → 20 أداة
```

---

## 5. القالب الموحد لصفحة الأداة

1. **الرأس:** اسم الأداة، وصف قصير، لمن تناسب، الزمن المتوقع، نوع الناتج، أزرار (حفظ / PDF / AI).
2. **بطاقة التعريف:** لماذا؟ متى؟ ماذا ستحصل؟
3. **التبديل:** Tabs بين "السريع" و"المتقدم".
4. **نموذج الإدخال:** حقول المستوى المختار فقط.
5. **الملخص الذكي:** ملخص + استنتاج + أهم نقطة.
6. **الإجراءات التالية:** استخدام النتيجة، توليد محتوى، إضافة للخطة.
7. **التقدم داخل المسار:** شريط يوضح الموقع الحالي.

### معايير الوضعين
- **السريع:** 3–7 أسئلة، مناسب للمبتدئ.
- **المتقدم:** نماذج تحليلية أوسع.

---

## 6. ربط الأدوات ببعضها (Data Flow داخل Workspace)

- الجملة التعريفية → العرض
- العميل المثالي → المحتوى والحملات
- تحليل المنافسين → التسعير والعرض
- التسعير → العرض والقمع
- القمع → رحلة العميل والمتابعة
- KPIs → الخطة التنفيذية

**قاعدة:** كل مخرجات الأدوات تُحفظ في `workspace_data` بصيغة موحدة قابلة لإعادة الاستدعاء.

---

## 7. الداشبورد — مرشد تنفيذي لا لوحة وصول

1. تحية ذكية + المرحلة الحالية + أفضل خطوة تالية.
2. مؤشرات حالة المشروع.
3. بطاقة المسار الحالي + نسبة الإكمال.
4. بطاقة الخطوة التالية.
5. نتائج جاهزة.
6. توصيات ذكية ديناميكية.
7. اختصارات التنفيذ.
8. آخر النشاطات.

---

## 8. الاستوديو الذكي (AI Studio)

- إعلان سوشيال، تسلسل إيميل، رسائل واتساب، عناوين صفحة هبوط، سكربت عرض، خطة محتوى، نسخة إعلان، متابعة عميل.
- **يقرأ دائماً من مخزن الـ Workspace** ولا يبدأ من الصفر.

---



---

## 10. نبرة المحتوى والهوية التحريرية

- **المرجع:** يجب الالتزام التام بالمعايير المحددة في ملف `BRAND_VOICE.md`.
- **النبرة:** واضحة ومباشرة، استراتيجية، تخاطب بـ"أنت"، تركز على النتيجة، وخالية من الرموز التعبيرية (Emojis).
- **اللغة:** عربية فصحى حديثة واحترافية (Professional MSA).

---

---

# الجزء الثاني: SaaS Architecture & Engineering Plan

## 11. النموذج الذهني الأساسي للـ SaaS

المنتج ليس تطبيقاً فردياً، بل **نظام SaaS بثلاث طبقات معمارية**:

### الطبقات المعمارية
1. **Core Platform (النواة):** Auth, Accounts, Workspaces, Billing, Admin, Feature Flags, Audit Logs.
2. **Modules (الوحدات):** كل مرحلة أو مجموعة أدوات هي Module مستقل، يمكن تفعيله/تعطيله حسب الباقة.
3. **Presentation (الواجهة):** RTL، Responsive، يعتمد على `Entitlements` لتحديد ما يُعرض.

### المبدأ الحاكم للبناء
**Build broad, expose selectively:**
- نبني الميزة كاملة في الكود.
- نغلقها خلف `Feature Flag` + `Plan Entitlement` + `Role Permission`.
- الآدمن يفتحها لمن يستحق، متى يستحق.

---

## 12. Accounts & Workspaces (نموذج البيانات الأساسي)

### التسلسل الهرمي الصريح
```
User  ──(عضوية)──▶  Account  ──(يحتوي)──▶  Workspace  ──(يحتوي)──▶  Project / Client
                       │                         │
                       └─▶ Plan / Subscription   └─▶ Members + Roles
                       └─▶ Billing               └─▶ Entitlements (موروثة من Account + overrides)
                       └─▶ Account-level members └─▶ Operational data (tool_runs, workspace_data)
```

### قواعد الملكية (لإزالة أي لبس)
- **Account يملك الفوترة والاشتراك (Plan/Subscription).** كل Plan مرتبط بـ Account، لا بـ Workspace مباشرة.
- **Workspace يملك البيانات التشغيلية** (مشاريع، نتائج أدوات، أعضاء العمل اليومي).
- **Membership يمكن أن تكون على مستويين:**
  - *Account-level membership:* مثل Billing Admin — يرى الفواتير والاشتراك لكن ليس بالضرورة كل الـ workspaces.
  - *Workspace-level membership:* العضوية التشغيلية اليومية (Owner / Admin / Editor / Viewer / Client).
- **Entitlements تتدفق من Account → Workspace**، مع إمكانية override من لوحة الآدمن على مستوى workspace محدد.

### التمييز الجوهري
- **User:** الهوية الشخصية (بريد + كلمة سر). قد يكون عضواً في عدة Accounts وعدة Workspaces.
- **Account (Billing Owner):** المالك التجاري والمسؤول عن الاشتراك. قد يكون فرداً أو شركة. يحتوي على workspace واحد أو أكثر حسب الباقة.
- **Workspace:** بيئة العمل التشغيلية — فيها بيانات المشاريع والأدوات والمخرجات.
- **Project / Client:** داخل الـ Workspace (مهم جداً لوضع الوكالة).

### أنواع الـ Workspaces
1. **Personal Workspace:** لمستخدم فردي — في الغالب Account بـ Workspace واحد.
2. **Team Workspace:** شركة أو فريق مع أعضاء متعددين.
3. **Agency Workspace:** وكالة تدير عدة عملاء (Clients) داخل نفس الـ Workspace، لكل عميل بياناته المنعزلة.

---

## 12.1 Canonical Terms / المصطلحات المعتمدة

> قاموس مرجعي حاسم. أي مطور أو مصمم يعمل على المشروع يجب أن يستخدم هذه المصطلحات بنفس المعنى حرفياً.

| المصطلح | التعريف المعتمد |
|---|---|
| **User** | الهوية الشخصية (بريد + كلمة سر). لا يملك بيانات تشغيلية بنفسه، بل يعمل دائماً ضمن Account/Workspace. |
| **Account** | الكيان التجاري الذي يملك الاشتراك والفوترة. يحتوي على workspace واحد أو أكثر. |
| **Workspace** | بيئة العمل التشغيلية. تحتوي المشاريع والأدوات والمخرجات والأعضاء. |
| **Project** | مبادرة / ملف عملي داخل Workspace. كل Project يمر بمراحل المنتج الخمس. |
| **Client** | كيان خاص بوضع الوكالة فقط. عميل خارجي للوكالة، له Projects خاصة معزولة داخل Agency Workspace. Client ليس User بالضرورة. |
| **Module** | وحدة أعمال كبرى في النظام (مثلاً: Stage 1 Discovery Module, AI Studio Module, Agency Module). الـ Module هو ما يُفعَّل/يُعطَّل بـ entitlement. |
| **Tool** | أداة تشغيلية محددة داخل Module أو داخل مرحلة من المراحل الخمس (مثل: تشخيص، الجملة التعريفية). الأداة لا تُفعَّل منفردة عادةً، بل تابعة للـ Module. |
| **Tool Run** | تنفيذ واحد لأداة معينة على Project محدد، مع inputs/outputs. |
| **Workspace Data** | المخزن الموحد لمخرجات الأدوات القابلة لإعادة الاستخدام (tagline, ideal_customer, offer...). هو مصدر البيانات الذي يقرأ منه AI Studio. |
| **Entitlement** | صلاحية مفعَّلة (مشتقة من الباقة أو override من الآدمن). مثال: `modules.stage_3 = true`. |
| **Feature Flag** | مفتاح تشغيل/إيقاف لميزة عالمية أو مرحلية. مستقل عن entitlements ولكن قد يتقاطع معها. |
| **Role** | الدور داخل Workspace (Owner / Admin / Editor / Contributor / Viewer / Client). |
| **Plan** | الباقة التجارية (Free / Starter / Pro / Team / Agency / Enterprise). تُترجم إلى entitlements. |

### قواعد الحسم
- **أين تُحفظ outputs؟** → في `workspace_data` (المخرجات القابلة لإعادة الاستخدام) + `tool_runs` (سجل التنفيذ).
- **ما الفرق بين Project و Client؟** → Project مبادرة عمل داخل أي Workspace. Client كيان خاص بـ Agency Workspace فقط، ويحتوي Projects خاصة به.
- **ما الفرق بين Tool و Module؟** → Module هي الحزمة القابلة للتفعيل/التعطيل بـ entitlement. Tool أداة منفردة داخل Module.
- **ما الفرق بين Account و Workspace؟** → Account يملك *الفوترة*. Workspace يملك *البيانات التشغيلية*.

---

## 13. Plans, Billing & Entitlements

### الباقات المقترحة (قابلة للتعديل من الآدمن)
| الباقة | الجمهور | Workspaces | Projects | أعضاء | Modules مفعّلة |
|---|---|---|---|---|---|
| Free | أصحاب الأفكار | 1 | 1 | 1 | المرحلة 1 فقط |
| Starter | مقدم خدمة | 1 | 3 | 1 | المراحل 1–3 |
| Pro | مشروع قائم | 2 | 10 | 3 | كل المراحل + AI محدود |
| Team | شركات/فرق | 3 | 25 | 10 | كل شيء + Roles |
| Agency | وكالات | 5+ | غير محدود | 25+ | كل شيء + Multi-client + Approvals |
| Enterprise | مؤسسات | مخصص | مخصص | مخصص | كل شيء + SSO + SLA |

### Entitlements Matrix
كل باقة تُترجم إلى مجموعة `entitlements` (قائمة صلاحيات):
```
entitlements: {
  modules.stage_1: true,
  modules.stage_2: true,
  modules.stage_3: false,
  ai_studio.monthly_credits: 100,
  workspaces.max: 2,
  projects.max_per_workspace: 10,
  agency_mode: false,
  white_label: false,
  api_access: false,
  ...
}
```

**قاعدة:** أي كود يتحقق من صلاحية يجب أن يسأل `entitlements`، لا أن يسأل اسم الباقة مباشرة.

---

## 14. Roles & Permissions (RBAC)

### الأدوار داخل الـ Workspace
- **Owner:** مالك كل شيء، يدير الفوترة والأعضاء.
- **Admin:** يدير المحتوى والأعضاء، بدون فوترة.
- **Editor:** يحرر الأدوات والمخرجات.
- **Contributor:** يضيف محتوى محدود.
- **Viewer:** قراءة فقط.
- **Client (Agency only):** عميل الوكالة، يرى فقط المشاريع المخصصة له، قد يعلق/يوافق.

### مصفوفة الصلاحيات (Access Matrix — مختصر)
| الإجراء | Owner | Admin | Editor | Contributor | Viewer | Client |
|---|---|---|---|---|---|---|
| إدارة الفوترة | ✓ | — | — | — | — | — |
| دعوة أعضاء | ✓ | ✓ | — | — | — | — |
| إنشاء مشروع | ✓ | ✓ | ✓ | — | — | — |
| تحرير أداة | ✓ | ✓ | ✓ | جزئي | — | — |
| توليد AI | ✓ | ✓ | ✓ | ✓ | — | — |
| عرض التقارير | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ (مقيد) |
| التعليق والموافقة | ✓ | ✓ | ✓ | — | — | ✓ |

---

## 15. Admin Panel & Feature Flags

### ما يتحكم به الآدمن
1. **Feature Flags عالمية:** فتح/إغلاق ميزة لكل المستخدمين أو لشريحة.
2. **Per-Plan Overrides:** تغيير صلاحيات باقة دون لمس الكود.
3. **Per-Workspace Overrides:** منح workspace محدد ميزة استثنائية.
4. **Beta Cohorts:** فتح ميزة تجريبية لمجموعة محددة قبل الإطلاق.
5. **Kill Switches:** إغلاق ميزة فوراً عند الحاجة.
6. **إدارة المستخدمين والفواتير والخطط.**
7. **Audit Logs:** من فعل ماذا ومتى.
8. **Content Moderation:** مراجعة مخرجات AI إذا لزم.

### هيكل Feature Flag
```
feature_flag: {
  key: "ai_studio.new_templates",
  status: "beta",  // off | beta | on
  rollout: {
    percentage: 25,
    plans: ["Pro", "Team", "Agency"],
    workspaces: [123, 456],  // overrides خاصة
  },
  expires_at: "2026-06-01"
}
```

---

## 16. Modules Lifecycle

كل Module يمر بـ 4 مراحل:
1. **Hidden (built, not exposed):** الكود موجود، مغلق خلف flag.
2. **Internal:** مفعّل لفريق التطوير فقط.
3. **Beta:** مفعّل لشريحة/باقات مختارة.
4. **GA (General Availability):** مفتوح حسب الباقة المستحقة.

**قاعدة الإطلاق:** لا يتم إطلاق Module قبل:
- تجاوز اختبارات الـ QA.
- تحديد الباقات المستحقة.
- كتابة وثائق المستخدم.
- مراجعة الآدمن.

---

## 17. Database Schema (عالي المستوى)

### الجداول الأساسية
```
users                 (id, email, password_hash, locale, created_at)
accounts              (id, owner_user_id, billing_email, plan_id, status)
workspaces            (id, account_id, name, type, created_at)
  └─ workspace_type: personal | team | agency

workspace_members     (id, workspace_id, user_id, role, invited_at, status)
projects              (id, workspace_id, name, client_id_nullable, stage)
  └─ client_id: للوكالات فقط

plans                 (id, code, name_ar, name_en, monthly_price, features_json)
subscriptions         (id, account_id, plan_id, status, current_period_end)
entitlements          (id, scope, scope_id, key, value, source)
  └─ scope: plan | workspace | user
  └─ source: plan_default | admin_override | promo

feature_flags         (id, key, status, rollout_json)
feature_flag_audience (flag_id, workspace_id|user_id|plan_id)

tools                 (id, code, stage, order, status)
tool_runs             (id, project_id, tool_code, mode, inputs_json, output_json, created_by)
workspace_data        (id, workspace_id, project_id, key, value_json)
  └─ key: tagline | ideal_customer | offer | pricing ...

ai_studio_generations (id, project_id, template, inputs_json, output, tokens_used, created_by)
ai_credits_ledger     (id, account_id, delta, reason, ref_id, created_at)

clients               (id, workspace_id, name, contact_info, status)  -- للوكالات
approvals             (id, project_id, item_type, item_id, status, reviewer_id, note)
comments              (id, entity_type, entity_id, author_id, body, created_at)

audit_logs            (id, actor_user_id, action, target_type, target_id, meta, created_at)
```

**قاعدة:** كل جدول يحتوي بيانات مستخدم يجب أن يحتوي `workspace_id` (أو يرتبط بها عبر FK) — لضمان عزل البيانات.

---

## 18. Access Matrix (كيف يتم التحقق في كل طلب)

كل Request في الـ Backend يمر بهذه السلسلة:
```
1. Authentication     →  من هو المستخدم؟
2. Workspace Context  →  في أي Workspace يعمل الآن؟
3. Membership Check   →  هل هو عضو في هذا الـ Workspace؟
4. Role Check         →  ما دوره؟ هل يملك الصلاحية؟
5. Entitlement Check  →  هل باقة الـ Workspace تسمح بهذه الميزة؟
6. Feature Flag Check →  هل الميزة مفعّلة له؟
7. Action Execution   →  تنفيذ + تسجيل في audit_logs
```

---

## 19. Agency Mode (طبقة معمارية مستقلة)

> **القاعدة الحاسمة (لإزالة أي تعارض مع الـ Roadmap):**
> - **Agency architecture is built from day one** — الـ schema والـ data model والـ access layers تستوعب الوكالات منذ اللحظة الأولى.
> - **Agency-facing functionality is exposed progressively** — الميزات الظاهرة للمستخدم تُفتح تدريجياً عبر entitlements + feature flags.
> - **Full agency experience is launched in V3** — التجربة المكتملة للوكيل والعميل (portals, approvals, white label) تصل GA في V3.
>
> بمعنى: البناء البنيوي من اليوم الأول، والإطلاق الظاهري لاحقاً — لا تعارض.

### العناصر المعمارية (تُبنى من البداية)
- **Multi-client workspaces:** كل workspace من نوع `agency` يحتوي عدة `clients`، كل client له `projects` خاصة معزولة.
- **جدول `clients`** موجود في الـ schema الأساسي منذ اليوم الأول حتى لو لم يُستخدم.
- **`workspace_type` enum** يتضمن `agency` منذ البداية.
- **Role enum** يتضمن `client` منذ البداية.
- **Entitlement `agency_mode`** موجود في المصفوفة منذ البداية (قيمته false افتراضياً).

### الميزات الظاهرة (تُفتح تدريجياً)
| الميزة | MVP | V2 | V3 |
|---|---|---|---|
| Schema يدعم agency | ✓ (مبني، مغلق) | ✓ | ✓ |
| Multi-client workspaces UI | — | Beta لفريق مختار | GA |
| Reusable templates بين clients | — | — | GA |
| Client-facing outputs (portals) | — | — | GA |
| Approvals workflow | — | داخلي | GA |
| Comments & mentions | — | ✓ (للفرق) | ✓ (للوكالات) |
| White label | — | — | GA (مدفوع) |
| Per-client billing reports | — | — | GA |

### تبسيط البناء
- نفس الـ schema الأساسي (workspace + projects) مع إضافة `clients` و `approvals` منذ البداية.
- الوضع يُفعّل بـ `entitlement: agency_mode = true`، لا بكود منفصل.
- أي مطور يضيف feature جديدة يجب أن يسأل: "هل تعمل لو كان الـ workspace من نوع agency؟" — حتى لو لم يُفعَّل agency mode بعد.

---

## 20. API Contracts (مبدأ عام)

- **REST + JSON** (أو GraphQL لاحقاً إذا لزم).
- **Versioned:** `/api/v1/...`
- **تنفيذ أساسي في الكود:** مصادقة Sanctum عبر `POST /api/v1/tokens` (اختياري: `workspace_public_id` لنطاق التوكن `workspace:{public_id}`) ثم `GET /api/v1/me` و`GET /api/v1/workspaces`، مع `GET /api/v1/ping` للصحة؛ المشاريع والأدوات والاستوديو والإدارة في `routes/api.php`. رؤوس اختيارية: `Idempotency-Key` لـ POST المشاريع وتشغيل الأداة وتوليد الاستوديو.
- **Idempotency keys** للعمليات المالية وتوليد AI.
- **Rate limiting** حسب الباقة.
- **Webhooks** للأحداث المهمة (subscription.created, tool_run.completed, approval.requested).
- **Scoped tokens:** توكن مرتبط بـ workspace محدد، لا بالمستخدم فقط.

### أمثلة endpoints
(المعرفات في المسار هي `public_id` للمساحة والمشروع؛ قيمة `{tcode}` في الكود هي كود الأداة مثل `diagnosis`.)
```
GET    /api/v1/workspaces/{workspace_public_id}/projects
POST   /api/v1/workspaces/{workspace_public_id}/projects
GET    /api/v1/workspaces/{workspace_public_id}/projects/{project_public_id}/tools/{tcode}
POST   /api/v1/workspaces/{workspace_public_id}/projects/{project_public_id}/tools/{tcode}/run
POST   /api/v1/workspaces/{workspace_public_id}/studio/generations
GET    /api/v1/admin/feature-flags
PATCH  /api/v1/admin/feature-flags/{key}
```

---

## 21. Context Capture (بديل Onboarding الخطي الصارم)

### المبدأ
Onboarding الخطي (من أنت؟ ما هدفك؟) يبقى **مساراً سريعاً اختيارياً**، لكن المنصة لا تعتمد عليه حصرياً.

### الطرق البديلة لجمع السياق
1. **Context from first tool:** ما يكتبه المستخدم في أول أداة يُحلَّل ويُستخدم لاستنتاج نوعه وهدفه.
2. **Workspace setup:** اختيار نوع الـ workspace (شخصي/فريق/وكالة) هو جزء من السياق.
3. **Progressive profiling:** أسئلة موزعة عبر الأدوات بدل شاشة واحدة طويلة.
4. **Skip & return:** يسمح للمستخدم بالقفز لأي أداة، مع تحذير ذكي إذا كان ينقصه سياق.

---

## 22. Roadmap (محدّث بطبقتين)

### MVP (الأولوية الأولى)
**طبقة المنتج:**
- الصفحة العامة + Onboarding + Dashboard.
- 6 أدوات بالوضع السريع (تشخيص، جملة تعريفية، عميل مثالي، عرض، تسعير، خطة تسويقية).
- قالب أداة موحد.

**طبقة SaaS:**
- Auth + Personal Workspace + Free/Starter plans.
- Entitlements + Feature Flags أساسيان.
- Admin Panel v0 (users, plans, flags).
- Audit logs أساسية.

### V2
**طبقة المنتج:**
- الوضع المتقدم لكل الأدوات.
- ربط المخرجات بين الأدوات.
- الاستوديو الذكي المدمج.
- مكتبة القوالب.

**طبقة SaaS:**
- Team Workspaces + Roles كاملة.
- Pro/Team plans + Billing integration (Stripe/Moyasar).
- AI credits ledger.
- Public API v1.

### V3
**طبقة المنتج:**
- تقارير متقدمة + Industry templates.

**طبقة SaaS:**
- **Agency Mode — التجربة المكتملة GA:** client portals, approvals, white-label, per-client billing.
  *(البنية المعمارية للوكالات مبنية من اليوم الأول — راجع القسم 19. ما يصدر في V3 هو الواجهة المكتملة، لا البناء من الصفر.)*
- SSO + Enterprise plan.
- Webhooks + Integrations (CRM/Analytics).
- Advanced analytics dashboards للآدمن.

---

## 23. قواعد العمل لأي وكيل يعدّل هذا المشروع

1. **اقرأ هذا الملف كاملاً** (الطبقتين) قبل أي تعديل.
2. **لا تضف ميزة بدون:** feature_flag + entitlement + role check.
3. **لا تضف أداة** خارج المراحل الخمس دون نقاش.
4. **لا تكسر قالب صفحة الأداة الموحد.**
5. **كل استعلام بيانات يجب أن يُفلتر بـ `workspace_id`** — لا تسريب بين Workspaces.
6. **كل إجراء حساس يُسجل في audit_logs.**
7. **اكتب العناوين بصيغة المستخدم** لا بصيغة المطور.
8. **اختبر RTL دائماً.**
9. **احفظ بيانات الأدوات في `workspace_data`** بصيغة موحدة قابلة لإعادة الاستخدام.
10. **لا تستخدم Emojis** في الواجهة إلا بطلب صريح.
11. **عند إضافة Module جديد:** مرّ به بمراحل الـ Lifecycle الأربع (Hidden → Internal → Beta → GA).
12. **عند التحقق من صلاحية:** اسأل `entitlements`، لا اسم الباقة.
13. **وثّق أي تعديل جوهري** في هذا الملف أو في CHANGELOG.

---



---

# الجزء الثالث: Laravel Implementation Architecture

> هذه الطبقة تحوّل كل ما سبق من قرارات منتج ومعمار SaaS إلى قرارات تنفيذية ثابتة داخل Laravel. أي مطور يجب أن يلتزم بهذه القواعد حرفياً، ولا يتخذ قرارات معمارية مستقلة خارجها.

---

## 25. Laravel Stack Decisions (قرارات ثابتة لا تُناقَش)

> هذه القرارات **محسومة**. أي تعديل عليها يتطلب مراجعة معمارية كاملة.

| المجال | القرار |
|---|---|
| **Backend framework** | Laravel (آخر LTS مستقرة) |
| **Database** | MySQL 8+ |
| **Primary architecture** | Modular Monolith (ليس microservices) |
| **Rendering strategy** | Laravel-first (Blade + Livewire عند الحاجة للتفاعلية) |
| **Admin panel** | داخل Laravel (لوحة مخصصة، ليست حزمة خارجية تقيّد المنطق) |
| **Queues** | Laravel Queues (Database driver للـ MVP، Redis لاحقاً) |
| **Access control** | Policies + Roles + Entitlements + Feature Flags |
| **File storage** | Laravel Filesystem (local → S3-compatible لاحقاً) |
| **API style** | REST, versioned (`/api/v1/...`) |
| **Core business entity** | Workspace |
| **Source of reusable outputs** | `workspace_data` + `tool_runs` |
| **Agency architecture** | Built-in from day one, exposed later |
| **Authentication** | Laravel Sanctum (sessions + token API) |
| **Background jobs** | Laravel Jobs (AI generation, exports, emails) |
| **Testing** | PHPUnit + Pest (اختياري) |
| **Frontend assets** | Vite + Tailwind |
| **RTL** | افتراضي، لا يُعامل كإضافة |

**قاعدة:** لا تُستورد حزمة ثقيلة (مثل حزمة Admin panel كاملة) تفرض هيكلها الخاص على المشروع. الـ conventions يجب أن تتبع هذه الوثيقة، لا الحزمة.

---

## 26. Project Structure (تنظيم الكود)

يجب أن يتبع المشروع تقسيماً واضحاً يمنع تضخم Controllers والـ Models. البنية المعتمدة:

```
app/
├── Domain/                       ← الكيانات التجارية والقواعد (لا تعرف عن HTTP)
│   ├── Account/
│   │   ├── Models/Account.php
│   │   ├── Services/
│   │   └── Events/
│   ├── Workspace/
│   │   ├── Models/Workspace.php
│   │   ├── Models/WorkspaceMember.php
│   │   ├── Enums/WorkspaceType.php
│   │   └── Services/
│   ├── Project/
│   ├── Client/                   ← Agency
│   ├── Tool/
│   │   ├── Models/Tool.php
│   │   ├── Models/ToolRun.php
│   │   ├── Registry/
│   │   └── Contracts/
│   ├── WorkspaceData/
│   ├── Billing/
│   │   ├── Models/Plan.php
│   │   ├── Models/Subscription.php
│   │   └── Services/
│   ├── Entitlement/
│   │   ├── Models/Entitlement.php
│   │   └── Services/EntitlementResolver.php
│   ├── FeatureFlag/
│   ├── AI/
│   │   ├── Models/AIGeneration.php
│   │   ├── Models/AICreditsLedger.php
│   │   └── Providers/             ← abstracted AI providers
│   ├── Approval/
│   ├── Audit/
│   └── Export/
│
├── Application/                  ← Use cases (tying domain + infrastructure)
│   ├── Tool/RunToolAction.php
│   ├── AI/GenerateAction.php
│   └── Billing/UpgradePlanAction.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Web/                  ← صفحات المنصة
│   │   ├── Admin/                ← لوحة الآدمن
│   │   └── Api/V1/
│   ├── Requests/                 ← Form Requests للتحقق من الإدخال
│   ├── Middleware/
│   │   ├── ResolveWorkspaceContext.php
│   │   ├── EnsureWorkspaceMembership.php
│   │   └── CheckEntitlement.php
│   └── Resources/                ← API transformers
│
├── Policies/                     ← التحقق النهائي لكل action
├── Actions/                      ← Single-purpose actions (اختياري عند البساطة)
├── Enums/
├── Jobs/
├── Events/
├── Listeners/
└── Providers/
```

### قواعد التقسيم
- **Domain/** لا يعتمد على `Illuminate\Http`. نقي من HTTP — لذلك يمكن إعادة استخداعده في Jobs و Commands و API.
- **Controllers نحيفة:** تستدعي Action أو Service، ولا تحتوي منطق أعمال.
- **Models ليست ذكية زيادة:** العلاقات والـ accessors فقط. المنطق في Services/Actions.
- **Logic داخل Blade ممنوع** إلا لعرض بسيط. أي فرع منطقي مهم → ViewModel أو Livewire.
- **Helpers مبعثرة ممنوعة.** كل helper يجب أن ينتمي لـ Service أو Action واضح.

---

## 27. Core Models & Ownership Rules (علاقات Eloquent)

> هذا القسم يحوّل قاموس المصطلحات (قسم 12.1) إلى علاقات Laravel صريحة.

### شجرة الملكية
```
User ─────────────── عضوية ──────▶ Account ──▶ Workspace ──▶ Project ──▶ ToolRun
                                       │            │             │
                                       │            │             └──▶ Approvals
                                       │            ├──▶ WorkspaceData (reusable)
                                       │            ├──▶ WorkspaceMembers
                                       │            └──▶ Clients (agency only) ──▶ Projects
                                       ├──▶ Subscription ──▶ Plan
                                       ├──▶ AccountMembers (billing-level)
                                       └──▶ AICreditsLedger
```

### العلاقات الأساسية (Eloquent)

```php
// Account
Account::hasMany(Workspace)
Account::hasMany(AccountMember)
Account::hasOne(Subscription)
Account::hasMany(AICreditsLedger)

// Workspace
Workspace::belongsTo(Account)
Workspace::hasMany(WorkspaceMember)
Workspace::hasMany(Project)
Workspace::hasMany(WorkspaceData)
Workspace::hasMany(Client)          // agency-only, لكن متاح دائماً
Workspace::morphMany(Entitlement)   // overrides على مستوى workspace

// Project
Project::belongsTo(Workspace)
Project::belongsTo(Client)->nullable()   // لوضع الوكالة
Project::hasMany(ToolRun)
Project::hasMany(Approval)

// Client (Agency)
Client::belongsTo(Workspace)
Client::hasMany(Project)

// ToolRun
ToolRun::belongsTo(Project)
ToolRun::belongsTo(Tool, 'tool_code', 'code')
ToolRun::belongsTo(User, 'created_by')

// WorkspaceData
WorkspaceData::belongsTo(Workspace)
WorkspaceData::belongsTo(Project)->nullable()

// Entitlement (polymorphic scope)
Entitlement::morphTo('scope')   // scope: Account | Workspace | User

// Subscription / Plan
Subscription::belongsTo(Account)
Subscription::belongsTo(Plan)
Plan::hasMany(Subscription)

// FeatureFlag
FeatureFlag::hasMany(FeatureFlagAudience)
```

### قواعد الملكية الحاسمة
| ما الذي يربط بـ `account_id`؟ | كل ما يخص الفوترة والاشتراك: `subscriptions`, `ai_credits_ledger`, `account_members`. |
| ما الذي يربط بـ `workspace_id`؟ | كل ما يخص العمل التشغيلي: `projects`, `workspace_members`, `workspace_data`, `clients`, `audit_logs` (عادة). |
| متى نستخدم `project_id`؟ | `tool_runs`, `approvals`, `workspace_data` (عند ربطها بمشروع محدد). |
| ما الذي يكون polymorphic؟ | `entitlements` (scope: Account/Workspace/User)، `comments` (entity_type)، `audit_logs` (target_type). |

### قاعدة إلزامية للاستعلامات
كل Model يخص بيانات تشغيلية يجب أن يملك **global scope** أو **Service wrapper** يفرض فلترة `workspace_id` من الـ context الحالي. لا يُسمح بـ `Project::all()` بدون workspace context.

---

## 28. Database Conventions (قواعد قاعدة البيانات)

### قواعد الـ IDs
- **كل كيان داخلي:** `BIGINT UNSIGNED id` auto-increment.
- **كل كيان ظاهر خارجياً (يُشارَك عبر URL أو API):** يملك `public_id` إضافي (ULID أو UUID) — مثل: `Workspace`, `Project`, `ToolRun`, `AIGeneration`, `Client`.
- **الـ APIs العامة والـ URLs لا تكشف `id` المتسلسل أبداً.**

### Soft Deletes
- **تُستخدم في:** `Account`, `Workspace`, `Project`, `Client`, `User`, `WorkspaceData`, `ToolRun` (للاحتفاظ بسجلات التدقيق).
- **لا تُستخدم في:** `AuditLog`, `AICreditsLedger`, `Subscription` (ledger tables — immutable history).

### Timestamps
- `created_at` و `updated_at` إلزامي في كل جدول.
- `deleted_at` عند تفعيل soft delete.

### متى JSON ومتى أعمدة منفصلة؟
القاعدة الذهبية: **"ما يُفلتر عليه أو يدخل التقارير → عمود. ما هو مرن ونادر الاستعلام → JSON."**

| يكون عموداً مستقلاً | يكون داخل JSON |
|---|---|
| مرحلة المشروع (`stage`) | محتوى الإجابات التفصيلية لأداة |
| حالة الاشتراك (`status`) | inputs/outputs لـ ToolRun |
| نوع الـ workspace | إعدادات AI template |
| دور العضو (`role`) | rollout rules لـ feature flag |
| `workspace_id`, `project_id` | metadata إضافية |

**قاعدة الحماية:** لا تضع داخل JSON أي حقل ستحتاج:
- الفلترة عليه في قائمة (`WHERE`)
- الترتيب عليه (`ORDER BY`)
- التجميع عليه (`GROUP BY`)
- عرضه كـ KPI

### Indexing إلزامي من البداية
- `workspace_id` على كل جدول تشغيلي.
- `(workspace_id, project_id)` composite على `tool_runs`, `workspace_data`, `approvals`.
- `account_id` على `subscriptions`, `workspaces`, `ai_credits_ledger`.
- `public_id` unique index على الجداول الظاهرة خارجياً.
- `key` index على `workspace_data` و `entitlements` و `feature_flags`.

### قاعدة `workspace_data` الحرجة
المخزن الموحد للمخرجات. لمنعه من التحول إلى "dump":
- يُفهرَس بـ `(workspace_id, project_id, key)`.
- `key` محدود بـ enum معروف (`tagline`, `ideal_customer`, `offer`, `pricing`, ...) لا نص حر.
- `value_json` يحمل المحتوى المرن فقط.
- إضافة `key` جديد يمر بـ PR review.

### Migrations
- كل تعديل على schema يمر بـ migration — لا تعديل يدوي على قاعدة البيانات.
- Migrations قابلة للـ rollback حيثما أمكن.

---

## 29. Access Control Architecture (تنفيذ Laravel)

تحويل السلسلة السبعية (قسم 18) إلى طبقات Laravel ملموسة:

| الطبقة المعمارية | التنفيذ في Laravel |
|---|---|
| **Authentication** | Laravel Sanctum / Auth middleware |
| **Workspace resolution** | `ResolveWorkspaceContext` middleware يحل الـ workspace من subdomain/route/header ويضعه في container |
| **Membership check** | `EnsureWorkspaceMembership` middleware |
| **Role / Policy check** | Laravel **Policies** (مثلاً `ProjectPolicy::create`, `ToolRunPolicy::run`) |
| **Entitlement check** | `CheckEntitlement` middleware + `EntitlementResolver` service |
| **Feature flag check** | `FeatureFlagService::isEnabled($key, $context)` |
| **Action execution** | Action/Service class → تسجيل `AuditLog` في الـ tail |

### تدفق الطلب (Request Lifecycle)
```
Request
  → auth (Sanctum)
  → ResolveWorkspaceContext (middleware)
  → EnsureWorkspaceMembership (middleware)
  → CheckEntitlement(key) (middleware، عند الحاجة)
  → FormRequest::authorize() ⇒ Policy::method()
  → Controller::action
  → Application\Action (use case)
  → Domain Service
  → AuditLog dispatch (event)
  → Response
```

### القاعدة الذهبية
- **Middleware** = فحص سياقي مبدئي (هل هو عضو؟ هل الباقة تدعم؟).
- **Policy** = التحقق النهائي من صلاحية action محدد على model محدد.
- **Service** = التنفيذ الفعلي مع أي قواعد أعمال إضافية.

**ممنوع:** التحقق من الصلاحية داخل Blade فقط. Blade يستعمل `@can` كدرع ظاهري، لكن Policy تُستدعى أيضاً في الـ backend.

### Roles vs Entitlements vs Flags — التفريق التنفيذي
- **Role:** *ماذا يستطيع هذا العضو أن يفعل داخل workspace؟* (Policy check)
- **Entitlement:** *هل الباقة تسمح بهذه الميزة؟* (EntitlementResolver check)
- **Feature Flag:** *هل الميزة مفتوحة في هذه المرحلة للجمهور الحالي؟* (FeatureFlagService check)

الثلاثة **مستقلة** ويجب أن تُفحص جميعاً عند الحاجة.

---

## 30. Tool Engine Design (محرك الأدوات)

الأداة ليست مجرد Controller+View. يجب أن تُبنى على ثلاث طبقات:

### طبقة 1: Tool Definition Layer
تعريف ثابت لكل أداة في **Tool Registry** (config/classes):

```php
Tool::define([
  'code'         => 'ideal_customer',
  'stage'        => 2,
  'modes'        => ['quick', 'advanced'],
  'entitlement'  => 'modules.stage_2',
  'inputs'       => IdealCustomerInputs::class,
  'outputs'      => ['ideal_customer_profile' => 'workspace_data'],
  'next'         => ['offer', 'content_plan'],
  'runner'       => IdealCustomerRunner::class,
]);
```

- التعريف يعيش في `app/Domain/Tool/Registry/`.
- لا تُضاف أداة بالكود داخل Controller مباشرة — يجب أن تُسجَّل في الـ Registry.

### طبقة 2: Tool Run Layer
كل تنفيذ فعلي يمر بـ **ToolRunner** موحد:

```php
$run = app(ToolRunner::class)->run(
  toolCode: 'ideal_customer',
  project: $project,
  mode: 'quick',
  inputs: $validatedInputs,
  user: $actor,
);
```

- `ToolRunner` يتحقق من entitlement + policy.
- ينشئ سجل في `tool_runs` (inputs_json, output_json, mode, created_by).
- يرسل event `ToolRunCompleted` لتحديث `workspace_data` تلقائياً.

### طبقة 3: Reusable Output Layer
المخرجات القابلة لإعادة الاستخدام تُكتب في `workspace_data` بمفتاح معياري، ليقرأها:
- أدوات أخرى في نفس المشروع.
- AI Studio عند التوليد.
- التقارير.

### قاعدة الإطلاق
- لا يُطلَق tool جديد بدون: تعريف في Registry + Runner class + Policy + Entitlement key + View موحدة (قالب الأداة) + اختبار.

---

## 31. AI Architecture داخل Laravel

AI ليس فكرة جانبية. يُبنى منذ V1 بالقواعد التالية:

### مبادئ ثابتة
1. **التوليد يتم عبر Queue دائماً** — لا استدعاء synchronous من Controller.
2. **Provider abstracted** — يوجد `AIProviderContract`، والتبديل بين Anthropic/OpenAI/Other يتم من config، لا من الكود.
3. **كل توليد يُسجل** في `ai_generations` (template, inputs, output, tokens, cost).
4. **كل توليد يستهلك credits** من `ai_credits_ledger` بحسب الباقة.
5. **المدخلات تأتي من `workspace_data` تلقائياً** — المستخدم لا يعيد إدخال بيانات موجودة.
6. **الناتج يُحفَظ كـ reusable asset** إذا كان قابلاً لإعادة الاستخدام.

### التدفق
```
User triggers "generate"
  → Controller dispatches GenerateAIJob
  → Job loads template + pulls workspace_data
  → AIProviderContract::generate($prompt)
  → On success: save to ai_generations + decrement ai_credits_ledger
  → Optional: write to workspace_data
  → Event AIGenerationCompleted → notify user
  → On failure: retry (max 3) + log + return credits
```

### القرارات الحاسمة
- **AI templates** تُدار من لوحة الآدمن (جدول `ai_templates`)، لا hardcoded.
- **Rate limiting:** حسب الباقة، عبر Laravel RateLimiter.
- **Error handling:** Job `tries = 3`, backoff exponential، مع سجل أخطاء في `ai_generations.error`.
- **Cost control:** fail-fast إذا تجاوز `ai_credits_ledger.balance` الحد.
- **Moderation hook:** قبل الإرجاع، يمر الناتج بـ moderation policy إذا فُعِّلت.

---

## 32. Admin Control Architecture

القاعدة الحاكمة:
> **أي شيء يمكن فتحه أو إغلاقه لاحقاً لا يجوز hardcode ظهوره في الواجهة. يُقرأ دائماً من: `entitlements`, `feature_flags`, أو `module_registry`.**

### ما يتحكم به الآدمن عبر اللوحة
1. **Plans:** CRUD للباقات + الأسعار + الـ entitlements الافتراضية.
2. **Feature Flags:** تفعيل/تعطيل + rollout percentage + plans audience + workspaces override.
3. **Entitlements per workspace:** override محدد دون تغيير الباقة.
4. **Beta cohorts:** إضافة workspaces لمجموعة تجريبية.
5. **Modules visibility:** إغلاق Module كاملاً بـ kill switch.
6. **AI templates:** إدارة القوالب + تكلفة كل قالب.
7. **Users & Accounts:** بحث، تجميد، إعادة تعيين كلمة سر، عرض الاستخدام.
8. **Audit logs:** فلترة وتصدير.
9. **Release statuses:** تغيير حالة Module (Hidden → Internal → Beta → GA).

### قاعدة الواجهة
في أي Blade/Livewire:
```php
@if(feature('ai_studio.new_templates') && entitlement('ai_studio.monthly_credits') > 0)
  {{-- render --}}
@endif
```
**ممنوع:** `@if(auth()->user()->plan === 'Pro')` — هذا hardcode يكسر الفلسفة.

---

## 33. API Design Rules

- **Versioning:** `/api/v1/...` من اليوم الأول، حتى لو V1 فقط.
- **Resource responses:** Laravel API Resources (`WorkspaceResource`, `ProjectResource`).
- **Error format موحد:**
  ```json
  { "error": { "code": "ENTITLEMENT_DENIED", "message": "...", "details": {...} } }
  ```
- **Pagination:** cursor-based للقوائم الطويلة، page-based للقصيرة.
- **Idempotency keys:** إلزامية على `POST` للعمليات المالية و AI generation.
- **Rate limiting:** حسب الباقة، عبر Laravel RateLimiter مع middleware مخصص.
- **Scoped tokens (Sanctum abilities):** token مرتبط بـ workspace محدد، مع قائمة abilities.
- **Webhooks:** events تُرسَل بـ HMAC signature، مع retry policy.

---

## 34. Queues, Jobs, Events Strategy

### Queue names
- `default` — مهام عامة.
- `ai` — AI generations (priority).
- `exports` — PDF/Excel exports.
- `notifications` — emails, webhooks.
- `audit` — audit log ingestion (عند الحاجة).

### Jobs ذات أولوية
| Job | Queue | Retries |
|---|---|---|
| `GenerateAIJob` | ai | 3 |
| `ExportProjectPdfJob` | exports | 2 |
| `SendWebhookJob` | notifications | 5 (exponential) |
| `ProcessSubscriptionEventJob` | default | 3 |

### Events الرئيسية
- `WorkspaceCreated`, `MemberInvited`
- `ToolRunCompleted` → يحدّث `workspace_data`
- `SubscriptionChanged` → يعيد حساب entitlements
- `AIGenerationCompleted`, `AIGenerationFailed`
- `ApprovalRequested`, `ApprovalGranted`
- `AuditEvent` (generic)

### قاعدة
كل event مهم يُسجَّل في `audit_logs` عبر Listener موحد (`RecordAuditListener`).

---

## 35. Definition of Done (متى تُعتبر V1 مكتملة؟)

### MVP لا يُعتبر مكتملاً إلا إذا:

**الأساسات (Core):**
- يمكن إنشاء `User` + `Account` + `Workspace` بشكل متسلسل.
- يمكن ربط Workspace بـ Account، وتحديد `workspace_type` (personal/team/agency).
- يمكن للمستخدم التبديل بين Workspaces متعددة.
- يمكن تطبيق Plan Entitlements على Account، وتدفقها إلى Workspace.

**الصلاحيات:**
- يمكن للآدمن إخفاء/إظهار Module عبر Feature Flag بدون نشر كود.
- كل طلب يمر بالسلسلة السبعية (auth → context → membership → role → entitlement → flag → action).
- كل إجراء حساس مسجل في `audit_logs`.
- يمكن منع الوصول غير المصرح (اختبار اختراق أساسي).

**الأدوات:**
- يمكن تشغيل أول 6 أدوات (تشخيص، جملة تعريفية، عميل مثالي، عرض، تسعير، خطة تسويقية) في الوضع السريع.
- كل Tool Run مسجل في `tool_runs`.
- المخرجات القابلة لإعادة الاستخدام محفوظة في `workspace_data`.
- يمكن لأداة قراءة مخرجات أداة أخرى في نفس المشروع.

**AI:**
- يمكن تشغيل AI generation عبر Queue (ليس synchronous).
- يمكن تتبع استهلاك credits.
- يمكن retry عند فشل مع حفظ الخطأ.

**Agency readiness:**
- Schema يستوعب agency workspace + clients + approvals، حتى لو الواجهة مغلقة.
- إضافة feature جديدة تمر باختبار: "هل تعمل لو workspace.type = agency؟"

**Admin:**
- لوحة آدمن v0: users, plans, feature flags, entitlement overrides, audit logs.

### ممنوع إطلاق V1 قبل تحقق كل ما سبق.

---

## 36. Non-Goals (ما لن نبنيه في V1 — صراحةً)

لمنع التضخم والتشتت:

### في V1 **لن نبني**:
- Native mobile apps (iOS/Android).
- CRM كامل (بما فيه pipelines, deal stages).
- Task manager كامل.
- White-label كامل.
- Client portal المكتمل.
- Analytics متقدمة للمستخدم النهائي.
- Real-time collaboration (live cursors, presence).
- تكاملات CRM/Analytics خارجية متعددة.
- SSO/SAML.
- Multi-currency billing متقدم.
- نظام إحالة/عمولات.
- GraphQL API.

### في V1 **سنبني من اليوم الأول** (رغم أنها قد تبدو ترفاً):
- Account / Workspace / Project / Client hierarchy كاملة.
- Plans / Entitlements / Feature Flags.
- Workspace isolation صارم.
- Tool engine + reusable outputs layer.
- Agency-ready schema (مغلقة ظاهرياً).
- Roles + Policies.
- Queue-based AI.
- Admin-driven exposure.
- Audit logging.
- Versioned API mindset (`/api/v1/`).

**الفلسفة:** بنية مكتملة + واجهة انتقائية = نسخة أولى قوية، لا منتج متضخم.

---

## 37. قواعد العمل لأي مطور Laravel على المشروع

1. **اقرأ الطبقات الثلاث كاملة وملف `BRAND_VOICE.md`** قبل فتح IDE.
2. **لا تكتب منطق أعمال داخل Controller أو Model.** المنطق في Services/Actions داخل `Domain/` أو `Application/`.
3. **كل نص في الواجهة يجب أن يتبع "دستور الهوية التحريرية"** في `BRAND_VOICE.md`.
4. **كل استعلام Eloquent يجب أن يُفلتَر بـ `workspace_id`** — استخدم global scope أو service wrapper.
4. **لا تضف ميزة بدون:** Feature Flag + Entitlement key + Policy + Migration + Test.
5. **لا تضف ستايلات داخلية (Inline CSS) نهائياً.** يتم استخدام `app.css` أو فئات Tailwind المخصصة.
6. **لا تضع منطقاً حساساً في Blade.** `@can` و `@feature` فقط كدرع ظاهري.
7. **لا تستورد حزمة Admin panel ثقيلة** تفرض هيكلها.
8. **كل AI call يمر بـ Queue.**
9. **كل عنصر ظاهر خارجياً يملك `public_id`.**
10. **يُمنع استخدام بيانات وهمية (Dummy Data) في ملفات الـ Views.** يجب أن تأتي كافة البيانات من الـ Controller.
11. **JSON فقط للمرن — لا للحقول القابلة للفلترة.**
12. **عند إضافة Module جديد:** مرّ بالـ lifecycle الأربع (Hidden → Internal → Beta → GA).
13. **عند التحقق من صلاحية:** اسأل `entitlements`، لا اسم الباقة.
14. **اختبر على workspace من نوع `agency`** حتى لو الميزة غير مفعّلة للوكالات.
15. **كل PR يحدّث الوثيقة** إذا عدّل قراراً معمارياً.
16. **تعدد الأوضاع:** كل ميزة جديدة يجب أن تُصمم لتعمل في الوضعين (Dark/Light) بشكل مريح للعين.
17. **العناوين الضخمة:** يجب أن تلتزم بمقياس `clamp` المحدد في `app.css` لمنع التداخل وتراكب الخطوط.

---

## 39. دستور واجهة المستخدم (UI/UX Constitution)
1. **المركزية:** يُمنع إضافة ستايلات داخلية (Inline CSS) نهائياً. يتم استخدام `app.css` أو فئات Tailwind المخصصة.
2. **التجاوب (Responsiveness):** يجب اختبار الواجهة على 3 مستويات (Desktop, Tablet, Mobile) قبل الاعتماد.
3. **البيانات:** يُمنع استخدام بيانات وهمية (Dummy Data) في ملفات الـ Views. يجب أن تأتي كافة البيانات من الـ Controller.
4. **تعدد الأوضاع:** كل ميزة جديدة يجب أن تُصمم لتعمل في الوضعين (Dark/Light) بشكل مريح للعين.
5. **الخطوط:** الخط المعتمد هو 'Alexandria' بأوزان تتراوح بين 300 و 900.
6. **النصوص:** العناوين الضخمة يجب أن تلتزم بمقياس `clamp` المحدد في `app.css` لمنع التداخل وتراكب الخطوط.

---

## 38. الخلاصة

> **الهدف النهائي (منتج):** أن يشعر المستخدم بأنه يعرف أين يبدأ، ولماذا، وما الناتج، وما الخطوة التالية — بدون تعقيد.
>
> **الهدف النهائي (SaaS):** نظام مبني شاملاً من الداخل، يُفتح مرحلياً عبر الآدمن، يخدم من الفرد حتى الوكالة، بعزل بيانات تام وتحكم كامل في الميزات.
>
> **الهدف النهائي (Laravel):** تطبيق Modular Monolith نظيف، يفصل الـ Domain عن الـ HTTP، يفرض السلسلة السبعية على كل طلب، ويبني كل شيء كـ pluggable modules قابلة للفتح والإغلاق من الآدمن.

الفرق في ثلاثة أسطر:
- **الطبقة الأولى تجيب:** كيف يتحرك المستخدم داخل المنتج؟
- **الطبقة الثانية تجيب:** كيف يُبنى الـ SaaS نفسه ليخدم كل الشرائح ويُفتح تدريجياً؟
- **الطبقة الثالثة تجيب:** كيف نُنفّذ كل ذلك داخل Laravel بقرارات ثابتة ومتينة؟
