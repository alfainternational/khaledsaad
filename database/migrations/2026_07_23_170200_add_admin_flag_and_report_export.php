<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // صلاحية الإدارة: لوحة الأدوات والبرومبتات والاستهلاك محصورة بها.
            $table->boolean('is_admin')->default(false)->after('email');
        });

        Schema::table('reports', function (Blueprint $table) {
            // مسار آخر PDF مولّد، حتى لا يُعاد توليده عند كل تنزيل.
            $table->string('pdf_path')->nullable()->after('published_at');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_generated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
