<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_competitors', function (Blueprint $table) {
            // مصدر المنافس: سمّاه المستخدم (يقين محلي) أم اقترحناه (مرشّح يحتاج تأكيدًا).
            $table->string('source')->default('named')->after('name');
            // الطبقة: المحلي يقود التحليل لأنه مصدر أغلب الأثر.
            $table->string('tier')->default('local')->after('source');
            // حالة المرشّح: مؤكد من المستخدم، أو بانتظار تأكيده، أو مستبعَد.
            $table->string('status')->default('confirmed')->after('tier');
            $table->string('note')->nullable()->after('weaknesses');

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('project_competitors', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'name']);
            $table->dropColumn(['source', 'tier', 'status', 'note']);
        });
    }
};
