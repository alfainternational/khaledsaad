<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->string('source_key')->nullable()->unique()->after('slug');
            $table->string('source_filename')->nullable()->after('source_key');
            $table->char('source_text_hash', 64)->nullable()->after('source_filename');
            $table->unsignedSmallInteger('learning_order')->nullable()->index()->after('source_text_hash');
            $table->json('learning_meta')->nullable()->after('learning_order');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->dropUnique(['source_key']);
            $table->dropIndex(['learning_order']);
            $table->dropColumn([
                'source_key',
                'source_filename',
                'source_text_hash',
                'learning_order',
                'learning_meta',
            ]);
        });
    }
};
