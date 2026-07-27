<?php

namespace App\Services\Consultations\Engine;

use App\Models\QuestionVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnswerValidator
{
    /** @param array<string,mixed> $payload */
    public function validate(QuestionVersion $question, array $payload): void
    {
        $unknown = (bool) ($payload['unknown'] ?? false);
        $skipped = (bool) ($payload['skipped'] ?? false);
        if ($unknown && ! $question->allow_unknown) {
            throw ValidationException::withMessages(['unknown' => 'هذا السؤال لا يسمح باختيار «لا أعرف».']);
        }
        if ($skipped && ! $question->allow_skip) {
            throw ValidationException::withMessages(['skipped' => 'هذا السؤال لا يسمح بالتخطي.']);
        }
        if ($unknown || $skipped) {
            return;
        }

        $value = $payload['value'] ?? null;
        $allowed = collect($question->options ?? [])->pluck('value')->map(fn ($item) => (string) $item)->all();
        if ($question->answer_type === 'boolean' && $allowed === []) {
            $allowed = ['1', '0', 'true', 'false'];
        }
        $rules = match ($question->answer_type) {
            'select', 'radio', 'boolean', 'confirmation' => ['required', 'string', 'max:500', Rule::in($allowed ?: ['1', '0', 'true', 'false'])],
            'multiselect' => ['required', 'array', 'min:1', 'max:30'],
            'number' => ['required', 'numeric'],
            'scale' => ['required', 'numeric', 'min:'.data_get($question->validation, 'min', 1), 'max:'.data_get($question->validation, 'max', 10)],
            'range' => ['required', 'array:min,max'],
            'ranking' => ['required', 'array', 'min:1', 'max:30'],
            'repeater' => ['required', 'array', 'min:1', 'max:'.data_get($question->validation, 'max_items', 20)],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'email' => ['required', 'email:rfc', 'max:320'],
            'date' => ['required', 'date'],
            default => ['required', 'string', 'max:5000'],
        };
        // توافق خلفي: العملاء الأقدم يرسلون خيارًا واحدًا كسلسلة حتى بعد
        // ترقية السؤال إلى متعدد. نتحقق منه كقائمة من عنصر واحد، ونترك
        // الحمولة الأصلية كما هي كي لا نكسر السجلات القديمة.
        $validationValue = $question->answer_type === 'multiselect' && ! is_array($value)
            ? [$value]
            : $value;
        $validator = Validator::make(['value' => $validationValue], ['value' => $rules]);
        if ($question->answer_type === 'multiselect') {
            $validator->after(function ($validator) use ($validationValue, $allowed): void {
                foreach ((array) $validationValue as $item) {
                    if (! is_scalar($item) || ! in_array((string) $item, $allowed, true)) {
                        $validator->errors()->add('value', 'تحتوي الإجابة على خيار غير معتمد.');
                        break;
                    }
                }
            });
        }
        if ($question->answer_type === 'range') {
            $validator->after(function ($validator) use ($value, $question): void {
                $minimum = data_get($value, 'min');
                $maximum = data_get($value, 'max');
                if (! is_numeric($minimum) || ! is_numeric($maximum) || (float) $minimum > (float) $maximum) {
                    $validator->errors()->add('value', 'أدخل نطاقًا صحيحًا؛ الحد الأدنى لا يتجاوز الحد الأعلى.');
                }
                if ((float) $minimum < (float) data_get($question->validation, 'min', -INF)
                    || (float) $maximum > (float) data_get($question->validation, 'max', INF)) {
                    $validator->errors()->add('value', 'النطاق خارج الحدود المسموح بها.');
                }
            });
        }
        if ($question->answer_type === 'ranking') {
            $validator->after(function ($validator) use ($value, $allowed): void {
                $keys = array_map('strval', array_keys((array) $value));
                $ranks = array_values((array) $value);
                if (array_diff($keys, $allowed) !== [] || count($ranks) !== count(array_unique($ranks))
                    || collect($ranks)->contains(fn ($rank) => ! is_numeric($rank) || (int) $rank < 1)) {
                    $validator->errors()->add('value', 'رتّب الخيارات بأرقام صحيحة غير مكررة.');
                }
            });
        }
        if ($question->answer_type === 'repeater') {
            $validator->after(function ($validator) use ($value): void {
                if (collect((array) $value)->contains(fn ($item) => ! is_string($item) || blank($item) || mb_strlen($item) > 1000)) {
                    $validator->errors()->add('value', 'كل عنصر يجب أن يكون نصًا صالحًا.');
                }
            });
        }
        $validator->validate();
    }
}
