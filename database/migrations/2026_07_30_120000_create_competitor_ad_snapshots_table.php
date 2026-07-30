<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لقطات مكتبات الإعلانات: ما رُصد لكل منافس على كل منصة، ومتى ومن أين.
 *
 * السحب هشّ يتكسّر مع أي تغيير في الصفحة (سياسة المصادر §١٠، الفئة ج). لذلك
 * تُخزَّن كل محاولة بحالتها: `fetched` بإعلاناتها، أو `unavailable`/`broke`
 * بملاحظة تغطية — فانكسار السحب يُعلَن فقدانَ تغطية لا «لا إعلانات لديه»
 * (§٤.٣). و`captured_at` و`source_url` مع كل صف: كل رقم يحمل مصدره وتاريخه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_ad_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_competitor_id')->constrained()->cascadeOnDelete();

            // المنصة (meta/tiktok/google…) وحالة السحب عليها.
            $table->string('platform');
            $table->string('status'); // fetched | unavailable | broke

            // مصدر الرصد وتاريخه: يرافقان كل رقم بلا استثناء.
            $table->string('source_url')->nullable();
            $table->timestamp('captured_at');

            // الإعلانات المرصودة كما وردت — النص الخام دليل يُعاد تصنيفه لاحقًا.
            $table->json('ads')->nullable();

            // لماذا نقصت التغطية، بلغة تُعرض لصاحب النشاط.
            $table->string('coverage_note')->nullable();

            $table->timestamps();

            $table->index(['project_competitor_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_ad_snapshots');
    }
};
