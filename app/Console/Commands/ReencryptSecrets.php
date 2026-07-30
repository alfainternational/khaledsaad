<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * إعادة تشفير كل ما هو مشفَّر بمفتاح التطبيق الحالي.
 *
 * يُستدعى بعد تدوير `APP_KEY`. الترتيب الملزم:
 *   ١. `APP_KEY` الجديد في `.env`، و`APP_PREVIOUS_KEYS` يحمل القديم.
 *   ٢. هذا الأمر — يفكّ بالمفتاح القديم ويكتب بالجديد.
 *   ٣. حذف `APP_PREVIOUS_KEYS` بعد التحقق.
 *
 * حذف المفتاح القديم قبل الخطوة ٢ يجعل البيانات غير قابلة للفك إلى الأبد: لا
 * توجد نسخة ثانية من مفاتيح بوابات الدفع.
 *
 * **مرحلتان لا واحدة.** الفكّ كله أولًا، ثم الكتابة كلها في معاملة واحدة. صفٌّ
 * واحد يتعذّر فكّه يُلغي العملية قبل أن تُكتب بايت: تدويرٌ نصفه بمفتاح ونصفه
 * بآخر يترك بوابة دفع معطّلة لا يُعرف أيّها.
 *
 * الوحدة المعالَجة هي **النص المشفَّر نفسه** لا معناه: `encryptString` فوق
 * `decryptString` يعيد التشفير بلا تفسير المحتوى، فيصحّ للسلسلة والمصفوفة
 * والسرّ معًا بلا فرع لكل نوع.
 */
class ReencryptSecrets extends Command
{
    protected $signature = 'platform:reencrypt
        {--dry-run : فكّ وتحقّق بلا كتابة}';

    protected $description = 'إعادة تشفير الأسرار بمفتاح التطبيق الحالي بعد تدويره';

    /**
     * الجداول المشفَّرة: الجدول، العمود، وشرط اختيار الصفوف.
     *
     * @var array<int, array{table: string, column: string, where: ?array{0: string, 1: string}, label: string}>
     */
    private const TARGETS = [
        [
            'table' => 'payment_gateways',
            'column' => 'credentials',
            'where' => null,
            'label' => 'بوابات الدفع',
        ],
        [
            // الأسرار وحدها مشفّرة؛ باقي الإعدادات نصّ صريح ولا يُلمس.
            'table' => 'settings',
            'column' => 'value',
            'where' => ['type', 'secret'],
            'label' => 'أسرار الإعدادات',
        ],
        [
            'table' => 'device_tokens',
            'column' => 'token',
            'where' => null,
            'label' => 'رموز الأجهزة',
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $plan = [];
        $failures = [];

        foreach (self::TARGETS as $target) {
            if (! DB::getSchemaBuilder()->hasTable($target['table'])) {
                $this->warn("الجدول {$target['table']} غير موجود — تخطٍّ.");

                continue;
            }

            $query = DB::table($target['table'])
                ->select('id', $target['column'])
                ->whereNotNull($target['column']);

            if ($target['where'] !== null) {
                $query->where($target['where'][0], $target['where'][1]);
            }

            $rows = $query->get();
            $decrypted = 0;

            foreach ($rows as $row) {
                $cipher = (string) $row->{$target['column']};

                if ($cipher === '') {
                    continue;
                }

                try {
                    $plain = Crypt::decryptString($cipher);
                } catch (Throwable $exception) {
                    /*
                     * القيمة التي لا تُفكّ لا تُعاد تشفيرها: تشفير نصٍّ مشفَّر
                     * يخلق طبقة ثانية لا يعرف بها أحد، والقيمة الأصلية تُفقد.
                     */
                    $failures[] = "{$target['table']}#{$row->id}: {$exception->getMessage()}";

                    continue;
                }

                $plan[] = [
                    'table' => $target['table'],
                    'column' => $target['column'],
                    'id' => $row->id,
                    'cipher' => Crypt::encryptString($plain),
                ];

                $decrypted++;
            }

            $this->line("  {$target['label']}: {$decrypted} من ".$rows->count());
        }

        if ($failures !== []) {
            $this->error('تعذّر فكّ صفوف — أُلغيت العملية قبل أي كتابة:');

            foreach ($failures as $failure) {
                $this->line("  - {$failure}");
            }

            $this->newLine();
            $this->warn('تحقّق أن APP_PREVIOUS_KEYS يحمل المفتاح القديم.');

            return self::FAILURE;
        }

        if ($plan === []) {
            $this->info('لا صفوف مشفَّرة — لا شيء يُعاد تشفيره.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('تجربة جافة: '.count($plan).' صفًّا قابلًا للفك، ولم يُكتب شيء.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan as $item) {
                DB::table($item['table'])
                    ->where('id', $item['id'])
                    ->update([$item['column'] => $item['cipher']]);
            }
        });

        $this->info('أُعيد تشفير '.count($plan).' صفًّا بالمفتاح الحالي.');
        $this->warn('احذف APP_PREVIOUS_KEYS من .env بعد التحقق من platform:preflight.');

        return self::SUCCESS;
    }
}
