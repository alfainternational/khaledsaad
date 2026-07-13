<?php

namespace Tests\Unit\AI\Chat;

use App\Domain\AI\Chat\ChatAnswerExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChatAnswerExtractorTest extends TestCase
{
    #[Test]
    #[DataProvider('answers')]
    public function it_extracts_natural_answer_text(array $result, string $expected): void
    {
        $this->assertSame($expected, (new ChatAnswerExtractor)->extract($result));
    }

    public static function answers(): array
    {
        return [
            'answer' => [['answer' => 'الإجابة الطبيعية', '_model_name' => 'qwen3:1.7b'], 'الإجابة الطبيعية'],
            'response' => [['response' => "  رد واضح\n"], 'رد واضح'],
            'text' => [['text' => 'نص بديل'], 'نص بديل'],
        ];
    }

    #[Test]
    public function it_rejects_metadata_only_results(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ChatAnswerExtractor)->extract(['_model_name' => 'qwen3:1.7b']);
    }
}
