@extends('layouts.public')

@section('title', 'الصفحة غير موجودة | خالد سعد')
@section('description', 'الرابط الذي فتحته غير موجود. اختر من أين تكمل.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">الرابط غير موجود</p>
                <h1>هذه الصفحة غير موجودة.</h1>
                <p class="page-hero__lead">
                    قد يكون الرابط قديمًا أو يحتوي على خطأ في الكتابة. يمكنك اختيار وجهتك من هنا والمتابعة مباشرة.
                </p>

                <div class="page-hero__actions">
                    <a class="button button--primary button--large" href="{{ route('tools.index') }}">اطّلع على الأدوات <span aria-hidden="true">←</span></a>
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
