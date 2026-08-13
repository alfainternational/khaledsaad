<?php

namespace App\Modules\Intake\Fields;

use App\Models\ToolField;
use App\Modules\Intake\Assist\ProfileQuestions;
use App\Support\Marketing\BriefQuestions;
use Illuminate\Support\Collection;

/**
 * دليل الحقول: من مفتاح حقل إلى سؤاله المقروء والباب الذي يُملأ منه.
 *
 * **سبب وجوده:** كان التقرير يقول «ناقص نعرفه عنك: جمهورك» ولا يقول أين
 * يُكتب. والسبب بنيويّ لا تحريريّ: الفجوة كانت تصل كنصّ حرّ يخترعه النموذج
 * بلا مفتاح، فلا يمكن ربطها بحقل حتى لو أردنا. وبلا مفتاح لا يوجد زر.
 *
 * وهذا الدليل هو الطرف الآخر من الجسر: يأخذ مفتاحًا حقيقيًّا فيعيد نصّ
 * السؤال كما يراه صاحب النشاط، ومصدره — ملفُّ المشروع، أو سؤالُ أداة، أو
 * بندٌ في موجز الوكالة — لأن لكل مصدر شاشة يُملأ منها.
 *
 * **لماذا لا `QuestionLocator`:** ذاك يحتاج تشغيلًا أو جلسة ليعرف **أي**
 * نسخة من السؤال يقصد المستخدم، وهو محقّ: `active_channels` سؤالان مختلفان
 * في أداتين. أما هنا فالسياق تشغيلٌ انتهى وتقريرٌ صدر، والمطلوب أن نعرف أين
 * يُكتب الجواب لا أي صياغة رآها. فيكفي مفتاحٌ واحد، ويُفضَّل الملفُّ عند
 * التعارض لأنه الباب الذي يبقى مفتوحًا بعد انتهاء التشغيل.
 */
final class FieldDirectory
{
    public const SOURCE_PROFILE = 'profile';

    public const SOURCE_TOOL = 'tool';

    public const SOURCE_BRIEF = 'brief';

    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    /** @var Collection<string, ToolField>|null */
    private ?Collection $fields = null;

    /**
     * وصف حقل واحد، أو `null` إن لم يكن مفتاحًا نعرفه.
     *
     * إرجاع `null` مقصود ومهمّ: الفجوة التي يخترع النموذج مفتاحها لا تصير
     * زرًّا يفتح شاشة فارغة، بل تبقى ملاحظة نصّية معلنة. زرٌّ يقود إلى لا
     * شيء أسوأ من غياب الزر.
     *
     * @return array{key: string, label: string, help: ?string, source: string}|null
     */
    public function describe(string $key): ?array
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->lookup($key);
    }

    /**
     * أوصاف عدة مفاتيح دفعةً واحدة، بلا ما لا نعرفه.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, label: string, help: ?string, source: string}>
     */
    public function describeMany(array $keys): array
    {
        return array_values(array_filter(array_map(
            fn (string $key): ?array => $this->describe($key),
            array_values(array_unique($keys)),
        )));
    }

    /**
     * هل هذا مفتاح حقل نعرفه أصلًا؟
     */
    public function knows(string $key): bool
    {
        return $this->describe($key) !== null;
    }

    /**
     * @return array{key: string, label: string, help: ?string, source: string}|null
     */
    private function lookup(string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        $profile = ProfileQuestions::find($key);
        if ($profile !== null) {
            return [
                'key' => $key,
                'label' => (string) $profile['label'],
                'help' => isset($profile['help']) ? (string) $profile['help'] : null,
                'source' => self::SOURCE_PROFILE,
            ];
        }

        $field = $this->toolFields()->get($key);
        if ($field instanceof ToolField) {
            return [
                'key' => $key,
                'label' => (string) $field->label,
                'help' => $field->help === null ? null : (string) $field->help,
                'source' => self::SOURCE_TOOL,
            ];
        }

        $brief = collect(BriefQuestions::fields())->firstWhere('key', $key);
        if (is_array($brief)) {
            return [
                'key' => $key,
                'label' => (string) ($brief['label'] ?? $key),
                // أسئلة الموجز تسمّي التفسير `why` لا `help`؛ كلاهما نفس
                // السطر الذي يقرأه صاحب النشاط تحت السؤال.
                'help' => isset($brief['why']) ? (string) $brief['why'] : null,
                'source' => self::SOURCE_BRIEF,
            ];
        }

        return null;
    }

    /**
     * حقول الأدوات مفهرسةً بمفتاحها.
     *
     * الاستعلام مرة واحدة لكل نسخة من الدليل: التقرير الواحد قد يعلن عشر
     * فجوات، وعشرة استعلامات لجدول ثابت تكلفة بلا مقابل. والحفظ في خاصية
     * لا في `static` عمدًا — العامل في الطابور يعيش طويلًا، وذاكرة ساكنة
     * فيه تُبقي حقول أداة حُذفت حيّةً إلى أن يُعاد تشغيله.
     *
     * و`unique` تُبقي أول ظهور لأن الفروق بين نسخ الأداة في الصياغة لا في
     * وجهة الملء.
     *
     * @return Collection<string, ToolField>
     */
    private function toolFields(): Collection
    {
        if ($this->fields === null) {
            $this->fields = ToolField::query()
                ->orderBy('tool_version_id')
                ->orderBy('sort_order')
                ->get(['id', 'tool_version_id', 'key', 'label', 'help'])
                ->unique('key')
                ->keyBy('key');
        }

        return $this->fields;
    }
}
