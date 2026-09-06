{{--
    أنماط الإصلاح: عناصر في المنصة تُعرض بلا تنسيق إطلاقًا.

    خارج حزمة Vite ومع الالتقاط في نفس الجزئية لأنهما يشتركان في السبب:
    حزمة الأصول لا يمكن إعادة بناؤها الآن بلا نشر عمل غير معتمَد. تُطوى
    قواعده في `resources/css/` عند أول بناء كامل ويُحذف الملف ووسمه.
--}}

{{--
    جسر الالتقاط بين الخادم والمتصفح.

    الوسم يحمل معرّف الزيارة ومعرّف هذه المشاهدة تحديدًا — وهما مولَّدان
    في الوسيط قبل العرض. بدون معرّف المشاهدة كان البيكون سيقول «بقي ٤٠
    ثانية في الجلسة» ولا يعرف في أي صفحة منها، فينهار قياس الصفحات.

    غياب الوسوم = الالتقاط مُطفأ، فيصمت السكربت كليًّا ولا يحاول الاتصال.

    والسكربت خارج حزمة Vite بوسم مباشر: القياس يجب ألّا يتوقّف لأن الحزمة
    قديمة في متصفح الزائر أو لأن دفعة نشر لم تشمل `public/build`. وبصمة
    التعديل في الرابط تمنع بقاء نسخة قديمة في الكاش بعد أي تحديث.
--}}
@php($insightsContext = request()->attributes->get('insights'))

@if (is_array($insightsContext))
    <meta name="insights-visit" content="{{ $insightsContext['visit'] }}">
    <meta name="insights-view" content="{{ $insightsContext['view'] }}">
    <meta name="insights-endpoint" content="{{ route('insights.collect') }}">
    <meta name="insights-heartbeat" content="{{ (int) config('insights.heartbeat_seconds', 15) }}">
    <meta name="insights-idle" content="{{ (int) config('insights.idle_after_seconds', 60) }}">

    <script defer src="{{ asset('js/insights.js') }}?v={{ @filemtime(public_path('js/insights.js')) ?: 1 }}"></script>
@endif
