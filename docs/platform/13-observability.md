# 13 — القياس والمراقبة

> `public/js/insights.js` موجود بالفعل (`insights-visit`, `insights-view`,
> `insights-endpoint`, `insights-heartbeat`, `insights-idle`).
> البنية قائمة؛ ما ينقص هو **قياس القمع**.

**المبدأ:** عطل «٦٠ سؤالًا ثم فشل» كان يجب أن يصرخ في لوحة قبل أن يُكتشف بالمصادفة.
كل ما في `00-context.md` كان قابلًا للرصد آليًا.

## 1. أحداث القمع

```
project_created
flow_started            { flow, tool, entry_point }
preflight_shown         { outcome, cost, affordable }
preflight_blocked       { reason: insufficient|plan|provider }   ← تنبيه إن ارتفع
question_answered       { index, total, seconds }                ← يكشف نقاط التسرّب
flow_abandoned          { last_index, total, seconds_total }
run_queued / run_started
run_completed           { duration_ms, cost_credits, provider_cost }
run_deferred            { reason: provider }                     ← تنبيه فوري
report_viewed           { scroll_depth, sections_opened }
task_materialized       { count }
task_adopted / task_completed / task_dropped { reason }
pulse_sent / pulse_opened
```

## 2. المؤشرات الحاكمة

| المؤشر | لماذا |
|---|---|
| **بدأ التشخيص ← وصل إلى تقرير** | الحكم الأول على المنتج |
| **متوسط رقم السؤال عند الانقطاع** | يحدّد أين تقصّ الاستبيان بالضبط |
| **أنجز مهمة واحدة خلال ٣٠ يومًا من أول تقرير** | مؤشر الحلقة والاحتفاظ (`05`) |
| **معدل `preflight_blocked`** | يقيس عدد من يصطدمون بجدار |
| **معدل `run_deferred`** | صحة مزوّد الذكاء |
| **تكلفة المزوّد لكل تقرير مقابل سعره بالأرصدة** | الهامش الحقيقي لكل أداة |
| **متوسط الأسئلة لكل أداة عبر الزمن** | يثبت أثر قاعدة الحقائق (`04`) |

## 3. التنبيهات

| الشرط | الشدّة |
|---|---|
| حصة مزوّد الذكاء < ٢٠٪ | **حرج — قبل أن يشعر أي مستخدم** |
| `run_deferred` > ٥٪ خلال ١٥ دقيقة | حرج |
| `preflight_blocked` > ٢٠٪ يوميًا | تحذير (خلل تسعير أو تعبئة) |
| انقطاع في القمع (بدأ ولم يُشغّل) > ٣٠٪ | تحذير |
| ‎p95‎ لزمن التوليد > العتبة | تحذير |

## 4. السجلّات

- سجل منظَّم (JSON) مع `request_id` و`run_id` و`account_id`.
- كل استدعاء مزوّد يُسجَّل في `run_attempts`: النموذج، الرموز، التكلفة، الزمن، الخطأ.
- تتبّع الأخطاء (Sentry أو ما يماثله) مع فصل `kind=ours` عن `kind=theirs`.
- **ممنوع تسجيل محتوى إجابات المستخدم في السجلّات** — راجع `14`.

## 5. لوحة `/admin`

صحة المزوّد والحصة والاتجاه · الـ runs المؤجّلة وأعمارها · القمع اليومي ·
الإنفاق مقابل السقف · هامش كل أداة · طابور المراجعة البشرية مقابل الـ SLA.
