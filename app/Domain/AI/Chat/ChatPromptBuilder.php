<?php

namespace App\Domain\AI\Chat;

use App\Domain\AI\Chat\Models\AiChatConversation;
use App\Support\AI\WorkspaceGenerationContextBuilder;

class ChatPromptBuilder
{
    public function __construct(private readonly WorkspaceGenerationContextBuilder $contextBuilder) {}

    /** @return array{prompt: string, system_prompt: string} */
    public function build(AiChatConversation $conversation, string $currentMessage): array
    {
        $history = $conversation->messages()
            ->where('status', 'completed')
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(20)
            ->get(['role', 'content'])
            ->reverse()
            ->map(function ($message): string {
                $label = $message->role === 'assistant' ? 'Assistant' : 'User';

                return $label.': '.trim((string) $message->content);
            })
            ->push('User: '.$currentMessage)
            ->implode("\n\n");

        $context = $this->contextBuilder->promptBlockForIds(
            $conversation->workspace_id,
            $conversation->project_id,
            $currentMessage,
        );
        $system = implode("\n\n", array_filter([
            'أنت المستشار الذكي في منصة التسويق الاستراتيجي. أجب بالعربية بوضوح ودفء مهني، وقدّم توصية عملية دقيقة دون تكرار السؤال.',
            $context,
            'أعد كائن JSON فقط يحتوي مفتاح answer وقيمته نص الإجابة الطبيعي. لا تضف مفاتيح أخرى.',
        ]));

        return ['prompt' => $history, 'system_prompt' => $system];
    }
}
