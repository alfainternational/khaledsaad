# خالد سعد

> منصة عربية للتشخيص التسويقي والتعلم العملي، يديرها خالد سعد من المملكة العربية السعودية.

## سلسلة تعلم التسويق

هذه السلسلة تضم دروسًا مترابطة مرتبة من الأساسيات إلى التطبيق. الصفحة الأصلية لكل درس هي المرجع العام القابل للاقتباس.

@foreach ($lessons as $lesson)
- [الدرس {{ $lesson->learning_order }}: {{ $lesson->title }}]({{ route('content.show', $lesson) }}): {{ $lesson->seo_description ?: $lesson->excerpt }}
@endforeach

## روابط أساسية

- [مكتبة المحتوى]({{ route('content.index') }})
- [سلسلة تعلم التسويق]({{ route('content.index', ['category' => 'تعلم-التسويق']) }})
- [خريطة الموقع]({{ route('sitemap') }})
