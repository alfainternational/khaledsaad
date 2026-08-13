<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * جداول إحصاءات الزوّار — أربع طبقات لا واحدة.
 *
 * جدول واحد لا يكفي: «كم بقي في الموقع» سؤال جلسة، و«كم بقي في هذه
 * الصفحة» سؤال مشاهدة، و«ماذا فعل» سؤال حدث. دمجها في جدول يجعل كل
 * سؤال منها استعلامًا ملتويًا، وفصلها يجعل كلًّا منها فهرسًا مباشرًا.
 *
 * الطبقة الرابعة تجميع يومي: لوحة تقرأ ثلاثة ملايين صف خام لتعرض تسعة
 * أرقام تتوقف عن الفتح بعد شهرين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
             * هوية الزائر: بصمة مُجزَّأة تعيش في كوكي طرف أول لسنة.
             * ليست هوية شخص — لا اسم ولا بريد ولا IP خام. وظيفتها الوحيدة
             * وصل زيارات اليوم بزيارات الأسبوع الماضي لنعرف «عاد أم جديد».
             */
            $table->string('visitor_id', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * `dateTime` لا `timestamp` في كل أعمدة الوقت هنا.
             *
             * MySQL يمنح أول عمود TIMESTAMP في الجدول قيمة افتراضية ضمنية
             * ويترك التالي بلا واحدة، فيرفض إنشاء الجدول تحت الوضع الصارم.
             * والأهم أن TIMESTAMP يُخزَّن بتوقيت UTC ويُحوَّل عند القراءة،
             * فتنزلق أرقام «زيارات اليوم» عبر مناطق زمنية مختلفة.
             */
            $table->dateTime('started_at')->index();
            $table->dateTime('last_activity_at')->index();
            $table->dateTime('ended_at')->nullable();

            /*
             * الزمن النشط بالثواني — مجموع نبضات المتصفح، لا فارق أول طلب
             * عن آخره. الفارق يعدّ التبويب المنسيّ قراءةً، وهذا لا يعدّه.
             */
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('page_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);

            $table->string('entry_path', 191);
            $table->string('exit_path', 191)->nullable();

            // من أين جاء: القناة محسوبة، وما تحتها هو الدليل الخام لها.
            $table->string('channel', 20)->index();
            $table->string('platform', 40)->nullable()->index();
            $table->string('source', 120)->nullable();
            $table->string('medium', 120)->nullable();
            $table->string('campaign', 120)->nullable()->index();
            $table->string('term', 120)->nullable();
            $table->string('content', 120)->nullable();
            $table->string('referrer_host', 191)->nullable()->index();
            $table->text('referrer_url')->nullable();
            $table->string('landing_query', 500)->nullable();

            // بأي شيء تصفّح: كلها مستخرجة من ترويسة الطلب نفسها.
            $table->string('device_type', 12)->index();
            $table->string('browser', 40)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('os_version', 20)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->unsignedSmallInteger('viewport_width')->nullable();
            $table->unsignedSmallInteger('viewport_height')->nullable();

            /*
             * الموقع: `inferred` دائمًا وبلا استثناء (§٤.١).
             *
             * لا قاعدة GeoIP خارجية هنا بقرار «كله داخلي»، فالبلد مُستنتَج
             * من المنطقة الزمنية التي يعلنها المتصفح ومن لغته. يُصيب غالبًا
             * ويخطئ مع VPN — ولذلك يحمل `location_evidence` ويُعرض موسومًا
             * بفرضية، ولا يُبنى عليه قرار وحده.
             */
            $table->string('country', 2)->nullable()->index();
            $table->string('location_basis', 12)->nullable();
            $table->string('location_evidence', 12)->default('inferred');
            $table->string('timezone', 60)->nullable();
            $table->string('language', 20)->nullable()->index();

            $table->boolean('is_returning')->default(false);
            $table->boolean('is_bounce')->default(true);

            // الآلات تُعلَّم ولا تُحذف: زحف نماذج الذكاء مصدر تشخيص لا ضوضاء.
            $table->boolean('is_bot')->default(false)->index();
            $table->string('bot_name', 40)->nullable();
            $table->string('bot_owner', 40)->nullable();

            // زيارة صاحب المنصة ليست سوقًا: تُسجَّل وتُستبعد عند القراءة.
            $table->boolean('is_staff')->default(false)->index();

            $table->string('conversion_name', 60)->nullable();
            $table->dateTime('converted_at')->nullable();

            // IP مُجزَّأ بمفتاح التطبيق: يكفي للتعرّف الاحتياطي ولا يُعكس.
            $table->string('ip_hash', 64)->nullable()->index();

            $table->timestamps();

            $table->index(['started_at', 'is_bot', 'is_staff'], 'vs_reporting_idx');
        });

        Schema::create('visitor_page_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('session_id')->constrained('visitor_sessions')->cascadeOnDelete();
            $table->string('visitor_id', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('path', 191)->index();
            $table->text('url');
            $table->string('route_name', 120)->nullable()->index();
            $table->string('title', 191)->nullable();
            $table->string('query_string', 500)->nullable();
            $table->text('referrer')->nullable();

            $table->unsignedSmallInteger('status_code')->default(200);
            $table->unsignedInteger('response_ms')->nullable();

            $table->unsignedSmallInteger('sequence')->default(1);
            $table->boolean('is_entry')->default(false);
            $table->boolean('is_exit')->default(false);

            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedTinyInteger('scroll_percent')->default(0);
            $table->unsignedSmallInteger('interactions')->default(0);

            $table->boolean('is_bot')->default(false)->index();
            $table->boolean('is_staff')->default(false)->index();

            $table->dateTime('viewed_at')->index();
            $table->dateTime('left_at')->nullable();

            $table->index(['path', 'viewed_at'], 'vpv_path_time_idx');
        });

        Schema::create('visitor_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('visitor_sessions')->cascadeOnDelete();
            $table->foreignId('page_view_id')->nullable()->constrained('visitor_page_views')->cascadeOnDelete();
            $table->string('visitor_id', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 60)->index();
            $table->string('category', 30)->default('interaction')->index();
            $table->string('label', 191)->nullable();
            $table->string('path', 191);
            $table->decimal('value', 12, 4)->nullable();
            $table->json('meta')->nullable();

            $table->boolean('is_staff')->default(false)->index();
            $table->dateTime('occurred_at')->index();
        });

        /*
         * التجميع اليومي: صف لكل (يوم × بُعد × قيمة).
         *
         * يُبنى بأمر مجدول من الصفوف الخام، فيبقى قابلًا لإعادة الحساب
         * كليًّا. لا يُكتب إليه من مسار الطلب أبدًا — رقمٌ لا يمكن إعادة
         * إنتاجه من مصدره رقمٌ لا يمكن تصديقه.
         */
        Schema::create('visitor_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('stat_date')->index();
            $table->string('dimension', 24);
            $table->string('value', 191);

            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedBigInteger('active_seconds')->default(0);

            $table->timestamps();

            $table->unique(['stat_date', 'dimension', 'value'], 'vds_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_daily_stats');
        Schema::dropIfExists('visitor_events');
        Schema::dropIfExists('visitor_page_views');
        Schema::dropIfExists('visitor_sessions');
    }
};
