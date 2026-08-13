<?php

namespace Database\Seeders;

use App\Services\Tools\ToolBuilder;
use Illuminate\Database\Seeder;

/**
 * كتالوج الأدوات من ملفات التعريف في `database/data/tools`.
 *
 * كان هنا ثابت `COMING_SOON` بسبعة مفاتيح تُبذر بحالة «قريبًا»، وثلاثة
 * توابع `syncTool`/`syncFields`/`syncPrompts` تكرّر ما يفعله `ToolBuilder`.
 * الأول صار ميتًا حين اكتمل ملف التعريف لكل مفتاح من السبعة — يُتخطّى كلّه
 * في كل بذر. والثاني لم يكن يُستدعى أصلًا، وكان ينادي `DB::` بلا استيراد
 * الواجهة: أي استدعاء له كان سيسقط بخطأ قاتل. حُذفا معًا.
 */
class ToolCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // مصدر واحد لمنطق التركيب (ToolBuilder): البذر ولوحة الآدمن
        // وأمر tool:release يتبعون القواعد نفسها — BR-012 والإصدار وtier.
        $builder = app(ToolBuilder::class);

        foreach ($this->definitions() as $definition) {
            $builder->sync($definition);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return collect(glob(database_path('data/tools/*.php')))
            ->map(fn (string $path) => require $path)
            ->all();
    }
}
