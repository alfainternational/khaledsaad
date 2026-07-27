<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مشاركة موجز الوكالة برابط آمن بدل إرسال الملف يدويًا.
 *
 * الرابط ملك صاحب المشروع: يُنشأ بطلبه، وينتهي بتاريخ، ويُلغى فورًا متى شاء،
 * وكل فتحة تُسجَّل ليعرف من اطّلع ومتى. لا تُخزَّن عناوين IP كما هي — بصمة
 * مجزّأة تكفي للتمييز دون الاحتفاظ ببيانات تعريف الزائر.
 *
 * إضافة أعمدة وجدول جديد — لا إعادة تهيئة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->string('share_token', 64)->nullable()->unique()->after('pdf_generated_at');
            $table->timestamp('share_created_at')->nullable()->after('share_token');
            $table->timestamp('share_expires_at')->nullable()->after('share_created_at');
            $table->timestamp('share_revoked_at')->nullable()->after('share_expires_at');
        });

        Schema::create('agency_report_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_report_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('web');
            $table->string('viewer_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['agency_report_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_report_views');

        Schema::table('agency_reports', function (Blueprint $table): void {
            $table->dropColumn(['share_token', 'share_created_at', 'share_expires_at', 'share_revoked_at']);
        });
    }
};
