<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title');
            $table->foreignId('content_media_id')->nullable()->constrained('content_media')->restrictOnDelete();
            $table->text('url')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['content_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_resources');
    }
};
