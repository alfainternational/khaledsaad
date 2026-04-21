# دليل التشغيل: التكاملات الخارجية

## الذكاء الاصطناعي

| المتغير | الغرض |
|---------|--------|
| `AI_PROVIDER` | `gemini` \| `nvidia` \| `fallback` |
| `GOOGLE_API_KEY` | Google Gemini |
| `GEMINI_MODEL` | النموذج الافتراضي |
| `NVIDIA_API_KEY` | NVIDIA NIM |
| `NVIDIA_API_BASE_URL` | افتراضي `integrate.api.nvidia.com` |

## الدفع

| المتغير | الغرض |
|---------|--------|
| `PAYPAL_*` | وضع، معرّفات، أسرار، webhooks — انظر [config/services.php](config/services.php) |

## البريد والتخزين

| المتغير | الغرض |
|---------|--------|
| `MAIL_*` | في الإنتاج: SMTP أو Postmark/Resend/SES |
| `AWS_*`، `FILESYSTEM_DISK` | عند استخدام S3 أو متوافق |

## تكامل HTTP السحابي (اختياري)

| المتغير | الغرض |
|---------|--------|
| `CLOUD_INTEGRATION_ENABLED` | تفعيل الطبقة |
| `CLOUD_BASE_URL` | عنوان الخدمة |
| `CLOUD_API_TOKEN` | Bearer اختياري |
| `CLOUD_*` | مهلات، إعادة محاولة، حد معدّل — [config/cloud.php](config/cloud.php) |

## التشغيل

- **طوابير:** `php artisan queue:work` (ومنها طابور `integrations` لـ [CloudHttpRequestJob](app/Jobs/CloudHttpRequestJob.php)).
- **مراقبة:** قنوات السجلات في `LOG_CHANNEL` و`CLOUD_LOG_CHANNEL`.
