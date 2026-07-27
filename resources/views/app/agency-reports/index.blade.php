@extends('layouts.app')

@section('title', 'موجز الوكالة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $project->name }}</p>
            <h1>موجز الوكالة الموحّد</h1>
            <p class="muted">نسخة ثابتة تجمع أحدث تشخيصات مشروعك، لتشرح المطلوب وتقارن عروض الوكالات على أساس واحد.</p>
        </div>
    </header>

    {{-- تحقق الميزانية قبل أي شيء: يغيّر قرار المستخدم لا يزيّن الصفحة. --}}
    @if ($budget['verdict']['level'] !== 'sufficient')
        <section class="card card--warn">
            <p class="eyebrow">قبل أن تراسل وكالة</p>
            <h2 class="section-title">{{ $budget['verdict']['headline'] }}</h2>
            <p>{{ $budget['verdict']['detail'] }}</p>
            @if (! empty($budget['verdict']['gap']))
                <p class="muted">
                    الفارق المطلوب تقريبًا {{ number_format((float) $budget['verdict']['gap']) }}
                    {{ $budget['market']['currency_label'] }} شهريًا — التفصيل الكامل داخل أي إصدار تنشئه.
                </p>
            @endif
        </section>
    @endif

    {{-- موجز التكليف: ما تسأل عنه الوكالة ولا تنتجه الأدوات التشخيصية. --}}
    {{-- أمر واحد: يشغّل الأدوات كلها بما تعرفه المنصة، ويبني المستند تلقائيًا. --}}
    <section class="card">
        <h2 class="section-title">أنشئ صورة شاملة عن مشروعك</h2>
        <p class="muted">
            يبدأ {{ $sweep['tool_count'] }} تشخيصات اعتمادًا على إجاباتك المحفوظة،
            ثم يجمع نتائجها في المستند الموحّد عند اكتمالها.
        </p>

        @if ($sweep['needs_warning'])
            <p class="evidence"><b>قبل أن تبدأ:</b> {{ $sweep['warning'] }}</p>
        @else
            <p class="muted">تغطية الأسئلة الإلزامية الحالية: {{ $sweep['coverage_percent'] }}٪.</p>
        @endif

        <details>
            <summary>ما مدى اكتمال المعلومات لكل تشخيص؟</summary>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>التشخيص</th><th>التغطية</th><th>أبرز ما ينقص</th></tr></thead>
                    <tbody>
                        @foreach ($sweep['tools'] as $tool)
                            <tr>
                                <td>{{ $tool['title'] }}</td>
                                <td>{{ $tool['percent'] }}٪</td>
                                <td class="muted">{{ implode('، ', $tool['missing']) ?: 'مكتملة' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <form method="POST" action="{{ route('app.projects.full-diagnosis', $project) }}" class="form form--inline">
            @csrf
            <label class="field">
                <span class="field__label">طريقة التحليل</span>
                <select name="mode">
                    <option value="auto">تحليل آلي فوري</option>
                    <option value="manual">مراجعة بشرية</option>
                </select>
            </label>
            <button type="submit" class="btn btn--primary">ابدأ التشخيص الشامل</button>
        </form>

        @if (session('sweep') && session('sweep')['skipped'] !== [])
            <h3>تعذّر بدء بعض التشخيصات</h3>
            <ul class="bullets">
                @foreach (session('sweep')['skipped'] as $skipped)
                    <li>{{ $skipped['title'] }} — {{ $skipped['reason'] }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2 class="section-title">موجز التكليف</h2>
        <p class="muted">
            التشخيصات تصف حالة مشروعك، وهذه الأسئلة تحدد ما تطلبه من الوكالة.
            أُجيب {{ $briefCompleteness['answered'] }} من {{ $briefCompleteness['total'] }}
            ({{ $briefCompleteness['percent'] }}٪).
        </p>

        @unless ($briefCompleteness['is_quotable'])
            <p class="evidence">
                <b>ينقص ما لا تستطيع وكالة التسعير بدونه:</b>
                {{ implode('، ', $briefCompleteness['missing_critical']) }}.
            </p>
        @endunless

        <form method="POST" action="{{ route('app.projects.agency-reports.brief', $project) }}" class="form form--wide">
            @csrf

            @foreach ($briefGroups as $group)
                <fieldset class="field">
                    <legend class="field__label">{{ $group['title'] }}</legend>
                    <p class="field__help">{{ $group['intent'] }}</p>

                    @foreach ($group['fields'] as $field)
                        @php($current = $field['key'] === 'services' ? $services : ($brief[$field['key']] ?? null))

                        <label class="field">
                            <span class="field__label">
                                {{ $field['label'] }}
                                @if (! empty($field['critical']))<span class="badge">أساسي</span>@endif
                            </span>

                            @if ($field['type'] === 'multiselect')
                                <span class="field__help">{{ $field['why'] }}</span>
                                <span class="choice-grid">
                                    @foreach ($field['options'] as $value => $label)
                                        <label class="field field--inline">
                                            <input type="checkbox" name="brief[{{ $field['key'] }}][]" value="{{ $value }}"
                                                @checked(in_array($value, (array) $current, true))>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </span>
                            @elseif ($field['type'] === 'bool' || $field['type'] === 'select')
                                <select name="brief[{{ $field['key'] }}]">
                                    <option value="">— لم أحدد بعد —</option>
                                    @foreach ($field['options'] as $value => $label)
                                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="field__help">{{ $field['why'] }}</span>
                            @elseif ($field['type'] === 'textarea')
                                <textarea name="brief[{{ $field['key'] }}]" rows="3"
                                    placeholder="{{ $field['placeholder'] ?? '' }}">{{ $current }}</textarea>
                                <span class="field__help">{{ $field['why'] }}</span>
                            @else
                                <input type="text" name="brief[{{ $field['key'] }}]" value="{{ $current }}"
                                    placeholder="{{ $field['placeholder'] ?? '' }}">
                                <span class="field__help">{{ $field['why'] }}</span>
                            @endif
                        </label>
                    @endforeach
                </fieldset>
            @endforeach

            <button type="submit" class="btn btn--primary">احفظ موجز التكليف</button>
        </form>
    </section>

    <section class="card">
        @if ($readiness['can_generate'])
            <h2 class="section-title">جاهز لإنشاء موجز الوكالة</h2>
            <p class="muted">سيُضمّن {{ $readiness['included_count'] }} تقارير، بأحدث نتيجة صالحة من كل تشخيص:</p>
            <ul class="bullets">
                @foreach ($readiness['included_tools'] as $tool)
                    <li>
                        {{ $tool['title'] }}
                        <span class="muted">
                            @if ($tool['scored'])
                                — {{ $tool['score'] }}/100
                            @else
                                — تشخيص وصفي يُضمّن بمحتواه من دون درجة رقمية
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('app.projects.agency-reports.store', $project) }}" class="form form--wide">
                @csrf
                <div class="field-row">
                    @foreach ([
                        'budget' => 'الميزانية',
                        'competitors' => 'المنافسون',
                        'evidence' => 'الأدلة التفصيلية',
                    ] as $key => $label)
                        <label class="field">
                            <span class="field__label">{{ $label }}</span>
                            <select name="visibility[{{ $key }}]">
                                <option value="full">تظهر كاملة للوكالة</option>
                                <option value="summary">تظهر كملخص</option>
                                <option value="private">داخلية لا تدخل النسخة</option>
                            </select>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn--primary">أنشئ إصدارًا جديدًا ثابتًا</button>
            </form>
        @else
            <h2 class="section-title">أكمل الأساس أولًا</h2>
            <p class="muted">حتى يكون التقرير مفيدًا للوكالة لا مجرد تجميع صفحات، أكمل التشخيصات التالية:</p>
            <ul class="bullets">
                @foreach ($readiness['missing_core'] as $tool)
                    <li><a href="{{ route('app.tools.show', $tool['key']) }}">{{ $tool['title'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($reports->isNotEmpty())
        <section>
            <h2 class="section-title">الإصدارات السابقة</h2>
            <div class="card-grid">
                @foreach ($reports as $report)
                    <a class="card card--link" href="{{ route('app.agency-reports.show', $report) }}">
                        <span class="eyebrow">الإصدار {{ $report->version }}</span>
                        <strong>{{ $report->title }}</strong>
                        <span class="muted">{{ $report->generated_at?->locale('ar')->translatedFormat('j F Y') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
