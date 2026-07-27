@extends('layouts.app')
@section('layout', 'form')

@section('title', 'تعيين خطة لمجموعة')

@section('content')
<header class="page-head"><div><p class="eyebrow">الإدارة · المستخدمون</p><h1>تعيين خطة لمجموعة</h1></div><a href="{{ route('admin.users.index') }}" class="btn btn--ghost">عودة</a></header>
<form method="POST" action="{{ route('admin.users.plans.preview') }}" class="form form--wide form-layout">
    @csrf
    <fieldset><legend class="field__label">المستخدمون ومساحات العمل</legend>
        <div class="table-wrap"><table class="table" data-table="matrix"><thead><tr><th>اختيار</th><th>المستخدم</th><th>المساحة</th><th>الخطة الحالية</th></tr></thead><tbody>
        @foreach($users as $user) @foreach($user->workspaces as $workspace)
            <tr><td><input type="checkbox" name="workspace_ids[]" value="{{ $workspace->id }}" aria-label="اختيار {{ $workspace->name }}"></td><td>{{ $user->name }}<br><small>{{ $user->email }}</small></td><td>{{ $workspace->name }}</td><td>{{ $workspace->subscription?->plan?->name ?? 'بلا خطة' }}</td></tr>
        @endforeach @endforeach
        </tbody></table></div>
    </fieldset>
    <label class="field"><span class="field__label">الخطة الجديدة</span><select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}{{ $plan->is_public ? '' : ' (خاصة)' }}</option>@endforeach</select></label>
    <label class="field"><span class="field__label">موعد التطبيق</span><select name="effective"><option value="now">فورًا</option><option value="period_end">نهاية الفترة</option></select></label>
    <label class="field"><span class="field__label">الرصيد</span><select name="credit_policy"><option value="keep">إبقاؤه</option><option value="plan_grant">إضافة رصيد الخطة</option><option value="add">إضافة مقدار</option></select></label>
    <label class="field"><span class="field__label">المقدار الإضافي</span><input type="number" name="credit_amount" min="1"></label>
    <button class="btn btn--primary" type="submit">اعرض المعاينة</button>
</form>
@endsection
