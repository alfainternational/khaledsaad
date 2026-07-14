@extends('layouts.app', ['title' => 'مقابلة التعريف', 'pageTitle' => 'مقابلة التعريف', 'pageKicker' => 'أساس مشروعك'])

@section('content')
<article class="card panel-modern">
    <header class="mb-4">
        <h2 class="text-xl font-bold">لنعرف مشروعك في دقائق</h2>
        <p class="text-sm tool-voice-hint">
            أجب على هذه الأسئلة القليلة بلغتك، كتابةً أو صوتاً. سنحفظها كأساس لمشروعك،
            وستقترحها الأدوات عليك تلقائياً بدل أن تعيد كتابتها في كل أداة.
        </p>
    </header>

    @if ($projects->isEmpty())
        <p class="app-empty">أنشئ مشروعاً أولاً لتبدأ مقابلة التعريف.</p>
    @else
        <form
            method="POST"
            action="{{ route('interview.store') }}"
            class="app-form-grid"
            data-interview-form
            data-interview-transcribe-url="{{ route('interview.transcribe') }}"
        >
            @csrf

            <label class="app-field">
                <span>المشروع</span>
                <select class="app-input" name="project_id">
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($currentProject && $currentProject->id === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>

            @error('answers')
                <div class="app-alert app-alert-error">{{ $message }}</div>
            @enderror

            @foreach ($questions as $question)
                @php($key = $question['key'])
                <div class="tool-field-wrap" data-field-wrap="{{ $key }}">
                    <label class="app-field">
                        <span class="tool-field-label-row">
                            <span>{{ $question['label'] }}</span>
                        </span>
                        <textarea
                            class="app-input"
                            name="answers[{{ $key }}]"
                            rows="3"
                            placeholder="{{ $question['placeholder'] }}"
                            data-interview-answer="{{ $key }}"
                        >{{ old('answers.'.$key, $existing[$key] ?? '') }}</textarea>
                    </label>
                    <div class="tool-field-context">{{ $question['hint'] }}</div>
                    @if (! empty($voiceEnabled))
                        <div class="tool-voice-input-head">
                            <button type="button" class="btn btn-secondary btn-sm tool-voice-btn" data-interview-voice="{{ $key }}" aria-pressed="false">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4"/></svg>
                                <span data-voice-label>تكلّم</span>
                            </button>
                            <span class="tool-voice-status" data-voice-status="{{ $key }}" role="status" aria-live="polite"></span>
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="app-inline-actions">
                <button type="submit" class="btn btn-primary">احفظ أساس مشروعي</button>
            </div>
        </form>
    @endif
</article>
@endsection
