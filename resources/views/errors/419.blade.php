@extends('layouts.public')
@section('layout', 'status')

@section('title', 'انتهت صلاحية الجلسة | خالد سعد')
@section('description', 'بقيت الصفحة مفتوحة مدة طويلة فانتهت صلاحية رمز الحماية فيها. ارجع خطوة وأعد الإرسال.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">انتهت صلاحية الجلسة</p>
                {{--
                    المصطلح يُشرح في جملته (§١٣): «انتهت الجلسة» وحدها لا تقول
                    للقارئ ما الذي انتهى ولا لماذا يرجع خطوة واحدة فيعمل.
                --}}
                <h1>بقيت الصفحة مفتوحة مدة طويلة.</h1>
                <p class="page-hero__lead">
                    كل نموذج يحمل رمز حماية تنتهي صلاحيته بعد مدة، وهذا ما انتهى.
                    إجاباتك المحفوظة سليمة: ارجع للصفحة السابقة وأعد الإرسال.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="history.back()">ارجع وأعد الإرسال <span aria-hidden="true">←</span></button>
                    @auth
                        <a class="button button--ghost button--large" href="{{ route('app.dashboard') }}">أو افتح لوحتك</a>
                    @else
                        <a class="button button--ghost button--large" href="{{ route('login') }}">أو سجّل دخولك</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
