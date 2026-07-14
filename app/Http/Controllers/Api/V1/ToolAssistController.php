<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\AI\ChallengeToolAnswerAction;
use App\Application\AI\MapSpeechToToolFieldsAction;
use App\Domain\AI\Speech\SpeechToTextContract;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مساعدات الأدوات عبر API v1 (تطابق الموبايل): تفريغ صوتي يوزّع على الحقول
 * (المرحلة 2) وسؤال متابعة للإجابات الرخوة (المرحلة 3). تعيد استخدام نفس الـActions.
 */
class ToolAssistController extends Controller
{
    use ResolvesCurrentProject;

    private function tool(Request $request): Tool
    {
        $tool = Tool::query()->where('code', (string) $request->route('tcode'))->firstOrFail();
        abort_unless($tool->status !== 'hidden', 404);

        return $tool;
    }

    public function transcribe(
        Request $request,
        SpeechToTextContract $speech,
        MapSpeechToToolFieldsAction $mapper,
    ): JsonResponse {
        $this->authorize('view', $this->currentProject());
        $tool = $this->tool($request);

        if (! $speech->isAvailable()) {
            return response()->json(['error' => ['code' => 'SPEECH_UNAVAILABLE', 'message' => 'خدمة الصوت غير مفعّلة.']], 422);
        }

        $maxKb = max(1, (int) round(((int) config('services.ai.speech.max_bytes', 20971520)) / 1024));
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/mp3,audio/aac,audio/m4a,audio/x-m4a,video/webm'],
            'mode' => ['nullable', 'string', 'max:32'],
        ]);

        $file = $request->file('audio');
        $transcript = $speech->transcribe(
            (string) file_get_contents($file->getRealPath()),
            'audio.'.($file->getClientOriginalExtension() ?: 'm4a'),
        );

        if ($transcript === null || trim($transcript) === '') {
            return response()->json(['error' => ['code' => 'TRANSCRIBE_FAILED', 'message' => 'تعذّر تحويل الصوت إلى نص.']], 422);
        }

        $result = $mapper->handle($tool->code, (string) ($validated['mode'] ?? 'guided'), $transcript);

        return response()->json([
            'data' => [
                'transcript' => $transcript,
                'fields' => $result['fields'],
                'ai_mapped' => $result['ai_mapped'],
            ],
        ]);
    }

    public function challenge(Request $request, ChallengeToolAnswerAction $action): JsonResponse
    {
        $this->authorize('view', $this->currentProject());
        $tool = $this->tool($request);

        $validated = $request->validate([
            'field' => ['required', 'string', 'max:64'],
            'value' => ['required', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'max:32'],
        ]);

        $question = $action->handle(
            $tool->code,
            (string) ($validated['mode'] ?? 'guided'),
            $validated['field'],
            $validated['value'],
        );

        return response()->json(['data' => ['question' => $question]]);
    }
}
