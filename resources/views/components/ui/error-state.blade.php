{{--
    شاشة الفشل الوحيدة في المنتج.

    تجيب على الأسئلة الأربعة بترتيبها: ماذا حدث (`title`)، ولماذا وماذا
    كلّفني (`message`)، وماذا أفعل الآن (`action`). كتابة شاشة فشل
    ارتجالية بجانبها هو ما أنتج أصلًا شاشةً بأحد عشر سطرًا متطابقًا
    تتّهم رصيد المستخدم بعطلٍ لدينا.
--}}
@props([
    'title',
    'message' => null,
    'kind' => 'ours',
    'retry' => null,
    'secondary' => null,
])

<section {{ $attributes->class(['ui-error-state', 'ui-error-state--'.$kind]) }} role="alert">
    <h2 class="section-title">{{ $title }}</h2>

    @if ($message)
        <p class="ui-error-state__message">{{ $message }}</p>
    @endif

    <div class="ui-error-state__actions">
        {{-- عطلٌ لدينا لا يُطلب فيه من المستخدم إجراء (INV-8): يبقى له
             زرّ إعادة المحاولة وحده، وهو تعجيلٌ لما نفعله نحن أصلًا. --}}
        @if ($retry)
            <form method="POST" action="{{ $retry }}">
                @csrf
                <button type="submit" class="btn btn--primary">{{ __('أعد المحاولة الآن') }}</button>
            </form>
        @endif

        {{ $slot }}

        @if ($secondary)
            {{ $secondary }}
        @endif
    </div>
</section>
