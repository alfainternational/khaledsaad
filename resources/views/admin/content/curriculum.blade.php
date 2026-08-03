@extends('layouts.app')
@section('layout', 'form')
@section('title', 'منهج الدورة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المحتوى · الدورات</p>
            <h1>منهج الدورة: {{ $course->title }}</h1>
            <p class="muted">قسّم الدورة إلى أقسام ثم أضف الدروس والمحاضرات بالترتيب.</p>
        </div>
        <a href="{{ route('admin.content.edit', $course) }}" class="btn btn--ghost">عودة إلى الدورة</a>
    </header>

    @if ($errors->any()) <div class="alert alert--error">{{ $errors->first() }}</div> @endif
    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="POST" action="{{ route('admin.content.sections.store', $course) }}" class="form form--wide">
        @csrf
        <div class="field-row">
            <label class="field"><span class="field__label">اسم القسم</span><input name="title" required></label>
            <label class="field"><span class="field__label">الوصف</span><input name="description"></label>
            <button class="btn btn--primary">إضافة قسم</button>
        </div>
    </form>

    <div class="curriculum-builder">
        @forelse ($course->sections as $section)
            <section class="card">
                <form method="POST" action="{{ route('admin.content.sections.update', [$course, $section]) }}" class="form">
                    @csrf @method('PUT')
                    <div class="field-row field-row--actions">
                        <label class="field"><span class="field__label">اسم القسم</span><input name="title" value="{{ $section->title }}" required></label>
                        <label class="field"><span class="field__label">الوصف</span><input name="description" value="{{ $section->description }}"></label>
                        <button class="btn btn--ghost btn--sm">حفظ القسم</button>
                    </div>
                </form>

                <ol class="curriculum-list">
                    @foreach ($section->items as $item)
                        <li>
                            <span>{{ $item->title }} · {{ $item->type === 'lesson' ? 'درس' : 'محاضرة' }}</span>
                            <form method="POST" action="{{ route('admin.content.sections.items.destroy', [$course, $section, $item]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn--ghost btn--sm">إزالة</button>
                            </form>
                        </li>
                    @endforeach
                </ol>

                <form method="POST" action="{{ route('admin.content.sections.items.store', [$course, $section]) }}" class="form">
                    @csrf
                    <div class="field-row field-row--actions">
                        <label class="field">
                            <span class="field__label">المادة</span>
                            <select name="content_id" required>
                                <option value="">اختر محتوى من المكتبة</option>
                                @foreach ($eligibleItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="btn btn--ghost btn--sm">إضافة للقسم</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.content.sections.destroy', [$course, $section]) }}" data-confirm="هل تريد حذف القسم وإزالته من هذه الدورة؟">
                    @csrf @method('DELETE')
                    <button class="btn btn--ghost btn--sm">حذف القسم</button>
                </form>
            </section>
        @empty
            <p class="empty-state">لا توجد أقسام بعد.</p>
        @endforelse
    </div>
@endsection
