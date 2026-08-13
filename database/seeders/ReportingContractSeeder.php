<?php

namespace Database\Seeders;

use App\Models\Objective;
use App\Models\RecommendationTemplate;
use App\Modules\Reporting\Templates\TemplateLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class ReportingContractSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = collect(require database_path('data/reporting/objectives.php'));

        foreach ($definitions as $definition) {
            Objective::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'domain' => $definition['domain'],
                    'description' => $definition['description'],
                    'active' => true,
                ],
            );
        }

        /*
         * البذر يقرأ المكتبة تحت لغة المصدر عمدًا.
         *
         * أجساد القوالب مغلَّفة بـ`__()`، فقراءتها تحت لغة أخرى تخزّن الترجمة
         * في قاعدة البيانات بدل المفتاح — وتتجمّد لغةُ من بَذَر على كل من يقرأ
         * بعده. ولغة المصدر بلا ملف ترجمة أصلًا، فـ`__()` تحتها تعيد النصّ
         * العربي نفسه: أي المفتاح الذي يترجمه `TemplateResolver` وقت العرض.
         *
         * والاستدعاء مباشر لا عبر `require` على ملف في `database/data`:
         * بوّابة «قدرة بلا مستدعٍ» تفحص ظهور اسم الصنف نصًّا في `app/` و`routes/`
         * و`database/seeders/` وحدها، فوساطةُ ملفٍ خارج تلك الجذور كانت تُخفي
         * `TemplateLibrary` عنها فتَعُدّه يتيمًا وهو مستعمَل فعلًا.
         */
        $source = (string) config('locales.source', 'ar');
        $previous = App::getLocale();
        App::setLocale($source);

        try {
            $definitions = TemplateLibrary::all();
        } finally {
            App::setLocale($previous);
        }

        foreach ($definitions as $definition) {
            $objective = Objective::where('slug', $definition['objective'])->firstOrFail();
            $template = RecommendationTemplate::updateOrCreate(
                [
                    'objective_id' => $objective->id,
                    'locale' => $definition['locale'],
                    'version' => $definition['version'],
                ],
                [
                    'kind' => $definition['kind'],
                    'title' => $definition['title'],
                    'body' => $definition['body'],
                    'required_context' => $definition['required_context'],
                    'is_hypothesis' => $definition['is_hypothesis'],
                    'active' => true,
                ],
            );

            $template->bindings()->delete();
            $template->bindings()->createMany($definition['bindings']);

            /*
             * الإصدار الأقدم يُعطَّل ولا يُحذف: توصيات صدرت فعلًا تشير إليه
             * بـ`template_id`، وحذفه يترك تقريرًا منشورًا بلا الورقة التي وُعد بها.
             */
            RecommendationTemplate::where('objective_id', $objective->id)
                ->where('locale', $definition['locale'])
                ->where('version', '<', $definition['version'])
                ->update(['active' => false]);
        }
    }
}
