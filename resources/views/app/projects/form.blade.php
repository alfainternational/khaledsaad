@extends('layouts.app', ['title' => $project->exists ? 'تعديل المشروع' : 'مشروع جديد', 'pageTitle' => $project->exists ? 'تعديل المشروع' : 'مشروع جديد', 'pageKicker' => 'Projects'])

@section('content')
@php($verifiedProfiles = old('verified_social_profiles_json', $project->verified_social_profiles_json ?? []))
@php($verifiedProfiles = is_array($verifiedProfiles) ? array_values($verifiedProfiles) : [])
@php($verifiedProfiles = $verifiedProfiles === [] ? [[], [], []] : array_pad($verifiedProfiles, max(3, count($verifiedProfiles)), []))
<form method="POST" action="{{ $action }}" class="app-form-grid" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="card">
        <div class="app-form-grid cols-2">
            <label class="app-field">
                <span>اسم المشروع</span>
                <input class="app-input" name="name" value="{{ old('name', $project->name) }}">
            </label>
            <label class="app-field">
                <span>العميل المرتبط</span>
                <select class="app-input" name="client_id">
                    <option value="">بدون عميل</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>المرحلة الحالية</span>
                <select class="app-input" name="stage">
                    @foreach ([1, 2, 3, 4, 5] as $stage)
                        <option value="{{ $stage }}" @selected((int) old('stage', $project->stage ?: 1) === $stage)>المرحلة {{ $stage }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>الحالة</span>
                <select class="app-input" name="status">
                    @foreach (['active', 'paused', 'completed', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $project->status ?: 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>القطاع</span>
                <select class="app-input" name="sector">
                    @foreach ($sectorOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('sector', $project->sector ?: 'general_business') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>السوق أو الدولة المرجعية</span>
                <input class="app-input" name="market_country" value="{{ old('market_country', $project->market_country) }}" placeholder="مثال: السعودية">
            </label>
            <label class="app-field cols-span-2">
                <span>الدومين الأساسي</span>
                <input class="app-input" name="primary_domain" value="{{ old('primary_domain', $project->primary_domain) }}" placeholder="example.com أو https://example.com">
            </label>
            <label class="app-field cols-span-2">
                <span>شعار المشروع</span>
                <input class="app-input" type="file" name="logo" accept="image/*">
                @if ($project->logo_path)
                    <small class="text-caption">الشعار الحالي محفوظ. رفع شعار جديد سيستبدله.</small>
                @else
                    <small class="text-caption">ارفع شعاراً مربعاً أو أفقياً واضحاً ليظهر في تقارير المشروع والتطبيق.</small>
                @endif
            </label>
            <label class="app-field cols-span-2">
                <span>الروابط الرسمية للسوشيال</span>
                <textarea class="app-input" name="official_social_links" rows="4" placeholder="رابط واحد في كل سطر">{{ old('official_social_links', implode(PHP_EOL, $project->official_social_links_json ?? [])) }}</textarea>
            </label>
            <div class="app-field cols-span-2">
                <span>التحقق اليدوي للسوشيال</span>
                <small class="text-caption">استخدمه عندما تمنع LinkedIn / X / غيرها القراءة العامة. كل بطاقة تمثل حساباً موثقاً يدوياً وسيُستخدم كدليل fallback داخل التحليل.</small>
                <div class="app-form-grid cols-2 mt-4">
                    @foreach ($verifiedProfiles as $index => $profile)
                        <div class="card">
                            <div class="app-form-grid cols-2">
                                <label class="app-field">
                                    <span>الشبكة</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][network]" value="{{ data_get($profile, 'network') }}" placeholder="LinkedIn / X / Instagram">
                                </label>
                                <label class="app-field">
                                    <span>الرابط</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][url]" value="{{ data_get($profile, 'url') }}" placeholder="https://...">
                                </label>
                                <label class="app-field">
                                    <span>المعرف أو الـ handle</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][handle]" value="{{ data_get($profile, 'handle') }}" placeholder="@example أو /company/example">
                                </label>
                                <label class="app-field">
                                    <span>عنوان الحساب / الاسم الظاهر</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][title]" value="{{ data_get($profile, 'title') }}" placeholder="اسم الحساب كما يظهر للمستخدم">
                                </label>
                                <label class="app-field cols-span-2">
                                    <span>وصف الحساب الموثق يدوياً</span>
                                    <textarea class="app-input" name="verified_social_profiles_json[{{ $index }}][description]" rows="3" placeholder="ما الذي يقوله الـ bio / about / headline فعلياً؟">{{ data_get($profile, 'description') }}</textarea>
                                </label>
                                <label class="app-field">
                                    <span>الـ CTA الأساسي</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][primary_cta]" value="{{ data_get($profile, 'primary_cta') }}" placeholder="احجز / تواصل / زر الرابط في البايو">
                                </label>
                                <label class="app-field">
                                    <span>ملاحظات التحقق</span>
                                    <input class="app-input" name="verified_social_profiles_json[{{ $index }}][verification_notes]" value="{{ data_get($profile, 'verification_notes') }}" placeholder="مثال: verified manually on 2026-04-23">
                                </label>
                                <label class="app-field cols-span-2">
                                    <span class="flex items-center gap-3">
                                        <input type="checkbox" name="verified_social_profiles_json[{{ $index }}][links_back_to_site]" value="1" @checked((bool) data_get($profile, 'links_back_to_site'))>
                                        <span>الحساب يربط فعلاً إلى الموقع الرسمي أو صفحة قرار مرتبطة به</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <label class="app-field cols-span-2">
                <span>المنافسون</span>
                <textarea class="app-input" name="competitors" rows="4" placeholder="اسم أو دومين كل منافس في سطر">{{ old('competitors', collect($project->competitors_json ?? [])->map(fn ($competitor) => is_array($competitor) ? ($competitor['domain'] ?? $competitor['label'] ?? '') : $competitor)->filter()->implode(PHP_EOL)) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>أهداف التحليل</span>
                <textarea class="app-input" name="analysis_goals" rows="3" placeholder="مثال: تحسين التحويل&#10;فهم المنافسين&#10;تجهيز الموقع للإعلانات">{{ old('analysis_goals', implode(PHP_EOL, $project->analysis_goals_json ?? [])) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span class="flex items-center gap-3">
                    <input type="checkbox" name="monitoring_enabled" value="1" @checked(old('monitoring_enabled', $project->monitoring_enabled))>
                    <span>تفعيل المراقبة الدورية لهذا المشروع</span>
                </span>
            </label>
        </div>
    </section>

    <div class="app-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $project->exists ? 'حفظ التعديلات' : 'إنشاء المشروع' }}</button>
        <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection
