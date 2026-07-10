# تطبيق «خالد سعد — نمو» (KS Growth Mobile)

تطبيق Flutter native يطابق تجربة المستخدم على الويب 100% (لوحة الآدمن تبقى ويب).
البنية: Clean Architecture + GetX + Dio + Sanctum Bearer.

## التشغيل محلياً

```bash
flutter pub get

# على محاكي أندرويد (10.0.2.2 = مضيف الجهاز):
flutter run --dart-define=API_BASE_URL=http://10.0.2.2/khaledsaad/public/api/v1

# على الإنتاج:
flutter run --dart-define=API_BASE_URL=https://khaledsaad.net/api/v1
```

بدون `--dart-define` يستخدم التطبيق الإنتاج افتراضاً (انظر `lib/core/config/env.dart`).

## الدفع (PayPal)

- «اشترك» يستدعي `POST /billing/subscribe` → يفتح `approval_url` في المتصفح الخارجي.
- PayPal يعيد المتصفح إلى جسر الويب `/billing/mobile/return` الذي يقفز إلى
  `ksgrowth://billing/return?subscription_id=...` (مخطط الرابط مسجّل في
  AndroidManifest وInfo.plist).
- التطبيق يلتقط الرابط عبر `app_links` ويستدعي `POST /billing/paypal/callback`
  للتحقق والتفعيل. Webhook الخادم يبقى مصدر الحقيقة النهائي.

## الإشعارات (Push)

الخادم جاهز بالكامل (`PushGateway` عبر FCM HTTP v1 + جدول `device_tokens` +
`POST/DELETE /api/v1/devices`)، ويعمل كـ no-op آمن حتى تفعيله. للتفعيل:

1. أنشئ مشروع Firebase وأضف تطبيق أندرويد بالحزمة `net.khaledsaad.ksgrowth_mobile`.
2. نزّل `google-services.json` إلى `android/app/`، و`GoogleService-Info.plist` إلى `ios/Runner/`.
3. أضف الحزمة: `flutter pub add firebase_core firebase_messaging` وهيّئها في `main.dart`،
   ثم أرسل التوكن إلى `BillingRepository.registerDevice()` بعد تسجيل الدخول.
4. في خادم Laravel أضف لملف `.env`:
   `FCM_PROJECT_ID=...` و`FCM_CREDENTIALS_PATH=/path/to/service-account.json`.

الحدث المربوط حالياً: اكتمال تحليل المشروع (audit) → إشعار بمعرّف المشروع للتوجيه العميق.

## النشر للمتاجر

```bash
# أندرويد (App Bundle للنشر في Google Play):
flutter build appbundle --release --dart-define=API_BASE_URL=https://khaledsaad.net/api/v1

# iOS (يتطلب Mac + Xcode + حساب Apple Developer):
flutter build ipa --release --dart-define=API_BASE_URL=https://khaledsaad.net/api/v1
```

قبل النشر: وقّع أندرويد بمفتاح upload keystore (راجع توثيق Flutter deployment)،
وفي iOS اضبط الـ Team والـ Bundle ID في Xcode.

## الاختبارات

```bash
flutter analyze   # يجب: No issues found
flutter test      # اختبارات الوحدة (عقد الأخطاء وغيرها)
```
