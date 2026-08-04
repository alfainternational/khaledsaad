<section class="content-gate" aria-labelledby="content-gate-title">
    <p class="eyebrow">للمشتركين</p>
    <h2 id="content-gate-title">سجّل بريدك لفتح المحتوى</h2>
    <p>لا توجد رسوم. أدخل بريدك مرة واحدة لفتح المحتوى المخصص للمشتركين على هذا الجهاز.</p>

    @if ($errors->any()) <div class="alert alert--error">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('content.subscribe', $content) }}" class="form">
        @csrf
        <label class="field">
            <span class="field__label">البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
        </label>
        <label class="field field--inline">
            <input type="checkbox" name="consent" value="1" required>
            <span>أوافق على حفظ بريدي لغرض فتح المحتوى وإرسال التحديثات.</span>
        </label>
        <button class="button button--primary">فتح المحتوى مجانًا</button>
    </form>
</section>
