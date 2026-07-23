<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Tool;
use Illuminate\Http\Request;

/**
 * نية البدء: الأداة التي اختارها الزائر قبل امتلاكه حسابًا.
 *
 * تُحمل عبر ?tool= ثم تُحفظ في الجلسة، حتى تنتهي رحلة (أداة → تسجيل → مشروع)
 * بتشغيل الأداة نفسها التي بدأ منها، لا بلوحة فارغة.
 */
trait CarriesStartIntent
{
    private const SESSION_KEY = 'start_tool';

    /**
     * يقرأ النية من الرابط أو الجلسة، ويحفظها للخطوة التالية.
     */
    protected function rememberStartIntent(Request $request): ?Tool
    {
        $key = $request->query('tool') ?? $request->input('start_tool') ?? $request->session()->get(self::SESSION_KEY);

        if (! is_string($key) || $key === '') {
            return null;
        }

        $tool = Tool::runnable()->with('currentVersion')->where('key', $key)->first();

        if ($tool === null) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $request->session()->put(self::SESSION_KEY, $tool->key);

        return $tool;
    }

    /**
     * يقرأ النية ويمسحها — تُستدعى عند استهلاكها فعليًا.
     */
    protected function consumeStartIntent(Request $request): ?Tool
    {
        $tool = $this->rememberStartIntent($request);
        $request->session()->forget(self::SESSION_KEY);

        return $tool;
    }

    protected function forgetStartIntent(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
