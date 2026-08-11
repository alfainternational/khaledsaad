<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_content_update_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('context_hash', 64);
            $table->text('summary');
            $table->longText('proposed_body_html');
            $table->json('changes');
            $table->json('sources');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['content_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_content_update_drafts');
    }
};
