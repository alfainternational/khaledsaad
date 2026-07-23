@extends('layouts.app')

@section('title', 'حزم الأرصدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>حزم الأرصدة</h1>
        </div>
        <a href="{{ route('admin.packs.create') }}" class="btn btn--primary">حزمة جديدة</a>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>الاسم</th><th>الرصيد</th><th>السعر</th><th>العملة</th><th>مفعّلة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($packs as $pack)
                    <tr>
                        <td>{{ $pack->name }}</td>
                        <td>{{ $pack->credits }}</td>
                        <td>{{ $pack->price }}</td>
                        <td>{{ $pack->currency }}</td>
                        <td>{{ $pack->is_active ? 'نعم' : 'لا' }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.packs.edit', $pack) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.packs.destroy', $pack) }}" onsubmit="return confirm('حذف هذه الحزمة؟')">
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
