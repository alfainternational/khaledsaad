@extends('layouts.app')
@section('content')
<section class="page-head"><p class="eyebrow">حوكمة التشخيص</p><h1>الاستشارة التسويقية الذكية</h1><p>الإصدارات المنشورة مقفلة؛ كل تعديل يبدأ من مسودة مستقلة.</p></section>
@foreach($blueprints as $blueprint)
<article class="card">
    <h2>{{ $blueprint->name }}</h2>
    <p>الإصدار الحالي: {{ $blueprint->currentVersion?->version ?? '—' }}</p>
    <div class="actions">
        @if($blueprint->currentVersion)<a class="btn btn--secondary" href="{{ route('admin.consultations.show', $blueprint->currentVersion) }}">راجع الإصدار الحالي</a>@endif
        <form method="POST" action="{{ route('admin.consultations.drafts.store', $blueprint) }}">@csrf<button class="btn btn--primary">أنشئ مسودة جديدة</button></form>
    </div>
    <ul>@foreach($blueprint->versions as $version)<li><a href="{{ route('admin.consultations.show', $version) }}">الإصدار {{ $version->version }} — {{ $version->status }}</a></li>@endforeach</ul>
</article>
@endforeach
@endsection
