@if ($learning['enabled'] && count($learning['outline']) && ($variant ?? 'desktop') === 'mobile')
    <details class="learning-outline learning-outline--mobile">
        <summary>في هذا الدرس <span aria-hidden="true">⌄</span></summary>
        <ol>
            @foreach ($learning['outline'] as $item)
                <li><a href="#{{ $item['id'] }}" data-outline-link>{{ $item['title'] }}</a></li>
            @endforeach
        </ol>
    </details>

@elseif ($learning['enabled'] && count($learning['outline']))
    <nav class="learning-outline learning-outline--desktop" aria-labelledby="learning-outline-title">
        <p class="eyebrow">خريطة القراءة</p>
        <h2 id="learning-outline-title">في هذا الدرس</h2>
        <ol>
            @foreach ($learning['outline'] as $item)
                <li><a href="#{{ $item['id'] }}" data-outline-link>{{ $item['title'] }}</a></li>
            @endforeach
        </ol>
    </nav>
@endif
