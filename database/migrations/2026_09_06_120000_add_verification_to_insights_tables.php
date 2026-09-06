<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * التحقّق من الزيارة: الفرق بين «طلبٌ وصل» و«متصفّحٌ فتح الصفحة».
 *
 * سلسلة الوكيل وحدها لا تكفي للتمييز. الماسح الذي ينتحل Chrome عاديًّا
 * يمرّ من `is_bot` كأنه إنسان، فيدخل أرقام السوق ويُفسدها كلها: زائر
 * فريد، وارتداد، ومتوسط بقاء، ونسبة تحويل. وهذا ما وقع فعلًا — أرقام
 * بآلاف الزوّار بينما عدد من نفّذ جافاسكربت منهم عشرات.
 *
 * الإشارة الحاسمة موجودة أصلًا ولم تكن تُستعمل: البيكون يُرسل عند
 * `pagehide` **دائمًا**، حتى بصفر ثانية. فمن وصلت منه نبضة واحدة فتح
 * الصفحة بمتصفّح حقيقي، ومن لم تصل منه شيء لم ينفّذ سطرًا واحدًا.
 *
 * ولأن §٤.٣ تمنع إخفاء الفجوة، لا تُحذف الجلسات غير المتحقَّقة ولا
 * تُعلَّم بوتات: تُعزل في خانتها وتُعرض في اللوحة بعددها.
 *
 * الحقل مكرَّر على الجداول الثلاثة عمدًا: القراءة تصفية مسطّحة على كل
 * منها، ووصلها بالجلسة في كل استعلام يحوّل كل رقم في اللوحة إلى JOIN.
 * والتكرار آمن لأن التحقّق يقع مرة واحدة لكل جلسة ولا يُنقض بعدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false)->after('is_staff');
            $table->string('verified_by', 20)->nullable()->after('is_verified');
            $table->index(['started_at', 'is_verified'], 'vs_verified_idx');
        });

        Schema::table('visitor_page_views', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false)->after('is_staff');
            $table->index(['viewed_at', 'is_verified'], 'vpv_verified_idx');
        });

        Schema::table('visitor_events', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false)->after('is_staff');
            $table->index(['occurred_at', 'is_verified'], 've_verified_idx');
        });

        /*
         * الأثر الرجعي: ما وصلت منه نبضة أو حدث في الماضي متحقَّق.
         *
         * `active_seconds > 0` أو `events_count > 0` هما الأثر الوحيد
         * الباقي على أن جافاسكربت نُفّذ في تلك الجلسة. الجلسات التي
         * أرسلت نبضة بصفر ثانية ولم تُسجَّل لها أحداث لا أثر لها في
         * الصفوف القديمة، فتبقى «غير متحقَّقة» — نقص في التغطية يُعلَن
         * ولا يُملأ بتخمين (§٤.٣).
         */
        DB::table('visitor_sessions')
            ->where(fn ($q) => $q->where('active_seconds', '>', 0)->orWhere('events_count', '>', 0))
            ->update(['is_verified' => true, 'verified_by' => 'backfill']);

        foreach (['visitor_page_views', 'visitor_events'] as $table) {
            DB::table($table)
                ->whereIn('session_id', fn ($query) => $query
                    ->select('id')
                    ->from('visitor_sessions')
                    ->where('is_verified', true))
                ->update(['is_verified' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('visitor_events', function (Blueprint $table): void {
            $table->dropIndex('ve_verified_idx');
            $table->dropColumn('is_verified');
        });

        Schema::table('visitor_page_views', function (Blueprint $table): void {
            $table->dropIndex('vpv_verified_idx');
            $table->dropColumn('is_verified');
        });

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->dropIndex('vs_verified_idx');
            $table->dropColumn(['is_verified', 'verified_by']);
        });
    }
};
