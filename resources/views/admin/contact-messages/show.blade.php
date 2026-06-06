@extends('layouts.admin', ['title' => 'رسالة', 'pageTitle' => $message->subject, 'pageKicker' => 'Contact'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>تفاصيل الرسالة</h2>
        <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" onsubmit="return confirm('حذف الرسالة؟');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
        </form>
    </div>
    <dl class="admin-form-grid cols-2 text-sm">
        <div><dt class="text-muted">الحالة</dt><dd>{{ $statusOptions[$message->status] ?? $message->status }}</dd></div>
        <div><dt class="text-muted">التاريخ</dt><dd>{{ $message->created_at->format('Y-m-d H:i') }}</dd></div>
        <div><dt class="text-muted">النوع</dt><dd>{{ $typeOptions[$message->message_type] ?? $message->message_type }}</dd></div>
        <div><dt class="text-muted">الاسم</dt><dd>{{ $message->name }}</dd></div>
        <div><dt class="text-muted">البريد</dt><dd><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></dd></div>
        @if($message->phone)
            <div><dt class="text-muted">الجوال</dt><dd>{{ $message->phone }}</dd></div>
        @endif
        <div style="grid-column: 1 / -1;"><dt class="text-muted">الموضوع</dt><dd>{{ $message->subject }}</dd></div>
        @if($message->convertedProject)
            <div><dt class="text-muted">تم التحويل إلى</dt><dd>{{ $message->convertedProject->name }}</dd></div>
            <div><dt class="text-muted">مساحة العمل</dt><dd>{{ $message->convertedWorkspace?->name }}</dd></div>
        @endif
    </dl>
    <div class="mt-6">
        <h3 class="font-bold mb-2">النص</h3>
        <pre class="admin-input whitespace-pre-wrap" style="min-height: 120px;">{{ $message->body }}</pre>
    </div>

    @if($message->isConsultation())
        @php
            $sections = [
                'الجهة' => [
                    'الشركة / المشروع' => data_get($message->payload, 'contact.company_name'),
                    'السوق' => data_get($message->payload, 'business.market'),
                ],
                'النشاط والعرض' => [
                    'وصف النشاط' => data_get($message->payload, 'business.summary'),
                    'العرض الحالي' => data_get($message->payload, 'business.offer'),
                ],
                'الجمهور' => [
                    'العميل المثالي' => data_get($message->payload, 'audience.ideal_customer'),
                    'الألم الرئيسي' => data_get($message->payload, 'audience.pain_points'),
                ],
                'الهدف والتنفيذ' => [
                    'الهدف' => data_get($message->payload, 'goals.primary_goal'),
                    'مؤشر النجاح' => data_get($message->payload, 'goals.success_metric'),
                    'الإطار الزمني' => data_get($message->payload, 'goals.timeframe'),
                    'الأولوية الحالية' => data_get($message->payload, 'execution.priority'),
                    'الميزانية' => data_get($message->payload, 'commercial.budget_range'),
                ],
            ];
            $channels = array_filter((array) data_get($message->payload, 'current_marketing.channels', []));
            $services = array_filter((array) data_get($message->payload, 'services', []));
        @endphp

        <div class="mt-8 grid gap-4">
            @foreach($sections as $sectionTitle => $items)
                <div class="admin-panel" style="padding: 1rem;">
                    <h3 class="font-bold mb-3">{{ $sectionTitle }}</h3>
                    <dl class="admin-form-grid cols-2 text-sm">
                        @foreach($items as $label => $value)
                            @continue(blank($value))
                            <div>
                                <dt class="text-muted">{{ $label }}</dt>
                                <dd class="whitespace-pre-wrap">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach

            @if($channels !== [])
                <div class="admin-panel" style="padding: 1rem;">
                    <h3 class="font-bold mb-3">القنوات الحالية</h3>
                    <p>{{ implode('، ', $channels) }}</p>
                </div>
            @endif

            @if($services !== [])
                <div class="admin-panel" style="padding: 1rem;">
                    <h3 class="font-bold mb-3">المخرجات المطلوبة</h3>
                    <p>{{ implode('، ', $services) }}</p>
                </div>
            @endif

            @if(filled(data_get($message->payload, 'notes.additional_context')))
                <div class="admin-panel" style="padding: 1rem;">
                    <h3 class="font-bold mb-3">ملاحظات إضافية</h3>
                    <pre class="admin-input whitespace-pre-wrap">{{ data_get($message->payload, 'notes.additional_context') }}</pre>
                </div>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('admin.contact-messages.update', $message) }}" class="mt-8 flex flex-wrap gap-3 items-end">
        @csrf
        @method('PATCH')
        <label class="admin-field">
            <span>تحديث الحالة</span>
            <select class="admin-input" name="status">
                @foreach ($statusOptions as $st => $label)
                    <option value="{{ $st }}" @selected($message->status === $st)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-primary">حفظ الحالة</button>
    </form>

    @if(! $message->convertedProject)
        <form method="POST" action="{{ route('admin.contact-messages.convert', $message) }}" class="mt-8 grid gap-4">
            @csrf
            <div class="admin-panel" style="padding: 1rem;">
                <h3 class="font-bold mb-4">تحويل الطلب إلى عميل / مشروع</h3>
                <div class="admin-form-grid cols-2">
                    <label class="admin-field">
                        <span>مساحة العمل المستهدفة</span>
                        <select class="admin-input" name="workspace_id" required>
                            <option value="">اختر مساحة العمل</option>
                            @foreach($workspaces as $workspace)
                                <option value="{{ $workspace->id }}">{{ $workspace->name }}{{ $workspace->account ? ' · '.$workspace->account->name : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-field">
                        <span>اسم العميل</span>
                        <input class="admin-input" name="client_name" value="{{ old('client_name', data_get($message->payload, 'contact.company_name', $message->name)) }}">
                    </label>
                    <label class="admin-field">
                        <span>اسم المشروع</span>
                        <input class="admin-input" name="project_name" value="{{ old('project_name', data_get($message->payload, 'contact.company_name', $message->subject)) }}">
                    </label>
                    <label class="admin-field">
                        <span>مرحلة المشروع</span>
                        <select class="admin-input" name="project_stage">
                            @foreach(range(1, 5) as $stage)
                                <option value="{{ $stage }}" @selected((int) old('project_stage', 2) === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <p class="text-sm text-muted mt-3">عند التحويل سيتم إنشاء عميل ومشروع جديدين، وإذا كان الطلب استشارة فسيتم ربط brief المشروع تلقائيًا.</p>
                <button type="submit" class="btn btn-secondary mt-4">تحويل إلى مشروع داخل النظام</button>
            </div>
        </form>
    @endif
</section>
@endsection
