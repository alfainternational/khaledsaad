<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->json('value_json')->nullable();
            $table->string('value_hash', 64)->nullable();
            $table->string('event_type')->default('asserted');
            $table->string('source_type');
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('confidence')->default('medium');
            $table->string('period')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['project_id', 'field_key', 'recorded_at'], 'knowledge_project_field_time');
            $table->index(['source_type', 'source_id']);
        });

        DB::table('project_answers')->orderBy('id')->chunkById(200, function ($answers): void {
            foreach ($answers as $answer) {
                $valueJson = is_string($answer->value_json)
                    ? $answer->value_json
                    : json_encode($answer->value_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                DB::table('project_knowledge_sources')->insert([
                    'project_id' => $answer->project_id,
                    'field_key' => $answer->field_key,
                    'value_json' => $valueJson,
                    'value_hash' => hash('sha256', (string) $valueJson),
                    'event_type' => 'asserted',
                    'source_type' => match ($answer->source_tool_key) {
                        null => 'profile',
                        'consultation' => 'consultation',
                        default => 'tool',
                    },
                    'source_key' => $answer->source_tool_key,
                    'source_id' => $answer->source_run_id,
                    'confidence' => 'medium',
                    'recorded_at' => $answer->updated_at ?? $answer->created_at ?? now(),
                    'created_at' => $answer->created_at ?? now(),
                    'updated_at' => $answer->updated_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_knowledge_sources');
    }
};
