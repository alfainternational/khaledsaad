<?php

namespace App\Domain\AI\Speech;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * منفّذ التفريغ الصوتي عبر Groq Whisper (متوافق مع OpenAI audio/transcriptions).
 *
 * يقرأ الإعداد وقت النداء ليلتقط تجاوزات الآدمن (SettingsStore). المفتاح يرث
 * مفتاح Groq العام للتوليد عند غياب مفتاح مخصّص للصوت — فمفتاح واحد يكفي الاثنين.
 * يتدهور بأمان (null) عند غياب المفتاح أو فشل المزوّد.
 */
class GroqSpeechToText implements SpeechToTextContract
{
    public function transcribe(string $audioContents, string $filename, ?string $language = null): ?string
    {
        $apiKey = $this->resolveKey();
        if ($apiKey === '' || $audioContents === '') {
            if ($apiKey === '') {
                Log::warning('Speech-to-text: Groq API key is missing.');
            }

            return null;
        }

        $base = rtrim((string) $this->cfg('base_url', 'https://api.groq.com/openai/v1'), '/');
        $language ??= (string) config('services.ai.speech.language', 'ar');

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout((int) $this->cfg('connect_timeout', 15))
                ->timeout((int) $this->cfg('timeout', 60))
                ->attach('file', $audioContents, $filename !== '' ? $filename : 'audio.webm')
                ->post("{$base}/audio/transcriptions", array_filter([
                    'model' => (string) $this->cfg('model', 'whisper-large-v3'),
                    'language' => $language !== '' ? $language : null,
                    'response_format' => 'json',
                    'temperature' => '0',
                ]));

            if ($response->successful()) {
                $text = $response->json('text');

                return is_string($text) && trim($text) !== '' ? trim($text) : null;
            }

            Log::warning('Speech-to-text: Groq API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);
        } catch (Exception $e) {
            Log::error('Speech-to-text: Groq connection error: '.$e->getMessage());
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return (bool) config('services.ai.speech.enabled', true) && $this->resolveKey() !== '';
    }

    private function resolveKey(): string
    {
        $key = $this->cfg('key')
            ?: config('services.ai.providers.groq.key');

        return is_string($key) ? trim($key) : '';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.ai.speech.providers.groq.{$key}", $default);
    }
}
