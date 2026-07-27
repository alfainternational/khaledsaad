# deploy/ — ملفات البنية التحتية للإنتاج

هذا المجلد يحتوي على كل ما يحتاجه خادم الإنتاج لتشغيل المنصة بشكل آمن.

## المحتوى

| المسار | الغرض |
|---|---|
| [deploy.sh](deploy.sh) | سكربت نشر تحديثي (pull → build → migrate → cache → queue:restart) |
| [nginx/khaledsaad.conf](nginx/khaledsaad.conf) | قالب Nginx vhost مع SSL ورؤوس أمنية |
| [supervisor/khaledsaad-queue.conf](supervisor/khaledsaad-queue.conf) | إعداد Supervisor لتشغيل Queue Worker |
| [supervisor/khaledsaad-schedule.conf](supervisor/khaledsaad-schedule.conf) | بديل Cron لتشغيل `schedule:work` كخدمة |
| [windows/install-queue-service.ps1](windows/install-queue-service.ps1) | تثبيت Queue Worker كخدمة Windows عبر NSSM |

## الاستخدام

راجع [DEPLOYMENT.md](../DEPLOYMENT.md) للتعليمات التفصيلية.

## ملاحظات

- **لا تعدّل هذه الملفات على الخادم مباشرة.** عدّلها في المستودع ثم `git pull`.
- قبل النشر الأول، استبدل `your-domain.com` بالنطاق الفعلي في `nginx/khaledsaad.conf`.
- `deploy.sh` يفترض وجود Composer و npm في `PATH`.
