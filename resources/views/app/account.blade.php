@extends('layouts.app', ['title' => 'الحساب', 'pageTitle' => 'إعدادات الحساب', 'pageKicker' => 'Account'])

@section('content')
<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">الإعدادات الأساسية</h3>
        </div>
        <form method="POST" action="{{ route('account.update') }}" class="app-form-grid cols-2">
            @csrf
            @method('PATCH')
            <label class="app-field">
                <span>اسم المستخدم</span>
                <input class="app-input" name="name" value="{{ old('name', auth()->user()->name) }}">
            </label>
            <label class="app-field">
                <span>اللغة</span>
                <select class="app-input" name="locale">
                    <option value="ar" @selected(old('locale', auth()->user()->locale) === 'ar')>ar</option>
                    <option value="en" @selected(old('locale', auth()->user()->locale) === 'en')>en</option>
                </select>
            </label>
            <label class="app-field">
                <span>اسم الحساب</span>
                <input class="app-input" name="account_name" value="{{ old('account_name', $account->name) }}">
            </label>
            <label class="app-field">
                <span>البريد المحاسبي</span>
                <input class="app-input" name="billing_email" value="{{ old('billing_email', $account->billing_email) }}">
            </label>
            <label class="app-field">
                <span>اسم مساحة العمل</span>
                <input class="app-input" name="workspace_name" value="{{ old('workspace_name', $workspace->name) }}">
            </label>
            <label class="app-field">
                <span>نوع مساحة العمل</span>
                <select class="app-input" name="workspace_type">
                    @foreach (['personal', 'team', 'agency'] as $type)
                        <option value="{{ $type }}" @selected(old('workspace_type', $workspace->type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>نوع الاستخدام</span>
                <select class="app-input" name="persona">
                    @foreach ($personas as $key => $label)
                        <option value="{{ $key }}" @selected(old('persona', $profile['persona'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>مستوى الفهم</span>
                <select class="app-input" name="awareness_level">
                    @foreach ($awarenessLevels as $key => $label)
                        <option value="{{ $key }}" @selected(old('awareness_level', $profile['awareness_level'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>الهدف الرئيسي</span>
                <select class="app-input" name="primary_goal">
                    @foreach ($goals as $key => $label)
                        <option value="{{ $key }}" @selected(old('primary_goal', $profile['primary_goal'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>المسار</span>
                <select class="app-input" name="recommended_path">
                    <option value="">تحديد تلقائي</option>
                    @foreach ($paths as $key => $label)
                        <option value="{{ $key }}" @selected(old('recommended_path', $profile['recommended_path'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field cols-span-2">
                <span>الفئة المستهدفة</span>
                <input class="app-input" name="audience" value="{{ old('audience', $profile['audience'] ?? null) }}">
            </label>
            <label class="app-field">
                <span>الدولة أو السوق الأساسي</span>
                <input class="app-input" name="country" value="{{ old('country', $profile['country'] ?? null) }}" placeholder="مثال: السعودية" required>
            </label>
            <label class="app-field">
                <span>لغة ولهجة المحتوى</span>
                <select class="app-input" name="content_locale" required>
                    @foreach ($contentLocales as $key => $label)
                        <option value="{{ $key }}" @selected(old('content_locale', $profile['content_locale'] ?? 'ar_modern_fusha') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field cols-span-2">
                <span>أكبر تحدٍ حالي</span>
                <input class="app-input" name="current_challenge" value="{{ old('current_challenge', $profile['current_challenge'] ?? null) }}">
            </label>
            <div class="app-form-actions cols-span-2">
                <button type="submit" class="btn btn-primary btn-lg">حفظ الإعدادات</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">الخطة والصلاحيات</h3>
            <a href="{{ route('billing.index') }}" class="btn btn-secondary btn-sm">ترقية الخطة / PayPal</a>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>الخطة الحالية</strong>
                    <small>{{ $account->subscription?->status ?? 'بدون اشتراك' }}</small>
                </div>
                <span class="app-badge">{{ $account->subscription?->plan?->name_ar ?? '—' }}</span>
            </div>
            @foreach ($entitlements as $key => $value)
                <div class="app-list-item">
                    <div><strong>{{ $key }}</strong></div>
                    <span class="app-badge">{{ is_bool($value) ? ($value ? 'enabled' : 'disabled') : $value }}</span>
                </div>
            @endforeach
        </div>
    </article>
</section>

@if ($isAccountOwner)
<section class="app-grid mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">مفتاح الذكاء الخاص (BYOK)</h3>
            <span class="app-badge">{{ $aiKeyConnected ? 'مربوط' : 'غير مربوط' }}</span>
        </div>
        <p class="app-empty">اربط مفتاح مزوّد ذكاء خاص بك لتعمل توليدات هذا الحساب على مفتاحك بدل رصيد المنصة. لن يُعرض المفتاح بعد حفظه.</p>
        @if ($aiKeyConnected)
            <div class="app-list">
                <div class="app-list-item">
                    <div>
                        <strong>المزوّد الحالي</strong>
                        <small>{{ $aiKeyMasked }}</small>
                    </div>
                    <span class="app-badge">{{ $aiKeyProvider }}</span>
                </div>
            </div>
        @endif
        <form method="POST" action="{{ route('account.ai-key.update') }}" class="app-form-grid cols-2">
            @csrf
            @method('PATCH')
            <label class="app-field">
                <span>المزوّد</span>
                <select class="app-input" name="provider" required>
                    @foreach ($aiKeyProviders as $provider)
                        <option value="{{ $provider }}" @selected(old('provider', $aiKeyProvider) === $provider)>{{ $provider }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>المفتاح</span>
                <input class="app-input" type="password" name="key" autocomplete="off" placeholder="{{ $aiKeyConnected ? 'أدخل مفتاحاً جديداً للاستبدال' : 'الصق مفتاح المزوّد هنا' }}" required>
            </label>
            <div class="app-form-actions cols-span-2">
                <button type="submit" class="btn btn-primary btn-lg">{{ $aiKeyConnected ? 'تحديث المفتاح' : 'ربط المفتاح' }}</button>
            </div>
        </form>
        @if ($aiKeyConnected)
            <form method="POST" action="{{ route('account.ai-key.destroy') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-secondary btn-sm">إلغاء الربط</button>
            </form>
        @endif
    </article>
</section>
@endif

<section class="app-grid app-two-col">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">أعضاء المساحة</h3>
            <a href="{{ route('team.index') }}" class="btn btn-secondary btn-sm">إدارة الفريق</a>
        </div>
        <div class="app-list">
            @forelse ($members as $member)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $member->user?->name ?? 'مستخدم محذوف' }}</strong>
                        <small>{{ $member->user?->email ?? '—' }} · {{ $member->role }}</small>
                    </div>
                    <span class="app-badge">{{ $member->status }}</span>
                </div>
            @empty
                <p class="app-empty">لا يوجد أعضاء داخل المساحة.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">الدعوات المعلقة</h3>
        </div>
        <div class="app-list">
            @forelse ($invitations as $invitation)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $invitation->email }}</strong>
                        <small>{{ $invitation->role }} · {{ $invitation->status }}</small>
                    </div>
                    <span class="app-badge">{{ $invitation->expires_at?->toDateString() ?? '—' }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد دعوات معلقة.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
