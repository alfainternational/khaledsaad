{{--
    قاموس نصوص JavaScript للغة الطلب.

    يسبق `@vite` دائمًا: الحزمة تقرأ `window.__I18N__` عند التحميل، وقاموسٌ
    يصل بعدها يعني نصوصًا عربية في أول رسم للشاشة ثم لا شيء يصلحها.

    عربيًّا يخرج `{}` — المفتاح هو النصّ نفسه، فلا شيء يُرسَل ولا شيء يُقرأ.
--}}
@php($phrases = $jsPhrases->forLocale(app()->getLocale()))

@if ($phrases !== [])
    <script>
        window.__I18N__ = {!! json_encode($phrases, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    </script>
@endif
