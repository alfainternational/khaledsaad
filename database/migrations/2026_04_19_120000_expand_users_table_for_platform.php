<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('locale', 10)->default('ar')->after('remember_token');
            $table->string('status')->default('active')->after('locale');
            $table->boolean('is_super_admin')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn([
                'public_id',
                'locale',
                'status',
                'is_super_admin',
                'last_login_at',
            ]);
        });
    }
};
