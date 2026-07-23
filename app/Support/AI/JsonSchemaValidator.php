<?php

namespace App\Support\AI;

/**
 * مُتحقق من مجموعة فرعية من JSON Schema تكفي مخرجات الأدوات.
 *
 * سبب كتابته بدل مكتبة خارجية: المخططات المستخدمة هنا محدودة ومعروفة،
 * والاعتماد على حزمة كاملة يضيف تبعية ثقيلة مقابل ميزات لا نستعملها.
 * المدعوم: type, required, properties, items, enum, minimum, maximum,
 * minItems, maxItems, minLength.
 */
class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string> قائمة المخالفات، فارغة عند النجاح.
     */
    public function validate(mixed $value, array $schema, string $path = '$'): array
    {
        $violations = [];

        if (isset($schema['type']) && ! $this->matchesType($value, $schema['type'])) {
            return ["{$path}: النوع المتوقع {$schema['type']}."];
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $violations[] = "{$path}: القيمة خارج القائمة المسموحة (".implode('، ', $schema['enum']).').';
        }

        if (is_array($value) && $this->isAssociative($value, $schema)) {
            $violations = array_merge($violations, $this->validateObject($value, $schema, $path));
        }

        if (is_array($value) && ($schema['type'] ?? null) === 'array') {
            $violations = array_merge($violations, $this->validateArray($value, $schema, $path));
        }

        if (is_string($value) && isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) {
            $violations[] = "{$path}: النص أقصر من {$schema['minLength']} حرفًا.";
        }

        if (is_numeric($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $violations[] = "{$path}: القيمة أقل من {$schema['minimum']}.";
            }

            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $violations[] = "{$path}: القيمة أكبر من {$schema['maximum']}.";
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateObject(array $value, array $schema, string $path): array
    {
        $violations = [];

        foreach ($schema['required'] ?? [] as $requiredKey) {
            if (! array_key_exists($requiredKey, $value)) {
                $violations[] = "{$path}.{$requiredKey}: حقل مطلوب مفقود.";
            }
        }

        foreach ($schema['properties'] ?? [] as $key => $childSchema) {
            if (array_key_exists($key, $value)) {
                $violations = array_merge(
                    $violations,
                    $this->validate($value[$key], $childSchema, "{$path}.{$key}"),
                );
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, mixed>  $value
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function validateArray(array $value, array $schema, string $path): array
    {
        $violations = [];

        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            $violations[] = "{$path}: العناصر أقل من {$schema['minItems']}.";
        }

        if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
            $violations[] = "{$path}: العناصر أكثر من {$schema['maxItems']}.";
        }

        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $violations = array_merge(
                    $violations,
                    $this->validate($item, $schema['items'], "{$path}[{$index}]"),
                );
            }
        }

        return $violations;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ! array_is_list($value),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function isAssociative(array $value, array $schema): bool
    {
        return ($schema['type'] ?? null) === 'object'
            || (isset($schema['properties']) && ! array_is_list($value));
    }
}
