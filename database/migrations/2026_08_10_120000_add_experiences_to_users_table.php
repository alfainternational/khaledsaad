<?php

use App\Support\Experience\ExperienceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('initial_experience', 20)->nullable()->after('locale');
            $table->string('active_experience', 20)->nullable()->after('initial_experience');
            $table->timestamp('business_experience_enabled_at')->nullable()->after('active_experience');
            $table->timestamp('learning_experience_enabled_at')->nullable()->after('business_experience_enabled_at');
            $table->timestamp('experience_selected_at')->nullable()->after('learning_experience_enabled_at');
        });

        app(ExperienceBackfill::class)->run();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'initial_experience',
                'active_experience',
                'business_experience_enabled_at',
                'learning_experience_enabled_at',
                'experience_selected_at',
            ]);
        });
    }
};
