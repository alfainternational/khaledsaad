<?php

namespace Tests\Unit\AI;

use App\Domain\AI\Support\LlmJsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LlmJsonParserTest extends TestCase
{
    #[Test]
    public function it_parses_clean_json(): void
    {
        $this->assertSame(['a' => 1], LlmJsonParser::parse('{"a":1}'));
    }

    #[Test]
    public function it_strips_code_fences(): void
    {
        $out = LlmJsonParser::parse("```json\n{\"a\":1}\n```");
        $this->assertSame(['a' => 1], $out);
    }

    #[Test]
    public function it_extracts_json_from_surrounding_noise(): void
    {
        $out = LlmJsonParser::parse('إليك النتيجة: {"executive_summary":"ملخص"} انتهى.');
        $this->assertSame('ملخص', $out['executive_summary']);
    }

    #[Test]
    public function it_repairs_trailing_commas_and_smart_quotes(): void
    {
        $out = LlmJsonParser::parse('{“a”: 1, “b”: [2, 3,],}');
        $this->assertSame(1, $out['a']);
        $this->assertSame([2, 3], $out['b']);
    }

    #[Test]
    public function it_enforces_required_keys(): void
    {
        // موجود لكن ينقصه المفتاح المطلوب → null (يُعامَل كفشل فيُعاد المحاولة/المحلي).
        $this->assertNull(LlmJsonParser::parse('{"other":"x"}', ['executive_summary']));
        $this->assertNull(LlmJsonParser::parse('{"executive_summary":""}', ['executive_summary']));
        $this->assertNotNull(LlmJsonParser::parse('{"executive_summary":"موجود"}', ['executive_summary']));
    }

    #[Test]
    public function it_returns_null_for_unusable_input(): void
    {
        $this->assertNull(LlmJsonParser::parse(null));
        $this->assertNull(LlmJsonParser::parse(''));
        $this->assertNull(LlmJsonParser::parse('نص بلا أي JSON'));
    }
}
