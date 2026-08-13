@props(['traces' => []])
@if (! empty($traces))
    <section {{ $attributes->class('human-traces') }} aria-label="سجل المراجعة البشرية">
        <strong>سجل المراجعة</strong>
        <ul>
            @foreach ($traces as $trace)
                <li>{{ $trace['body'] }}</li>
            @endforeach
        </ul>
    </section>
@endif
