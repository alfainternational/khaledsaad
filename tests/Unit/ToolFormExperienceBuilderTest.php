<?php

namespace Tests\Unit;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Support\Tooling\ToolFormExperienceBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolFormExperienceBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_adaptive_field_experience_from_project_profile_and_context(): void
    {
        $builder = new ToolFormExperienceBuilder();

        $tool = new Tool([
            'code' => 'offer-builder',
            'name' => 'Offer Builder',
            'stage' => 3,
        ]);

        $project = new Project([
            'name' => 'Retention Sprint',
            'stage' => 3,
        ]);
        $project->setRelation('client', new Client(['name' => 'Acme Foods']));

        $latestRun = new ToolRun([
            'summary_json' => [
                'headline' => 'العرض الحالي جيد لكن يحتاج وعداً أوضح',
            ],
        ]);

        $blueprint = [
            'intro' => 'Intro',
            'why' => 'Why',
            'when' => 'When',
            'outcome' => 'Outcome',
            'ai_role' => 'AI role',
            'result_label' => 'Offer Result',
            'modes' => [
                'guided' => [
                    'label' => 'بسيط',
                    'description' => 'desc',
                    'fields' => [
                        [
                            'key' => 'offer_audience',
                            'label' => 'لمن هذا العرض؟',
                            'type' => 'text',
                            'placeholder' => 'مثال: مطاعم محلية صغيرة',
                        ],
                        [
                            'key' => 'offer_result',
                            'label' => 'ما النتيجة الأساسية؟',
                            'type' => 'text',
                            'placeholder' => 'مثال: طلبات أكثر',
                        ],
                        [
                            'key' => 'offer_guarantee',
                            'label' => 'ما عنصر الطمأنة أو تقليل المخاطرة؟',
                            'type' => 'text',
                            'placeholder' => 'مثال: مراجعة أول شهر',
                        ],
                    ],
                ],
            ],
        ];

        $profile = [
            'audience' => 'المطاعم المحلية الصغيرة',
            'primary_goal' => 'زيادة الاستفسارات الجادة',
            'country' => 'السعودية',
            'content_locale' => 'ar_modern_fusha',
        ];

        $experience = $builder->build(
            $tool,
            $blueprint,
            $profile,
            $project,
            $latestRun,
            [['headline' => 'تشخيص: الرسالة الحالية غير واضحة']],
        );

        $this->assertStringContainsString('Retention Sprint', $experience['summary']['intro']);
        $this->assertSame('المطاعم المحلية الصغيرة', data_get($experience, 'modes.guided.fields.offer_audience.suggested_value'));
        $this->assertSame('زيادة الاستفسارات الجادة', data_get($experience, 'modes.guided.fields.offer_result.suggested_value'));
        $this->assertSame('critical', data_get($experience, 'modes.guided.fields.offer_audience.priority'));
        $this->assertStringContainsString('زيادة الاستفسارات الجادة', data_get($experience, 'modes.guided.fields.offer_result.context_hint'));
        $this->assertStringContainsString('العرض الحالي جيد لكن يحتاج وعداً أوضح', data_get($experience, 'modes.guided.fields.offer_guarantee.context_hint'));
    }

    #[Test]
    public function catalog_goal_slugs_are_context_only_not_pasted_as_suggested_answers(): void
    {
        $builder = new ToolFormExperienceBuilder();

        $tool = new Tool([
            'code' => 'goal-definition',
            'name' => 'Goal Definition',
            'stage' => 1,
        ]);

        $blueprint = [
            'modes' => [
                'guided' => [
                    'label' => 'بسيط',
                    'description' => 'desc',
                    'fields' => [
                        [
                            'key' => 'primary_goal_text',
                            'label' => 'ما الهدف الأهم الآن؟',
                            'type' => 'textarea',
                            'placeholder' => 'صف هدفك',
                        ],
                    ],
                ],
            ],
        ];

        $profile = [
            'primary_goal' => 'clarify_idea',
        ];

        $experience = $builder->build($tool, $blueprint, $profile, null, null, []);

        $this->assertNull(data_get($experience, 'modes.guided.fields.primary_goal_text.suggested_value'));
        $hint = (string) data_get($experience, 'modes.guided.fields.primary_goal_text.context_hint');
        $this->assertStringContainsString('ما الهدف الأهم الآن؟', $hint);
        $this->assertStringContainsString('توضيح الفكرة', $hint);
        $this->assertStringNotContainsString('clarify_idea', $hint);
    }

    #[Test]
    public function goal_reason_fields_use_rationale_hints_not_duplicate_goal_outcome_text(): void
    {
        $builder = new ToolFormExperienceBuilder();

        $tool = new Tool([
            'code' => 'goal-definition',
            'name' => 'Goal Definition',
            'stage' => 1,
        ]);

        $blueprint = [
            'modes' => [
                'guided' => [
                    'label' => 'بسيط',
                    'description' => 'desc',
                    'fields' => [
                        [
                            'key' => 'goal_reason',
                            'label' => 'لماذا هذا الهدف هو الأهم؟',
                            'type' => 'textarea',
                            'placeholder' => '',
                        ],
                    ],
                ],
            ],
        ];

        $profile = ['primary_goal' => 'clarify_idea'];

        $experience = $builder->build($tool, $blueprint, $profile, null, null, []);

        $this->assertSame('goal_rationale', data_get($experience, 'modes.guided.fields.goal_reason.category'));
        $this->assertStringContainsString('السبب التجاري', (string) data_get($experience, 'modes.guided.fields.goal_reason.context_hint'));
        $this->assertNotNull(data_get($experience, 'modes.guided.fields.goal_reason.suggested_value'));
    }
}
