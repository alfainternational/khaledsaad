{{--
    مبدّل اللغة.

    رابط لا زرّ نموذج: الرابط يُشارَك ويُفهرَس ويعمل بلا جافاسكربت، والزرّ
    لا يفعل شيئًا من هذا. و`?lang=` يبقى في الرابط فتصل الصفحة إلى من
    تُرسَل له باللغة نفسها التي رآها المُرسِل.

    يظهر فقط حين تكون هناك لغة ثانية مفعّلة — سطر واجهة لخيار واحد ضجيج.
--}}
@php($switcherLocales = $appLocales->switcher())

@if (count($switcherLocales) > 1)
    <div class="language-switcher" role="group" aria-label="اللغة">
        @foreach ($switcherLocales as $option)
            <a
                class="language-switcher__option @if ($option['current']) is-current @endif"
                href="{{ request()->fullUrlWithQuery(['lang' => $option['code']]) }}"
                hreflang="{{ $option['code'] }}"
                lang="{{ $option['code'] }}"
                dir="{{ $option['dir'] }}"
                @if ($option['current']) aria-current="true" @endif
                rel="nofollow"
            >{{ $option['native'] }}</a>
        @endforeach
    </div>
@endif
