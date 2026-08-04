<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->char('access_token_hash', 64)->nullable()->unique();
            $table->dateTime('consented_at');
            $table->dateTime('subscribed_at');
            $table->dateTime('last_access_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_subscribers');
    }
};
