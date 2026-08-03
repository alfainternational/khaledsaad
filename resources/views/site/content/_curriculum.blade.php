<section class="course-curriculum" aria-labelledby="course-curriculum-title">
    <h2 id="course-curriculum-title">منهج الدورة</h2>
    @forelse ($content->sections as $section)
        <section class="course-section">
            <h3>{{ $section->position }}. {{ $section->title }}</h3>
            @if ($section->description) <p>{{ $section->description }}</p> @endif
            <ol>
                @foreach ($section->items->filter->isPublished() as $item)
                    <li>
                        <a href="{{ route('content.show', $item) }}">
                            <span>{{ $item->title }}</span>
                            <small>{{ $item->type === 'lesson' ? 'درس' : 'محاضرة' }}@if($item->duration_minutes) · {{ $item->duration_minutes }} دقيقة @endif</small>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <p>سيُضاف منهج الدورة قريبًا.</p>
    @endforelse
</section>
