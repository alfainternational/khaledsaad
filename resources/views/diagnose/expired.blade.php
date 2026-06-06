@extends('layouts.marketing', ['title' => 'انتهت صلاحية التشخيص', 'description' => 'أعد تشغيل التشخيص للحصول على قراءة جديدة.'])

@section('content')
<section class="dx">
    <div class="dx-shell dx-shell--narrow">
        <div class="dx-card dx-center">
            <span class="dx-chip">انتهت الصلاحية</span>
            <h1 class="dx-title">انتهت صلاحية هذا التشخيص الأولي</h1>
            <p class="dx-sub">القراءة الأولية صالحة لفترة محدودة. أعد تشغيل التشخيص للحصول على قراءة محدّثة.</p>
            <p class="dx-mt-4"><a href="{{ route('diagnose.form') }}" class="dx-submit dx-btn-inline">أعد التشخيص</a></p>
        </div>
    </div>
</section>
@endsection
