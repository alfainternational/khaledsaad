{{--
    ترقيم المنصة — عربي RTL، ومعه أساس الرقم.

    الاسم `tailwind` ليس اختيارًا: هو القالب الذي يحلّه Laravel فعلًا
    (`Paginator::$defaultView`)، فتجاوزه هنا يسري على كل `->links()` في
    المنصة بلا سطر PHP واحد ولا تعديل مزوّد.

    ما كان قبله: قالب Laravel الافتراضي يُخرج كلاسات Tailwind
    (`sm:flex-1`، `dark:bg-gray-800`، `focus:ring`…) لا يقابلها نمط في
    هذا المشروع — خمس وثلاثون منها — فيظهر شريط الترقيم مكدَّسًا بلا شكل
    في ستّ صفحات: المدوّنة وفهارس المحتوى والمشتركين والوسائط والتصنيفات
    وسجلّ الزيارات.

    ومعه العدّ لا الأسهم وحدها (§١٣): «41–80 من 312» تقول للقارئ أين هو
    من الكل، والأسهم وحدها تتركه يعدّ النقرات.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="تنقّل بين الصفحات">
        <span class="pager__count">
            {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
            من {{ number_format($paginator->total()) }}
        </span>

        <span class="pager__links">
            @if ($paginator->onFirstPage())
                <span class="pager__link is-disabled" aria-disabled="true">السابق</span>
            @else
                <a class="pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a>
            @endif

            {{-- أرقام الصفحات تظهر حين تكون قليلة؛ وإلا فالموضع نصًّا،
                 لأن ثلاثين رقمًا في سطر واحد تُقرأ ككتلة لا كتنقّل. --}}
            @if ($paginator->lastPage() <= 9)
                @foreach ($elements ?? [] as $element)
                    @if (is_string($element))
                        <span class="pager__gap">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pager__link is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager__link" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <span class="pager__page">صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span>
            @endif

            @if ($paginator->hasMorePages())
                <a class="pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a>
            @else
                <span class="pager__link is-disabled" aria-disabled="true">التالي</span>
            @endif
        </span>
    </nav>
@endif
