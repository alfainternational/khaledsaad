@extends('layouts.app')
@section('layout', 'index')

@section('title', 'فهرس الميزات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>فهرس الميزات</h1>
            <p class="muted">
                هذه هي العناصر التي تُختار داخل الخطط. العنصر الموسوم «مُطبَّق» يمنعه النظام فعليًا عند التجاوز،
                والموسوم «عرضي» وعد خدمة بشري لا بوابة تقنية.
            </p>
        </div>
        <a href="{{ route('admin.features.create') }}" class="btn btn--primary">عنصر جديد</a>
    </header>

    @if ($errors->any())
        <div class="alert alert--error">{{ $errors->first() }}</div>
    @endif

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>العنصر</th><th>المفتاح</th><th>المجموعة</th><th>النوع</th>
                    <th>التطبيق</th><th>الافتراضي</th><th>مفعّل</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($features as $feature)
                    <tr>
                        <td>
                            {{ $feature->name }}
                            @if ($feature->description)
                                <p class="muted">{{ $feature->description }}</p>
                            @endif
                        </td>
                        <td><code>{{ $feature->key }}</code></td>
                        <td>{{ $feature->groupLabel() }}</td>
                        <td>{{ $feature->typeLabel() }}{{ $feature->unit ? ' · '.$feature->unit : '' }}</td>
                        <td>
                            <span @class(['badge', 'badge--assumption' => ! in_array($feature->key, $wired, true)])>
                                {{ in_array($feature->key, $wired, true) ? 'مُطبَّق' : 'عرضي' }}
                            </span>
                        </td>
                        <td>
                            @if ($feature->isNumeric())
                                {{ $feature->default_enabled ? ($feature->default_value ?? 'بلا حد') : 'مغلق' }}
                            @else
                                {{ $feature->default_enabled ? 'مفتوح' : 'مغلق' }}
                            @endif
                        </td>
                        <td>{{ $feature->is_active ? 'نعم' : 'لا' }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.features.edit', $feature) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            @unless (in_array($feature->key, $wired, true))
                                <form method="POST" action="{{ route('admin.features.destroy', $feature) }}" data-confirm="حذف هذا العنصر من كل الخطط؟">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
