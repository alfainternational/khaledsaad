@extends('layouts.public')
@section('layout', 'status')

@section('title', 'انتهت صلاحية الجلسة | خالد سعد')
@section('description', 'بقيت الصفحة مفتوحة مدة طويلة فانتهت صلاحيتها. ارجع خطوة وأعد الإرسال.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">انتهت صلاحية الجلسة</p>
                <h1>الصفحة بقيت مفتوحة مدة طويلة.</h1>
                <p class="page-hero__lead">
                    لا تقلق — إجاباتك المحفوظة لم تضع. ارجع للصفحة السابقة وأعد الإرسال،
                    وسيكمل كل شيء من حيث توقفت.
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
