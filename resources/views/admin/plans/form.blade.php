@extends('layouts.app')
@section('layout', 'form')

@section('title', $plan->exists ? 'تعديل خطة' : 'خطة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · الخطط</p>
            <h1>{{ $plan->exists ? 'تعديل: '.$plan->name : 'خطة جديدة' }}</h1>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="form form--wide form-layout">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div class="field-row">
            <label class="field">
                <span class="field__label">المفتاح (إنجليزي)</span>
                <input type="text" name="key" value="{{ old('key', $plan->key) }}" required>
            </label>
            <label class="field">
                <span class="field__label">الاسم</span>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">السعر (ريال)</span>
                <input type="number" name="price" value="{{ old('price', $plan->price ?? 0) }}" min="0" required>
            </label>
            <label class="field">
                <span class="field__label">الدورة</span>
                <select name="interval">
                    <option value="monthly" @selected(old('interval', $plan->interval) === 'monthly')>شهرية</option>
                    <option value="yearly" @selected(old('interval', $plan->interval) === 'yearly')>سنوية</option>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field__label">الرصيد الشهري</span>
                <input type="number" name="monthly_credits" value="{{ old('monthly_credits', $plan->monthly_credits ?? 0) }}" min="0" required>
            </label>
            <label class="field">
                <span class="field__label">حد المشاريع</span>
                <input type="number" name="project_limit" value="{{ old('project_limit', $plan->project_limit ?? 1) }}" min="1" required>
            </label>
            <label class="field">
                <span class="field__label">الترتيب</span>
                <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0">
            </label>
        </div>

        <fieldset class="field">
            <legend class="field__label">عناصر الميزات</legend>
            <p class="field__help">
                اختر ما تشمله الخطة وحدّد عدده. العدد الفارغ في الحدود والحصص يعني «بلا حد»، والصفر يعني المنع.
                ما لا تختاره لا يُعرض ولا يُسمح به. العناصر الموسومة «عرضية» تظهر للعميل ولا يمنعها النظام تقنيًا.
            </p>

            @foreach ($features->groupBy('group') as $group => $items)
                <p class="eyebrow">{{ $items->first()->groupLabel() }}</p>
                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead>
                            <tr><th>ضمن الخطة</th><th>العنصر</th><th>العدد</th><th>نص بديل (اختياري)</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $feature)
                                @php($row = $selection[$feature->id] ?? null)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="features[{{ $feature->id }}][enabled]" value="1"
                                            @checked(old("features.{$feature->id}.enabled", $row?->enabled))>
                                    </td>
                                    <td>
                                        {{ $feature->name }}
                                        @unless ($feature->isEnforced())
                                            <span class="badge badge--assumption">عرضي</span>
                                        @endunless
                                        @if ($feature->description)
                                            <p class="muted">{{ $feature->description }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($feature->isNumeric())
                                            <input type="number" min="0" style="max-width:7rem"
                                                name="features[{{ $feature->id }}][value]"
                                                value="{{ old("features.{$feature->id}.value", $row?->value) }}"
                                                placeholder="بلا حد">
                                            <span class="muted">{{ $feature->unit }}</span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="text" maxlength="190" name="features[{{ $feature->id }}][note]"
                                            value="{{ old("features.{$feature->id}.note", $row?->note) }}"
                                            placeholder="{{ $feature->describeValue($row?->value) }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </fieldset>

        <label class="field">
            <span class="field__label">سطور وصف إضافية (تُعرض فقط إن لم تُحدَّد أي عناصر أعلاه)</span>
            <textarea name="features_text" rows="3">{{ old('features_text', implode("\n", $plan->features ?? [])) }}</textarea>
        </label>

        <label class="field field--inline">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public ?? true))>
            <span>ظاهرة للعملاء</span>
        </label>

        <button type="submit" class="btn btn--primary">{{ $plan->exists ? 'حفظ' : 'إنشاء' }}</button>
    </form>
@endsection
