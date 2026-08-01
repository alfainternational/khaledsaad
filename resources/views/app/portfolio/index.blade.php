@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'محفظة الأنشطة | خالد سعد')

@section('content')
    <header class="page-head">
        <h1>محفظة الأنشطة</h1>
        <p class="muted">درجة كل نشاط واتجاهه، مرتّبة بالأحوج إلى تدخّلك أولًا.</p>
    </header>

    <section class="card">
        <dl class="kv">
            <div>
                <dt>أنشطة مقيسة</dt>
                <dd>{{ $portfolio['summary']['measured'] }} من {{ $portfolio['summary']['total'] }}</dd>
            </div>
            <div>
                <dt>متوسط الدرجة</dt>
                <dd>
                    @if ($portfolio['summary']['average_score'] === null)
                        <span class="muted">لا نشاط مقيس بعد</span>
                    @else
                        {{-- المتوسط على المقيس وحده، ومعه عدده: متوسط يشمل غير المقيس كذبٌ بالحساب. --}}
                        {{ $portfolio['summary']['average_score'] }}/100
                        <span class="muted">من {{ $portfolio['summary']['measured'] }} نشاطًا</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt>أنشطة تتراجع</dt>
                <dd>{{ $portfolio['summary']['declining'] }}</dd>
            </div>
        </dl>
    </section>

    <section class="card">
        <h2>الأنشطة</h2>

        @if ($portfolio['projects'] === [])
            <p class="muted">لا أنشطة في هذه المساحة بعد.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>النشاط</th>
                        <th>الدرجة</th>
                        <th>التغطية</th>
                        <th>الاتجاه</th>
                        <th>آخر قياس</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($portfolio['projects'] as $row)
                    <tr>
                        <td>
                            <a href="{{ route('app.readiness.show', $row['project']['id']) }}">{{ $row['project']['name'] }}</a>
                            <div class="muted">{{ $row['sector_display'] }}</div>
                        </td>
                        <td>
                            {{-- غير المقيس لا يُعرض صفرًا: الصفر حكم، والغياب إقرار (§٤.٣). --}}
                            @if (! $row['measured'])
                                <span class="muted">لم يُقَس</span>
                            @else
                                <strong>{{ $row['maturity_score'] }}</strong>/100
                            @endif
                        </td>
                        <td>
                            {{ $row['axes_active'] }} من {{ $row['axes_total'] }} محاور
                        </td>
                        <td>
                            @if ($row['trend']['direction'] === 'up')
                                <span class="tag tag--measured">+{{ $row['trend']['delta'] }}</span>
                            @elseif ($row['trend']['direction'] === 'down')
                                <span class="tag tag--assumption">{{ $row['trend']['delta'] }}</span>
                            @elseif ($row['trend']['direction'] === 'flat')
                                <span class="muted">بلا تغيّر</span>
                            @else
                                <span class="muted">{{ $row['trend']['reason'] }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($row['last_measured_at'])
                                {{ \Illuminate\Support\Carbon::parse($row['last_measured_at'])->diffForHumans() }}
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
