<?php

namespace App\Domain\AI\Kernel;

use App\Domain\AI\Kernel\Contracts\Skill;
use Illuminate\Contracts\Container\Container;

/**
 * سجلّ المهارات. يحفظ أسماء أصناف المهارات ويحلّها كسلاً (lazily) من الـ container
 * عند الحاجة فقط — لا تكلفة إقلاع، يناسب طلب PHP-FPM القصير.
 *
 * النظير في cloud: skills/loadSkillsDir.ts + bundledSkills.ts.
 */
class SkillRegistry
{
    /** @var array<int, class-string<Skill>> */
    private array $skills = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Skill>  $skillClass
     */
    public function register(string $skillClass): self
    {
        $this->skills[] = $skillClass;

        return $this;
    }

    /**
     * أعد أول مهارة قادرة على التصرّف في هذا السياق.
     */
    public function resolveFor(AgentContext $context): ?Skill
    {
        foreach ($this->skills as $skillClass) {
            /** @var Skill $skill */
            $skill = $this->container->make($skillClass);
            if ($skill->handles($context)) {
                return $skill;
            }
        }

        return null;
    }

    public function get(string $code, AgentContext $context): ?Skill
    {
        foreach ($this->skills as $skillClass) {
            /** @var Skill $skill */
            $skill = $this->container->make($skillClass);
            if ($skill->code() === $code && $skill->handles($context)) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * @return array<int, class-string<Skill>>
     */
    public function all(): array
    {
        return $this->skills;
    }
}
