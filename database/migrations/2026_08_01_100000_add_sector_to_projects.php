<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * القطاع المعلن (مواصفة التخصص القطاعي الثلاثي).
 *
 * nullable عمدًا: المشاريع السابقة للمواصفة تبقى بلا قطاع معلن ويستمر
 * الاستدلال النصي معها — لا نخترع إعلانًا لم يقله صاحبه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('sector', 30)->nullable()->after('industry')->index();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['sector']);
            $table->dropColumn('sector');
        });
    }
};
