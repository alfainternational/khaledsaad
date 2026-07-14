<?php

namespace App\Http\Controllers\Web;

use App\Application\AI\MapSpeechToToolFieldsAction;
use App\Domain\AI\Speech\SpeechToTextContract;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إدخال الصوت للأدوات (المرحلة 2): يستقبل تسجيلاً صوتياً، يفرّغه نصاً، ثم يوزّع
 * المعنى على حقول الأداة عبر الذكاء. يُرجع JSON ليملأ النموذج في الواجهة مباشرة.
 */
class ToolTranscriptionController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function store(
        Request $request,
        Tool $tool,
        SpeechToTextContract $speech,
        MapSpeechToToolFieldsAction $mapper,
    ): JsonResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        abort_unless($tool->status !== 'hidden', 404);

        if (! $speech->isAvailable()) {
            return response()->json([
                'message' => 'خدمة الصوت غير مفعّلة حالياً. اكتب إجابتك يدوياً.',
            ], 422);
        }

        $maxKb = max(1, (int) round(((int) config('services.ai.speech.max_bytes', 20971520)) / 1024));
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/mp3,video/webm'],
            'mode' => ['nullable', 'string', 'max:32'],
        ]);

        $file = $request->file('audio');
        $contents = (string) file_get_contents($file->getRealPath());
        $filename = 'audio.'.($file->getClientOriginalExtension() ?: 'webm');

        $transcript = $speech->transcribe($contents, $filename);

        if ($transcript === null || trim($transcript) === '') {
            return response()->json([
                'message' => 'تعذّر تحويل الصوت إلى نص. حاول مرة أخرى أو اكتب يدوياً.',
            ], 422);
        }

        $mode = (string) ($validated['mode'] ?? 'guided');
        $result = $mapper->handle($tool->code, $mode, $transcript);

        return response()->json([
            'transcript' => $transcript,
            'fields' => $result['fields'],
            'ai_mapped' => $result['ai_mapped'],
        ]);
    }
}
