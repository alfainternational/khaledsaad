<?php

namespace App\Console\Commands;

use App\Models\PromptVersion;
use App\Models\Tool;
use App\Models\ToolVersion;
use App\Services\Tools\ToolBuilder;
use Illuminate\Console\Command;

/**
 * سكّ إصدار جديد لأداة واحدة من ملف تعريفها.
 *
 * لماذا: البرومبت المقفل (BR-012) لا يُعدَّل أبدًا — تعديله يستوجب إصدار
 * أداة جديدًا ببرومبتات جديدة غير مقفلة، والقديم يبقى أثرًا محفوظًا.
 * كانت الآلية موجودة في ToolBuilder لكن بلا أمر موجّه، فتحديث برومبت
 * مقفل كان يحتاج تشغيل tinker يدويًا.
 */
class ReleaseToolVersion extends Command
{
    protected $signature = 'tool:release
        {key : مفتاح الأداة كما في database/data/tools}
        {--dry-run : اعرض ما سيحدث دون أي كتابة}';

    protected $description = 'يسكّ إصدارًا جديدًا لأداة واحدة من ملف تعريفها، ببرومبتات جديدة غير مقفلة';

    public function handle(ToolBuilder $builder): int
    {
        $key = (string) $this->argument('key');
        $path = database_path("data/tools/{$key}.php");

        if (! is_file($path)) {
            $this->error("لا يوجد ملف تعريف: {$path}");

            return self::FAILURE;
        }

        $tool = Tool::where('key', $key)->first();

        if ($tool === null) {
            $this->error("الأداة {$key} غير مبذورة بعد — شغّل db:seed --class=ToolCatalogSeeder أولًا.");

            return self::FAILURE;
        }

        $definition = require $path;
        $current = ToolVersion::where('tool_id', $tool->id)->max('version');
        $next = ((int) $current) + 1;
        $previous = ToolVersion::where('tool_id', $tool->id)->where('version', $current)->first();
        $lockedStages = $previous
            ? PromptVersion::where('tool_version_id', $previous->id)->whereNotNull('locked_at')->pluck('stage')
            : collect();

        $this->line("الأداة: {$key} — الإصدار الحالي v{$current}، الجديد v{$next}.");

        if ($lockedStages->isNotEmpty()) {
            $this->line('برومبتات مقفلة في الإصدار الحالي (ستبقى أثرًا ويُنشأ بديلها غير مقفل): '.$lockedStages->implode('، '));
        }

        if ($this->option('dry-run')) {
            $this->info('dry-run: لم يُكتب شيء.');

            return self::SUCCESS;
        }

        $definition['version']['number'] = $next;
        $builder->sync($definition);

        $released = ToolVersion::where('tool_id', $tool->id)->where('version', $next)->firstOrFail();

        // tier قرار تشغيلي محفوظ عبر الإصدارات — لا يعود standard مع كل سكّ.
        if ($previous) {
            PromptVersion::where('tool_version_id', $previous->id)
                ->get()
                ->each(function (PromptVersion $old) use ($released): void {
                    PromptVersion::where('tool_version_id', $released->id)
                        ->where('stage', $old->stage)
                        ->update(['tier' => $old->tier]);
                });
        }

        $prompts = PromptVersion::where('tool_version_id', $released->id)->count();

        $this->info("صدر v{$next} (id={$released->id}) وصار الإصدار الفعّال، ببرومبتات غير مقفلة ({$prompts}).");

        $this->bumpDefinitionNumber($path, $next);

        return self::SUCCESS;
    }

    /**
     * يبقي ملف التعريف مصدر الحقيقة: أول 'number' في الملف هو رقم الإصدار
     * (كتلة version تسبق الحقول دائمًا)، فيُرفع ليطابق ما صدر.
     */
    private function bumpDefinitionNumber(string $path, int $next): void
    {
        $contents = (string) file_get_contents($path);
        $updated = preg_replace("/('number'\s*=>\s*)\d+/", '${1}'.$next, $contents, 1, $count);

        if ($count === 1 && $updated !== null) {
            file_put_contents($path, $updated);
            $this->line("حُدّث رقم الإصدار في ملف التعريف إلى {$next}.");

            return;
        }

        $this->warn("لم أستطع تحديث رقم الإصدار في {$path} — حدّث 'number' يدويًا إلى {$next}.");
    }
}
