@extends('layouts.app')
@section('layout', 'wizard')

@section('content')
<section class="consultation-shell" aria-labelledby="consultation-title">
    <p class="eyebrow">الاستشارة التسويقية الذكية</p>
    <h1 id="consultation-title">{{ $consultation['project']['name'] }}</h1>
    <p class="muted">{{ $consultation['progress']['label'] }}</p>

    <div class="consultation-progress" role="progressbar" aria-valuenow="{{ $consultation['progress']['percent'] }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $consultation['progress']['percent'] }}%"></span>
    </div>

    @if ($consultation['status'] === 'active' && $consultation['question'])
        <article class="card consultation-question">
            <h2>{{ $consultation['question']['text'] }}</h2>
            @if ($consultation['question']['why']) <p><strong>لماذا نسأل؟</strong> {{ $consultation['question']['why'] }}</p> @endif
            @if ($consultation['question']['help']) <p class="muted">{{ $consultation['question']['help'] }}</p> @endif

            <form method="POST" action="{{ route('app.consultations.answer', $consultation['uuid']) }}">
                @csrf
                @include('app.consultations._answer-field', ['question' => $consultation['question'], 'current' => null])
                <div class="actions">
                    <button class="btn btn--primary" type="submit">احفظ وتابع</button>
                    @if ($consultation['question']['allow_unknown'])
                        <button class="btn btn--ghost" type="submit" name="unknown" value="1" formnovalidate>لا أعرف</button>
                    @endif
                </div>
            </form>
        </article>
    @elseif ($consultation['status'] === 'review')
        <article class="card">
            <h2>راجع ما فهمناه</h2>
            <p>اكتملت المعلومات الأساسية. صحّح أي تعارض قبل بدء التحليل.</p>

            @foreach (['facts' => 'حقائق صرّحت بها', 'estimates' => 'تقديرات تحتاج تحققًا', 'unknowns' => 'معلومات غير متاحة', 'assumptions' => 'افتراضات معلنة'] as $group => $title)
                <section class="consultation-review-group">
                    <h3>{{ $title }}</h3>
                    @forelse ($consultation['review'][$group] as $item)
                        <p><strong>{{ $item['label'] ?? $item['statement'] ?? $item['key'] }}</strong>@isset($item['value']) — {{ is_array($item['value']) ? implode('، ', $item['value']) : $item['value'] }} @endisset</p>
                        @if(isset($item['question_key']))
                            <details class="consultation-revise"><summary>صحّح الإجابة</summary>
                                <form method="POST" action="{{ route('app.consultations.answers.update', [$consultation['uuid'], $item['question_key']]) }}">@csrf @method('PUT')
                                    @include('app.consultations._answer-field', ['question' => $item, 'current' => $item['value'] ?? null])
                                    <button class="btn btn--secondary">احفظ التصحيح</button>
                                    @if($item['allow_unknown'])<button class="btn btn--ghost" name="unknown" value="1" formnovalidate>لا أعرف</button>@endif
                                </form>
                            </details>
                        @endif
                    @empty
                        <p class="muted">لا توجد عناصر.</p>
                    @endforelse
                </section>
            @endforeach

            @if ($consultation['conflicts'])
                <section class="consultation-conflicts" aria-labelledby="conflicts-title">
                    <h3 id="conflicts-title">تعارضات تحتاج توضيحك</h3>
                    @foreach ($consultation['conflicts'] as $conflict)
                        <form method="POST" action="{{ route('app.consultations.conflicts.resolve', [$consultation['uuid'], $conflict['id']]) }}" class="card card--warning">
                            @csrf
                            <p>{{ $conflict['message'] }}</p>
                            <label>ما التفسير الصحيح؟ <textarea name="resolution" required minlength="5" maxlength="1000"></textarea></label>
                            <button class="btn btn--secondary" type="submit">احفظ التوضيح</button>
                        </form>
                    @endforeach
                </section>
            @endif
            <form method="POST" action="{{ route('app.consultations.confirm', $consultation['uuid']) }}">
                @csrf
                <button type="submit" class="btn btn--primary" @disabled(!$consultation['can_confirm'])>أكد وابدأ التحليل الشامل</button>
            </form>
        </article>
    @else
        <article class="card consultation-status" aria-live="polite">
            <h2>{{ $consultation['status'] === 'completed' ? 'اكتمل التقرير' : ($consultation['status'] === 'failed' ? 'تعذر التحليل' : 'نبني تقريرك الآن') }}</h2>
            <p>{{ $consultation['status_message'] }}</p>
            @if ($consultation['report_uuid'])
                <a class="btn btn--primary" href="{{ route('app.agency-reports.show', $consultation['report_uuid']) }}">افتح التقرير الموحد</a>
            @elseif ($consultation['status'] === 'analysis_queued')
                <meta http-equiv="refresh" content="8">
                <p class="muted">تُحدّث الصفحة تلقائيًا كل بضع ثوانٍ.</p>
            @elseif ($consultation['status'] === 'failed')
                <form method="POST" action="{{ route('app.consultations.retry', $consultation['uuid']) }}">@csrf<button class="btn btn--primary">أعد محاولة التحليل</button></form>
            @endif
        </article>
    @endif

    <aside class="card consultation-evidence">
        <h2>ملفات وأدلة</h2>
        <p class="muted">PDF أو مستند أو جدول أو صورة، بحد أقصى 10 ميجابايت. لا يُحلل أي ملف دون موافقتك في السؤال الخاص بالمصادر.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('app.consultations.evidence.store', $consultation['uuid']) }}">@csrf
            <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp">
            <button class="btn btn--secondary">ارفع دليلًا</button>
        </form>
        @foreach($consultation['evidence'] as $item)
            <div class="consultation-file"><span>{{ $item['name'] }}</span><form method="POST" action="{{ route('app.consultations.evidence.destroy', [$consultation['uuid'], $item['id']]) }}">@csrf @method('DELETE')<button class="link-button">حذف</button></form></div>
        @endforeach
    </aside>

    <footer class="consultation-data-actions">
        <a href="{{ route('app.consultations.export', $consultation['uuid']) }}">نزّل بيانات الاستشارة</a>
        <form method="POST" action="{{ route('app.consultations.destroy', $consultation['uuid']) }}" onsubmit="return confirm('حذف بيانات الاستشارة؟ سيبقى المشروع وأي تقرير منشور.')">
            @csrf @method('DELETE')
            <button type="submit" class="link-button">حذف بيانات الاستشارة</button>
        </form>
    </footer>
</section>
@endsection
