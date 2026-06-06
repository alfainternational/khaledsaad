@extends('layouts.admin', ['title' => 'رسائل التواصل', 'pageTitle' => 'صندوق الوارد', 'pageKicker' => 'Contact'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>الرسائل</h2>
        <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="flex gap-2 items-center">
            <select name="message_type" class="admin-input" onchange="this.form.submit()">
                <option value="">كل الأنواع</option>
                @foreach($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($typeFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="admin-input" onchange="this.form.submit()">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>النوع</th>
                    <th>الاسم</th>
                    <th>البريد</th>
                    <th>ملخص الطلب</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr>
                        <td>{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $typeOptions[$m->message_type] ?? $m->message_type }}</td>
                        <td>{{ $m->name }}</td>
                        <td>{{ $m->email }}</td>
                        <td>
                            <div>{{ \Illuminate\Support\Str::limit($m->subject, 55) }}</div>
                            @if($m->convertedProject)
                                <div class="text-muted text-xs mt-1">محول إلى: {{ $m->convertedProject->name }}</div>
                            @elseif($m->isConsultation())
                                <div class="text-muted text-xs mt-1">{{ \Illuminate\Support\Str::limit(data_get($m->payload, 'goals.primary_goal', ''), 55) }}</div>
                            @endif
                        </td>
                        <td>{{ $statusOptions[$m->status] ?? $m->status }}</td>
                        <td><a href="{{ route('admin.contact-messages.show', $m) }}" class="btn btn-secondary btn-sm">عرض</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا رسائل.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $messages->links() }}</div>
</section>
@endsection
