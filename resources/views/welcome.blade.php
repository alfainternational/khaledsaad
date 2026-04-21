@extends('layouts.marketing', [
    'title' => config('app.name'),
    'description' => 'واجهة Laravel النظيفة للمشروع.',
])

@section('content')
<section class="section-lg">
    <div class="site-container">
        <div class="card reveal text-center">
            <p class="text-eyebrow mb-3 text-p">Laravel</p>
            <h1 class="heading-lg mb-4">المنصة جاهزة للعمل</h1>
            <p class="text-body mb-6">
                هذه صفحة ترحيبية بسيطة مرتبطة ببنية الواجهة المركزية للمشروع بدون أي أنماط أو سكربتات مضمنة.
            </p>
            <div class="cta-actions">
                <a href="{{ route('home') }}" class="btn btn-primary btn-lg">الصفحة الرئيسية</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-lg">لوحة العمل</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-lg">تسجيل الدخول</a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
