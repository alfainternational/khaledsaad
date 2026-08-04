@if ($learning['enabled'])
    <nav class="learning-adjacent" aria-label="التنقل بين دروس السلسلة">
        @if ($learning['previous'])
            <a href="{{ route('content.show', $learning['previous']) }}" rel="prev">
                <small>الدرس السابق</small>
                <strong>{{ $learning['previous']->title }}</strong>
            </a>
        @else
            <span></span>
        @endif
        @if ($learning['next'])
            <a href="{{ route('content.show', $learning['next']) }}" rel="next">
                <small>الدرس التالي</small>
                <strong>{{ $learning['next']->title }}</strong>
            </a>
        @endif
    </nav>
@endif
