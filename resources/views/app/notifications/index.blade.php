@extends('layouts.app')
@section('layout', 'index')

@section('title', 'الإشعارات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإشعارات</p>
            <h1>آخر التحديثات التي تهمك</h1>
        </div>
        @if ($notifications->contains(fn ($n) => ! $n['read']))
            <form method="POST" action="{{ route('app.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm">علّم الكل كمقروء</button>
            </form>
        @endif
    </header>

    {{-- بند ٣٢: تنبيهات نظام المتصفح بإذن صريح — تكمل البريد ولا تستبدله --}}
    <p>
        <button type="button" class="btn btn--ghost btn--sm" data-browser-notifications>
            فعّل تنبيهات المتصفح على هذا الجهاز
        </button>
    </p>

    @php($activeGroup = request()->query('group', ''))
    <nav class="filter-chips" aria-label="تصفية التنبيهات">
        @foreach (['' => __('الكل'), 'watch' => __('المتابعة والمنافسون'), 'reports' => __('تقاريري'), 'tasks' => __('المهام'), 'billing' => __('الرصيد')] as $key => $label)
            <a href="{{ route('app.notifications.index', $key === '' ? [] : ['group' => $key]) }}"
                @class(['filter-chip', 'is-active' => $activeGroup === $key])
                @if ($activeGroup === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($notifications->isEmpty())
        <section class="empty">
            <h2>لا إشعارات بعد</h2>
            <p>ستظهر هنا تحديثات التقارير والمهام والرصيد عندما تحتاج إلى اطلاع أو إجراء.</p>
        </section>
    @else
        <ul class="list">
            @foreach ($notifications as $notification)
                <li @class(['list__item', 'notification', 'notification--unread' => ! $notification['read']])>
                    <div class="notification__body">
                        <strong>{{ $notification['title'] }}</strong>
                        <span class="muted">{{ $notification['body'] }}</span>
                        <time class="muted">{{ $notification['at'] }}</time>
                    </div>

                    @if ($notification['url'])
                        <form method="POST" action="{{ route('app.notifications.read', $notification['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--sm">عرض التفاصيل</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
