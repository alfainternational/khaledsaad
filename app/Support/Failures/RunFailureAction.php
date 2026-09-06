<?php

declare(strict_types=1);

namespace App\Support\Failures;

/**
 * إجراء واحد واضح — لا قائمة خيارات.
 *
 * شاشة فشل بثلاثة أزرار تطلب من المستخدم أن يشخّص عطلنا بنفسه.
 */
final class RunFailureAction
{
    public function __construct(
        public readonly string $label,
        /** اسم مسار مسجَّل، أو `null` حين يكون التصحيح في مكانه لا في صفحة أخرى. */
        public readonly ?string $route = null,
        /** @var array<string, mixed> */
        public readonly array $parameters = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'route' => $this->route,
            'parameters' => $this->parameters,
        ];
    }
}
