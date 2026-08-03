@extends('layouts.public')
@section('layout', 'marketing')
@section('title', 'المقالات والدروس والدورات | خالد سعد')
@section('description', 'مكتبة عربية عملية تجمع المقالات والدروس والمحاضرات والدورات.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero content-library-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">المكتبة المعرفية</p>
                <h1>محتوى يساعدك على الفهم والتطبيق</h1>
                <p class="page-hero__lead">استكشف مقالات ودروسًا ومحاضرات ودورات عملية تساعدك على اتخاذ قرار أوضح وتحويل المعرفة إلى خطوات قابلة للتنفيذ.</p>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <nav class="content-filters" aria-label="تصفية المحتوى">
                    <a href="{{ route('content.index') }}" @class(['button button--ghost', 'is-active' => $type === null])>الكل</a>
                    @foreach (['article' => 'المقالات', 'lesson' => 'الدروس', 'lecture' => 'المحاضرات', 'course' => 'الدورات'] as $value => $label)
                        <a href="{{ route('content.index', ['type' => $value]) }}" @class(['button button--ghost', 'is-active' => $type === $value])>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="content-library-grid">
                    @forelse ($contents as $item)
                        <article class="content-library-card">
                            @if ($item->cover_image_path)
                                <img src="{{ str_starts_with($item->cover_image_path, 'http') || str_starts_with($item->cover_image_path, '/') ? $item->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($item->cover_image_path) }}" alt="">
                            @endif
                            <div class="content-library-card__body">
                                <div class="content-library-card__meta">
                                    <span>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] }}</span>
                                    @if ($item->isSubscriberOnly()) <span>للمشتركين</span> @endif
                                    @if ($item->duration_minutes) <span>{{ $item->duration_minutes }} دقيقة</span> @endif
                                </div>
                                <h2><a href="{{ route('content.show', $item) }}">{{ $item->title }}</a></h2>
                                <p>{{ $item->excerpt }}</p>
                                <a class="text-link" href="{{ route('content.show', $item) }}">عرض المحتوى <span>←</span></a>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state content-library-empty">
                            <h2>لم تجد مادة مناسبة بعد؟</h2>
                            <p>ابدأ التشخيص المجاني لتتعرف على فجوات مشروعك والخطوة التي تستحق الأولوية.</p>
                            <a class="button button--primary" href="{{ route('tools.index') }}">ابدأ التشخيص المجاني</a>
                        </div>
                    @endforelse
                </div>

                {{ $contents->links() }}
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
