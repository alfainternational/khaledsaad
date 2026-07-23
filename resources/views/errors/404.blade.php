@extends('layouts.public')

@section('title', 'الصفحة غير موجودة | خالد سعد')
@section('description', 'الرابط الذي فتحته غير موجود. اختر من أين تكمل.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">الرابط غير موجود</p>
                <h1>الصفحة دي ما موجودة عندنا.</h1>
                <p class="page-hero__lead">
                    غالبًا الرابط قديم أو فيه خطأ في الكتابة. ما في داعي ترجع للخلف —
                    اختر من هنا وتكمل مباشرة.
                </p>

                <div class="page-hero__actions">
                    <a class="button button--primary button--large" href="{{ route('tools.index') }}">شوف بماذا نساعدك <span aria-hidden="true">←</span></a>
                    @auth
                        <a class="button button--ghost button--large" href="{{ route('app.dashboard') }}">ادخل على مشروعك</a>
                    @else
                        <a class="button button--ghost button--large" href="{{ route('home') }}">الصفحة الرئيسية</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
