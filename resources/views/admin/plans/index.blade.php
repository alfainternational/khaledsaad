@extends('layouts.app')

@section('title', 'الخطط')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>الخطط</h1>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="btn btn--primary">خطة جديدة</a>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>الاسم</th><th>المفتاح</th><th>السعر</th><th>الرصيد الشهري</th><th>حد المشاريع</th><th>ظاهرة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td>{{ $plan->key }}</td>
                        <td>{{ $plan->price }} ريال</td>
                        <td>{{ $plan->monthly_credits }}</td>
                        <td>{{ $plan->project_limit }}</td>
                        <td>{{ $plan->is_public ? 'نعم' : 'لا' }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('حذف هذه الخطة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
