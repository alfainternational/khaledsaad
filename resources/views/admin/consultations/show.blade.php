@extends('layouts.app')
@section('layout', 'detail')
@section('content')
<section class="page-head"><p class="eyebrow">{{ $version->status === 'draft' ? 'مسودة قابلة للتحرير' : 'إصدار منشور مقفل' }}</p><h1>{{ $version->blueprint->name }} — {{ $version->version }}</h1></section>
<div class="actions"><a class="btn btn--secondary" href="{{ route('admin.consultations.simulate', $version) }}">حاكِ النطاق</a>@if($version->status === 'draft')<form method="POST" action="{{ route('admin.consultations.publish', $version) }}" data-confirm="هل تريد نشر هذا الإصدار وقفله؟ لن تتمكن من تعديل الأسئلة المنشورة بعد ذلك.">@csrf<button class="btn btn--primary">تحقق وانشر</button></form>@endif</div>
@foreach($version->modules as $module)
<details class="card" @if($loop->first) open @endif>
    <summary><strong>{{ $module->module->name }}</strong> · {{ $module->importance }} · {{ $module->questions->count() }} سؤال</summary>
    @forelse($module->questions as $binding)
        @php($question = $binding->questionVersion)
        <article class="admin-question">
            <p><code>{{ $question->definition->key }}</code> · الأثر {{ $binding->diagnostic_impact }}/5 · {{ $question->answer_type }}</p>
            @if($version->status === 'draft')
                <form method="POST" action="{{ route('admin.consultations.questions.update', [$version, $question]) }}">@csrf @method('PUT')
                    <label>نص السؤال<textarea name="user_text" required>{{ $question->user_text }}</textarea></label>
                    <label>المساعدة<textarea name="help_text">{{ $question->help_text }}</textarea></label>
                    <label>لماذا نسأل؟<textarea name="why_text">{{ $question->why_text }}</textarea></label>
                    <label><input type="checkbox" name="required" value="1" @checked($question->required)> إلزامي</label>
                    <label><input type="checkbox" name="allow_unknown" value="1" @checked($question->allow_unknown)> يسمح بلا أعرف</label>
                    <label><input type="checkbox" name="allow_skip" value="1" @checked($question->allow_skip)> يسمح بالتخطي</label>
                    <button class="btn btn--secondary">حفظ</button>
                </form>
            @else
                <h3>{{ $question->user_text }}</h3><p>{{ $question->why_text }}</p>
            @endif
        </article>
    @empty<p class="muted">لا أسئلة مباشرة؛ تُستخدم الوحدة لتفسير نطاق التشخيص.</p>@endforelse
</details>
@endforeach
@endsection
