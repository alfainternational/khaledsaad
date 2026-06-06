@extends('layouts.marketing', ['title' => 'تشخيص مجاني لمشروعك', 'description' => 'احصل على قراءة أولية صادقة لوضع مشروعك التسويقي خلال دقائق.'])

@section('content')
<section class="dx">
    <div class="dx-shell">
        <div class="dx-hero-head">
            <span class="dx-chip">تشخيص فوري</span>
            <h1 class="dx-title">اعرف أين يقف مشروعك الآن</h1>
            <p class="dx-sub">قراءة أولية صادقة لموقعك ومقارنة بمنافسك خلال دقائق. لا نطلب حساباً الآن — فقط رابطك وهدفك.</p>
        </div>

        @if ($errors->any())
            <div class="dx-alert" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('diagnose.start') }}" class="dx-card dx-form">
            @csrf

            <label class="dx-field">
                <span>نوع الحالة</span>
                <select name="case_type" class="dx-select" required>
                    <option value="website">موقع إلكتروني</option>
                    <option value="social">حسابات سوشيال</option>
                    <option value="project">مشروع بدون موقع</option>
                    <option value="competitors">مشروع ومنافسون</option>
                </select>
            </label>

            <div class="dx-grid-2">
                <label class="dx-field">
                    <span>رابط الموقع</span>
                    <input type="text" name="input_url" class="dx-input" placeholder="example.com" value="{{ old('input_url') }}">
                </label>
                <label class="dx-field">
                    <span>اسم النشاط (إن لم يوجد موقع)</span>
                    <input type="text" name="business_name" class="dx-input" value="{{ old('business_name') }}">
                </label>
            </div>

            <div class="dx-grid-2">
                <label class="dx-field">
                    <span>هدفك الحالي</span>
                    <select name="goal" class="dx-select">
                        <option value="leads">زيادة العملاء المحتملين</option>
                        <option value="trust">تحسين الثقة والتحويل</option>
                        <option value="competitors">فهم المنافسين</option>
                        <option value="content">تحسين المحتوى</option>
                        <option value="offer">إطلاق عرض أو خدمة</option>
                        <option value="diagnose">لا أعرف، شخّص لي الوضع</option>
                    </select>
                </label>
                <label class="dx-field">
                    <span>منافس للمقارنة (اختياري)</span>
                    <input type="text" name="competitor" class="dx-input" placeholder="competitor.com" value="{{ old('competitor') }}">
                </label>
            </div>

            <button type="submit" class="dx-submit">ابدأ التشخيص الآن</button>

            <p class="dx-trust">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4z"/></svg>
                التحليل يتم داخل المنصة بالكامل. لن نطلب بطاقة أو حساباً الآن.
            </p>
        </form>
    </div>
</section>
@endsection
