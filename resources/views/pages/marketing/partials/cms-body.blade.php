@props(['html'])

@if($html)
    <div class="marketing-cms-body text-body leading-relaxed space-y-4">
        {!! $html !!}
    </div>
@endif
