@props(['locale' => null, 'kind' => 'output'])

@php
    /*
     * إعلان لغة المحتوى حين تختلف عن لغة الواجهة.
     *
     * §٤.٣ يقول «الفجوات تُعلن ولا تُخفى». كُتب عن فجوات البيانات، وينطبق
     * حرفيًّا على اللغة: من يفتح تقريرًا بالفرنسية ويجد نصّه عربيًّا بلا
     * كلمة تفسير يقرأ ذلك عطلًا في المنتج، لا حدًّا معروفًا. والفرق بين
     * الاثنين سطرٌ واحد.
     *
     * لا يظهر شيء حين تتطابق اللغتان — وهو الحال الطبيعي لكل مستخدم عربي.
     */
    $registry = $appLocales;
    $contentLocale = $locale ?: $registry->source();
    $mismatched = $registry->isEnabled($contentLocale) && $contentLocale !== app()->getLocale();
@endphp

@if ($mismatched)
    <p {{ $attributes->merge(['class' => 'alert alert--info content-language']) }} role="note">
        <span class="content-language__badge" lang="{{ $registry->htmlLang($contentLocale) }}"
              dir="{{ $registry->direction($contentLocale) }}">{{ $registry->nativeName($contentLocale) }}</span>
        {{--
            اسم اللغة يحمله الوسم المجاور ولا يُحقن في الجملة.

            حقنه يُنتج «available in العربية only» — اسمٌ بلغته داخل جملة
            بلغة أخرى، يقرؤه من لا يعرف الحروف رموزًا. والوسم يؤدي المعنى
            بصريًّا وبـ`dir` صحيح، فتبقى الجملة قابلة للترجمة كاملةً.
        --}}
        @if ($kind === 'library')
            {{ __('هذه المادة متاحة بهذه اللغة فقط. واجهة المنصة بلغتك، لكن محتوى الأكاديمية لم يُترجَم بعد.') }}
        @else
            {{ __('كُتب هذا المخرَج بهذه اللغة لأنه وُلّد وأنت تستخدمها. تبديل لغة الواجهة لا يعيد كتابة مخرَج وُلّد من قبل.') }}
        @endif

        {{ $slot }}
    </p>
@endif
