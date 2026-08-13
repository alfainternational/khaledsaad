@extends('layouts.public')
@section('layout', 'status')

@section('title', 'الصفحة غير موجودة | خالد سعد')
@section('description', 'الرابط الذي فتحته لا يقابل صفحة على الموقع. اختر من أين تكمل.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">الرابط غير موجود</p>
                {{--
                    صفحة الخطأ تقول ما حدث ثم تعطي طريق الخروج، بلا اعتذار
                    ولا طرفة: القارئ هنا يبحث عن وجهة لا عن مجاملة.
                --}}
                <h1>هذه الصفحة غير موجودة.</h1>
                <p class="page-hero__lead">
                    الرابط الذي فتحته لا يقابل صفحة في الموقع — غالبًا قديم أو فيه خطأ كتابة.
                    اختر وجهتك من هنا وأكمل مباشرة.
                </p>

                <div class="page-hero__actions">
                    @auth
                        <a class="button button--primary button--large" href="{{ route('app.dashboard') }}">افتح لوحة مشروعك <span aria-hidden="true">←</span></a>
                    @else
                        <a class="button button--primary button--large" href="{{ route('tools.index') }}">اطّلع على التشخيصات <span aria-hidden="true">←</span></a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
