<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BYOK (Bring Your Own Key): يسمح للحساب بربط مفتاح مزوّد ذكاء خاص به،
 * فتُحتسب توليداته على مفتاحه بدل رصيد المنصة — لتوفير التكلفة.
 * المفتاح يُخزَّن مشفّراً (cast: encrypted) ولا يُعاد للعميل إطلاقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // المزوّد المتوافق مع OpenAI (openrouter يمنح Claude و ChatGPT بمفتاح واحد).
            $table->string('ai_provider', 40)->nullable()->after('status');
            // المفتاح مشفّراً (text لأن الناتج المشفّر أطول من الخام بكثير).
            $table->text('ai_provider_key')->nullable()->after('ai_provider');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_provider_key']);
        });
    }
};
