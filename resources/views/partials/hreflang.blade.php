{{--
    اللغات البديلة لهذه الصفحة.

    `x-default` يشير إلى العربية لا إلى الإنجليزية: هي لغة السوق المستهدف،
    فمن وصل بلا تفضيل لغوي معروف يجب أن يصل إليها.

    كل رابط هنا يجب أن يطابق `canonical` الخاص بتلك اللغة حرفيًّا — وهو ما
    يوفّره `LocaleUrls` لكليهما من مصدر واحد. حين اختلفا كان جوجل يرى
    إشارتين متناقضتين ويحسمهما لصالح `canonical`، فتسقط اللغات الأخرى من
    الفهرس كلها.

    ملاحظة صريحة عن الحدود: التمايز بمعامل `?lang=` لا بمسار `/en/`. جوجل
    يدعم المعامل ويفهرسه، لكن المسار إشارة أقوى. الترقية تغيّر شكل كل رابط
    عام، فهي قرار منفصل — وهذا الملف و`LocaleUrls` هما الموضعان الوحيدان
    اللذان يتغيّران عندها.
--}}
@if (count($appLocales->enabled()) > 1)
    @foreach ($localeUrls->alternates() as $code => $href)
        <link rel="alternate" hreflang="{{ $appLocales->htmlLang($code) }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $localeUrls->forLocale($appLocales->source()) }}">
@endif
