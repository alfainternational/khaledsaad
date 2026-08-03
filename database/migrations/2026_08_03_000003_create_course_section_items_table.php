<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['course_section_id', 'position']);
            $table->unique(['course_section_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_section_items');
    }
};
