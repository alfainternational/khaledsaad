@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'الزيارات')

@push('head')
    {{-- أنماط الإحصاءات خارج حزمة Vite: تُنشر مع الوحدة بلا انتظار بناء أصول. --}}
    <link rel='stylesheet' href='{{ asset('css/insights.css') }}?v={{ @filemtime(public_path('css/insights.css')) ?: 1 }}'>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">إحصاءات الزوّار</p>
            <h1>الزيارات</h1>
            {{--
                السطر الواحد خلف كل رقم مجمَّع.

                الإجماليات تقول ما يفعله «الزوّار»، وهذه الصفحة تقول ما فعله
                زائرٌ بعينه — وهو ما يُقرأ حين يصلك سؤال من عميل محتمل.
            --}}
            <p class="muted"><span class="live-dot" aria-hidden="true"></span>{{ $live_now }} على الموقع الآن (نشاط خلال 5 دقائق).</p>
        </div>
    </header>

    @include('admin.insights.partials.nav')

    {{--
        شريط التصفية بأنماط الوحدة وحدها (`public/css/insights.css`).

        أول نشر خرج منهارًا لأن القالب استعمل كلاسات لا يقابلها نمط. والاتّكاء
        على أنماط اللوحة العامة ليس علاجًا: حزمة `app.css` تختلف بين المحلي
        والإنتاج، فما يُتحقّق منه هنا لا يصف ما سيظهر هناك.
    --}}
    <form method="GET" class="insights-filters" role="search">
        <div class="insights-filters__field insights-filters__field--grow">
            <label class="insights-filters__label" for="insights-q">بحث</label>
            <input id="insights-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="مسار، مُحيل، حملة، أو معرّف زائر">
        </div>

        <div class="insights-filters__field">
            <label class="insights-filters__label" for="insights-channel">القناة</label>
            <select id="insights-channel" name="channel">
                <option value="">كل القنوات</option>
                @foreach ($channels as $key => $label)
                    <option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <label class="insights-filters__check">
            <input type="checkbox" name="converted" value="1" @checked($filters['converted'])>
            <span>حوّلت فقط</span>
        </label>

        <label class="insights-filters__check">
            <input type="checkbox" name="live" value="1" @checked($filters['live'])>
            <span>نشطة الآن</span>
        </label>

        <div class="insights-filters__actions">
            <button type="submit" class="btn btn--primary btn--sm">تصفية</button>
            <a href="{{ route('admin.insights.visitors') }}" class="btn btn--ghost btn--sm">مسح</a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="table" data-table="matrix">
            <thead>
                <tr>
                    <th>الوقت</th>
                    <th>الزائر</th>
                    <th>المصدر</th>
                    <th>الدخول ← الخروج</th>
                    <th>الصفحات</th>
                    <th>البقاء</th>
                    <th>الجهاز</th>
                    <th>البلد</th>
                    <th>النتيجة</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>
                            <a href="{{ route('admin.insights.session', $session->uuid) }}">{{ $session->started_at->format('m-d H:i') }}</a>
                            @if ($session->last_activity_at->gt(now()->subMinutes(5)))
                                <span class="live-dot" aria-hidden="true" title="نشط الآن"></span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.insights.visitor', $session->visitor_id) }}">
                                {{ $session->user?->name ?? 'زائر '.substr($session->visitor_id, 0, 6) }}
                            </a>
                            <br>
                            <small class="muted">{{ $session->is_returning ? 'عائد' : 'جديد' }}</small>
                        </td>
                        <td>
                            {{ $channels[$session->channel] ?? $session->channel }}
                            @if ($session->platform)
                                <br><small class="muted">{{ $session->platform }}</small>
                            @elseif ($session->referrer_host)
                                <br><small class="muted">{{ $session->referrer_host }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="path-cell" title="{{ $session->entry_path }}">{{ $session->entry_path }}</span>
                            @if ($session->exit_path && $session->exit_path !== $session->entry_path)
                                <br><span class="path-cell muted" title="{{ $session->exit_path }}">← {{ $session->exit_path }}</span>
                            @endif
                        </td>
                        <td>{{ $session->page_views_count }}</td>
                        <td>{{ $session->durationForHumans() }}</td>
                        <td>{{ $session->device_type }}<br><small class="muted">{{ $session->browser ?? '—' }}</small></td>
                        <td>{{ \App\Modules\Insights\LocationInference::countryName($session->country) }}</td>
                        <td>
                            @if ($session->conversion_name)
                                {{ config('insights.conversion_events')[$session->conversion_name] ?? $session->conversion_name }}
                            @elseif ($session->is_bounce)
                                <span class="muted">ارتداد</span>
                            @else
                                <span class="muted">تصفّح</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">لا زيارات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{--
        ترقيم المنصة الموحّد (`vendor/pagination/tailwind.blade.php`).

        كان هنا ترقيم خاص بهذه الصفحة وحدها لأن قالب Laravel الافتراضي
        يخرج بلا شكل. عولج الأصل بدل ترقيعه هنا، فصارت الصفحات الستّ
        المُرقَّمة في المنصة تعرض الشريط نفسه — وواحدٌ يُصان أسهل من ستّة.
    --}}
    {{ $sessions->links() }}
@endsection
