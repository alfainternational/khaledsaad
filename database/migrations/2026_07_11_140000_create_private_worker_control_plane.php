<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_workers', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 48)->unique();
            $table->string('name', 120);
            $table->text('secret_ciphertext');
            $table->json('capabilities_json');
            $table->string('status', 24)->default('active');
            $table->string('version', 80)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->char('last_ip_hash', 64)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('intelligence_worker_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intelligence_worker_id')->constrained('intelligence_workers')->cascadeOnDelete();
            $table->string('nonce', 80);
            $table->unsignedBigInteger('request_timestamp');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['intelligence_worker_id', 'nonce'], 'worker_nonces_unique');
            $table->index('expires_at');
        });

        Schema::table('intelligence_jobs', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('public_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intelligence_worker_id')->nullable()->after('project_id')->constrained('intelligence_workers')->nullOnDelete();
            $table->char('lease_token_hash', 64)->nullable()->after('status');
            $table->char('input_hash', 64)->nullable()->after('payload_json');
            $table->char('output_hash', 64)->nullable()->after('result_json');
            $table->string('model_name', 120)->nullable()->after('output_hash');
            $table->string('model_version', 120)->nullable()->after('model_name');
            $table->unsignedSmallInteger('timeout_seconds')->default(300)->after('attempts');
            $table->unsignedTinyInteger('max_attempts')->default(3)->after('timeout_seconds');
            $table->unsignedTinyInteger('progress')->default(0)->after('max_attempts');
            $table->timestamp('lease_started_at')->nullable()->after('available_at');

            $table->index(['intelligence_worker_id', 'status', 'leased_until'], 'intelligence_jobs_worker_lease');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_jobs', function (Blueprint $table) {
            $table->dropIndex('intelligence_jobs_worker_lease');
            $table->dropConstrainedForeignId('account_id');
            $table->dropConstrainedForeignId('intelligence_worker_id');
            $table->dropColumn([
                'lease_token_hash', 'input_hash', 'output_hash', 'model_name', 'model_version',
                'timeout_seconds', 'max_attempts', 'progress', 'lease_started_at',
            ]);
        });

        Schema::dropIfExists('intelligence_worker_nonces');
        Schema::dropIfExists('intelligence_workers');
    }
};
