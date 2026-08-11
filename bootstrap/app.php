<?php

use App\Http\Middleware\AuthenticateBrowserSession;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureSupportedAppVersion;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeature::class,
            'experience-access' => \App\Http\Middleware\EnsureExperienceAccess::class,
        ]);

        /*
         * حارس عقد api/v1: يعمل على كل مسارات الواجهة لا على بعضها، لأن
         * تغيير العقد قد يمسّ أي منها. خامل ما دام min_supported_build صفرًا.
         */
        $middleware->api(append: [
            EnsureSupportedAppVersion::class,
            // التطبيق يمرّر لغته في `?lang=` أو `Accept-Language`، فيصل
            // النص المترجَم من نفس ملفات الترجمة التي يقرأها الويب.
            SetLocale::class,
        ]);

        // «سجّل خروجي من الأجهزة الأخرى» (بند ٢٣) يحتاج هذا الحارس ليُبطل
        // الجلسات الأخرى فعليًا عند إعادة توقيع كلمة المرور.
        $middleware->web(append: [
            AuthenticateBrowserSession::class,

            /*
             * تحديد اللغة: داخل مجموعة web لا عامًّا، لأنه يقرأ كوكي
             * التفضيل والمستخدم المسجَّل — وكلاهما غير متاح قبل فكّ
             * تشفير الكوكيّات وبدء الجلسة، وهما داخل هذه المجموعة.
             */
            SetLocale::class,
        ]);

        /*
         * التقاط الزيارة: عام لا داخل مجموعة web.
         *
         * وسائط المجموعة تُطبَّق على المسارات المطابقة وحدها، فالطلب الذي
         * لا يطابق مسارًا (٤٠٤) لا يمرّ بها إطلاقًا. ولمّا كانت الروابط
         * المكسورة التي يصلها زوّار فعليون من أهم ما تكشفه هذه اللوحة —
         * ولا يكشفه شيء آخر — وجب أن يكون الالتقاط عامًّا.
         *
         * الاستبعادات في `config/insights.php` تتكفّل بـ api وwebhooks
         * والملفات الثابتة، وكل الكتابة في `terminate` بعد إرسال
         * الاستجابة فلا تضيف زمنًا إلى انتظار الزائر.
         */
        $middleware->append(TrackVisit::class);

        /*
         * كوكيّا القياس خارج التشفير — وهو شرط عمل الالتقاط العام.
         *
         * وسيط فكّ التشفير يعيش داخل مجموعة web، والالتقاط العام يسبقه في
         * السلسلة. فلو بقيا مشفَّرين لقرأهما الالتقاط نصًّا مبهمًا فيفشل
         * التحقق، فيبدأ كل طلب زيارةً جديدة لزائر جديد — أي انهيار
         * «الجلسة» و«العائد» معًا بلا أن يفشل شيء ظاهر.
         *
         * ولا تُفقد حماية: قيمتهما سلسلة عشوائية لا تعني شيئًا خارج
         * قاعدتنا، ولا تحمل هوية ولا صلاحية ولا تُستعمل في أي قرار وصول.
         */
        $middleware->encryptCookies(except: [
            \App\Modules\Insights\VisitorIdentity::COOKIE,
            \App\Modules\Insights\VisitorIdentity::VISIT_COOKIE,
        ]);

        // إشعارات البوابات تصل من خوادمها بلا جلسة ولا رمز CSRF؛
        // التحقق من صحتها يتم بتوقيع المزوّد داخل المتحكّم.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',

            /*
             * بيكون الإحصاءات: `navigator.sendBeacon` لا يضبط ترويسات،
             * ويُطلق عند إغلاق التبويب حين لا يبقى وقت لطلب يحمل رمزًا.
             *
             * الأمان لا يسقط: المسار لا يُنشئ جلسة ولا يقرأ شيئًا، ويرفض
             * أي معرّف غير موجود في قاعدتنا. والمعرّف نفسه لا يُقرأ إلا
             * من نفس الأصل، فالتزوير عبر المواقع يحتاج تخمين UUID كامل.
             */
            '_insights/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
