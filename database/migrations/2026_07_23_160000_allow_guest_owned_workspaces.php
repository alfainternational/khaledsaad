<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مساحة عمل بلا صاحب حساب: يبدأ الزائر تجربته فورًا، وحين يسجّل
        // تنتقل المساحة نفسها إليه بما فيها من إجابات ونتائج — لا نسخ ولا فقد.
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('guest_session_id')->nullable()->after('owner_id')
                ->constrained('guest_sessions')->nullOnDelete();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_session_id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
        });
    }
};
