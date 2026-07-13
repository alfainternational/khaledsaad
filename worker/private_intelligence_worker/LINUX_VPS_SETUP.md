# عقل المنصة المملوك — تشغيل العامل الذكي على VPS خاص بالموقع

هذا الدليل يشغّل «الذكاء المحلي الخاص بالمنصة» على خادم تملكه المنصة نفسها —
غير مرتبط بأي جهاز شخصي ولا بأي مزوّد ذكاء خارجي. العامل يسحب المهام من
khaledsaad.net عبر HTTPS موقّعة بـHMAC وينفّذها بنماذج مفتوحة على عتادك.

## 1) اختيار الخادم (القرار الوحيد المطلوب)

| الخيار | العتاد | النموذج المقترح | جودة عربية متوقعة | التكلفة التقريبية |
|---|---|---|---|---|
| اقتصادي | CPU · 8GB RAM | qwen3:4b | ~75 — جيد للمهام القصيرة، بطيء للملفات الطويلة | 10–20$ / شهر |
| متوازن (المرشّح) | CPU · 16–32GB RAM | qwen3:8b | ~80–84 | 25–50$ / شهر |
| احترافي | GPU 16–24GB (A10/4090) | qwen3:14b أو 32b | **88–93** | 100–250$ / شهر أو ~0.3$/ساعة سحابي |

مزوّدون مناسبون: Hetzner وContabo (CPU رخيص)، RunPod وVast.ai (GPU بالساعة)،
DigitalOcean/Linode (متوازن). أوبونتو 22.04 أو 24.04.

## 2) التنصيب (أمر واحد على الـVPS)

```bash
# انسخ مجلد worker/private_intelligence_worker إلى الخادم ثم:
sudo REASONING_MODEL=qwen3:8b bash install_linux_worker.sh
```

يُنصّب: Ollama + النماذج (توليد + تضمينات nomic-embed-text) + Tesseract العربي
+ pdftotext، ويسجّل خدمة `ksgrowth-worker` دائمة تعيد تشغيل نفسها.

## 3) بيانات الاعتماد (على خادم Laravel)

```bash
php artisan private-worker:provision "Site VPS" \
  --capability=deterministic_echo --capability=ocr \
  --capability=document_extract --capability=embeddings \
  --capability=local_llm --json
```

انسخ `id` و`secret` الناتجين إلى `/etc/ksgrowth-worker.env` على الـVPS
(`AI_WORKER_ID` و`AI_WORKER_SECRET`)، ثم:

```bash
sudo systemctl restart ksgrowth-worker
journalctl -u ksgrowth-worker -f   # يجب أن ترى نبضات poll ناجحة
```

## 4) تفعيل المسار في الإنتاج

في `.env` على khaledsaad.net:

```
AI_PRIVATE_WORKER_ENABLED=true
AI_PRIVATE_WORKER_GATEWAY_MODEL=qwen3:1.7b
AI_PRIVATE_WORKER_REASONING_MODEL=qwen3:8b
```

ثم `php artisan config:clear`. من هذه اللحظة:

- **التوليد** (استوديو/مستشار) يُفضَّل عبر عامل المنصة، والسلسلة السحابية تبقى
  احتياط طوارئ فقط (prefer_for_generation).
- **التضمينات** تتحول تلقائياً لهوية العامل (nomic-embed-text) — راجع
  `EmbeddingIdentity`: عامل مفعّل ⇒ هوية العامل؛ عامل معطّل ⇒ هوية API المضمّن.
- لوحة `/admin/ai-control` تُظهر صحة العامل (آخر نبضة، المهام المنجزة).

## 5) قواعد أمان مضمّنة أصلاً

- العامل يتصل خروجاً فقط — الخادم لا يتصل بالعامل أبداً.
- كل مهمة موقّعة HMAC مع توقيت وnonce؛ السر لا يُخزَّن نصاً في السجلات.
- السجلات تحمل معرّفات المهام ورموز الأخطاء فقط — لا برومبتات ولا محتوى.

## استكشاف الأخطاء

| العرض | السبب الأرجح | الحل |
|---|---|---|
| `WORKER_AUTH_FAILED` في السجل | id/secret خطأ أو انتهى | أعد provision وحدّث env |
| المهام تبقى queued | `AI_PRIVATE_WORKER_ENABLED` ليس true على الخادم | فعّله ثم config:clear |
| توليد بطيء جداً | نموذج أكبر من العتاد | انزل درجة نموذج أو ارفع العتاد |
| `Ollama connection refused` | خدمة ollama متوقفة | `systemctl restart ollama` |
