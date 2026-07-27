<?php

namespace App\Services\Tools\V2;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * بوابة دلالية أخيرة بين مخرج النموذج وحفظ التقرير.
 *
 * المخطط يثبت الشكل، وهذه الطبقة تثبت المعنى: لا رقم بلا أصل، ولا دليل
 * غير موجود في مدخلات العميل، ولا تعليمات محقونة أو توصيات مكررة.
 */
class ReportSemanticGuard
{
    /**
     * @param  array<string, mixed>  $synthesis
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $baseline
     * @return array<string, mixed>
     */
    public function repair(array $synthesis, array $context, array $baseline): array
    {
        unset($synthesis['score'], $synthesis['base_score'], $synthesis['score_band']);

        if (isset($synthesis['confidence'])) {
            $synthesis['confidence'] = max(0, min(100, (int) $synthesis['confidence']));
        }

        $source = $this->searchable([$context, $baseline]);
        $allowedNumbers = $this->numbers($source);
        $seen = [];
        $findings = [];
        $assumptions = Arr::wrap($synthesis['assumptions'] ?? []);

        foreach (Arr::wrap($synthesis['findings'] ?? []) as $finding) {
            if (! is_array($finding) || $this->containsInjection($finding)) {
                continue;
            }

            $title = trim((string) ($finding['title'] ?? ''));
            $fingerprint = Str::lower(preg_replace('/\s+/u', ' ', $title) ?? $title);

            if ($title === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $evidence = trim((string) ($finding['evidence'] ?? ''));
            $supported = $evidence !== '' && Str::contains($source, $this->normalise($evidence));
            $unsupportedNumbers = array_diff(
                $this->numbers($this->searchable($finding)),
                $allowedNumbers,
            );

            if (! $supported || $unsupportedNumbers !== []) {
                $finding['is_assumption'] = true;
                unset($finding['evidence']);

                if ($unsupportedNumbers !== []) {
                    $assumptions[] = 'تحتاج الأرقام الواردة في نتيجة «'.$title.'» إلى قياس أو مصدر قبل اعتمادها.';
                }
            } else {
                $finding['is_assumption'] = (bool) ($finding['is_assumption'] ?? false);
            }

            $finding['claim_type'] = $finding['is_assumption'] ? 'inference' : 'observed';
            $finding['provenance'] = $finding['is_assumption']
                ? 'inference_from_inputs'
                : 'user_input';
            $finding['recommendations'] = collect(Arr::wrap($finding['recommendations'] ?? []))
                ->filter(fn ($recommendation) => is_array($recommendation) && ! $this->containsInjection($recommendation))
                ->unique(fn (array $recommendation) => Str::lower(trim((string) ($recommendation['title'] ?? ''))))
                ->take(3)
                ->values()
                ->all();

            if ($finding['recommendations'] !== []) {
                $findings[] = $finding;
            }
        }

        $synthesis['findings'] = array_slice($findings, 0, 8);
        $synthesis['assumptions'] = array_values(array_slice(array_unique(array_filter(
            array_map(fn ($item) => trim((string) $item), $assumptions),
        )), 0, 10));

        if ($this->containsInjection($synthesis['summary'] ?? '')) {
            unset($synthesis['summary']);
        }

        $firstRecommendation = $synthesis['findings'][0]['recommendations'][0] ?? null;
        if (is_array($firstRecommendation)) {
            $synthesis['next_step'] = [
                'title' => $firstRecommendation['title'],
                'description' => $firstRecommendation['description'],
            ];
        } elseif ($this->containsInjection($synthesis['next_step'] ?? [])) {
            unset($synthesis['next_step']);
        }

        return $synthesis;
    }

    private function searchable(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->normalise(is_string($encoded) ? $encoded : '');
    }

    private function normalise(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    /** @return array<int, string> */
    private function numbers(string $value): array
    {
        preg_match_all('/(?<![\pL\pN])\d+(?:[.,]\d+)?/u', $value, $matches);

        return array_values(array_unique(array_map(
            fn (string $number) => str_replace(',', '.', ltrim($number, '0') ?: '0'),
            $matches[0] ?? [],
        )));
    }

    private function containsInjection(mixed $value): bool
    {
        $text = $this->searchable($value);

        foreach ([
            'ignore previous', 'ignore all instructions', 'system prompt',
            'تجاهل التعليمات', 'تجاهل كل ما سبق', 'تعليمات النظام',
            'اكشف البرومبت', 'اطبع البرومبت',
        ] as $needle) {
            if (Str::contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
