@extends('layouts.app')
@section('layout', 'form')

@section('title', 'معاينة تغيير الخطط')

@section('content')
<header class="page-head"><div><p class="eyebrow">الإدارة · المستخدمون</p><h1>معاينة تغيير الخطط</h1><p class="muted">@if($preview['count'] === 2) مستخدمان سيتأثران @else {{ $preview['count'] }} مستخدم/مساحة سيتأثر @endif</p></div><a href="{{ route('admin.users.plans.bulk') }}" class="btn btn--ghost">تعديل الاختيار</a></header>
<div class="table-wrap"><table class="table"><thead><tr><th>المستخدم</th><th>المساحة</th><th>الحالية</th><th>الجديدة</th><th>الرصيد</th><th>النفاذ</th></tr></thead><tbody>
@foreach($preview['items'] as $item)<tr><td>{{ $item['user'] }}</td><td>{{ $item['workspace'] }}</td><td>{{ $item['current_plan'] }}</td><td>{{ $item['target_plan'] }}</td><td>{{ $item['credit_policy'] }}</td><td>{{ $item['effective'] }}</td></tr>@endforeach
</tbody></table></div>
<form method="POST" action="{{ route('admin.users.plans.assign') }}" class="form">
    @csrf
    @foreach($payload['workspace_ids'] as $id)<input type="hidden" name="workspace_ids[]" value="{{ $id }}">@endforeach
    <input type="hidden" name="plan_id" value="{{ $payload['plan_id'] }}"><input type="hidden" name="credit_policy" value="{{ $payload['credit_policy'] }}"><input type="hidden" name="effective" value="{{ $payload['effective'] }}">
    @if(isset($payload['credit_amount']))<input type="hidden" name="credit_amount" value="{{ $payload['credit_amount'] }}">@endif
    <label><input type="checkbox" name="confirmation" value="1" required> راجعت القائمة وأؤكد تطبيق التغيير على جميع المساحات الظاهرة.</label>
    <button class="btn btn--primary" type="submit">نفّذ التغيير الجماعي</button>
</form>
@endsection
