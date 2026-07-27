<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الميزات ككيانات حقيقية بدل سطور نصّية في الخطة.
 *
 * features: فهرس الميزات. كل ميزة لها مفتاح ثابت يقابل نقطة تطبيق في الكود
 * (enforcement=gate) أو تكون عرضية للتسويق فقط (enforcement=display) بلا ادّعاء
 * أنها مُطبَّقة. النوع يحدد شكلها: تشغيل/إيقاف، حد أقصى، أو حصة شهرية.
 *
 * plan_features: ما تختاره الخطة من الفهرس + العدد. هذا هو مصدر الحقيقة
 * للاستحقاق، والآدمن يحرّره من اللوحة دون لمس الكود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('group')->default('general'); // core|reports|growth|support
            $table->string('type')->default('boolean');  // boolean|limit|quota
            $table->string('unit')->nullable();          // مشروع | تشغيل/شهر | متابعة
            // gate = يمنع فعليًا عند التجاوز | display = عرض تسويقي فقط.
            $table->string('enforcement')->default('gate');
            // ما يسري على خطة لم يُحدَّد لها هذا العنصر صراحة.
            $table->boolean('default_enabled')->default(false);
            $table->integer('default_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            // null في limit/quota = بلا حد. في boolean يُهمَل.
            $table->integer('value')->nullable();
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
    }
};
