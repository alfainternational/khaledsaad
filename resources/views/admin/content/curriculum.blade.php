@extends('layouts.app')
@section('layout', 'form')
@section('title', '???? ??????')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">??????? ? ???????</p>
            <h1>???? ??????: {{ $course->title }}</h1>
            <p class="muted">???? ?????? ??? ????? ?? ??? ?????? ?????????? ????????.</p>
        </div>
        <a href="{{ route('admin.content.edit', $course) }}" class="btn btn--ghost">???? ??? ??????</a>
    </header>

    @if ($errors->any()) <div class="alert alert--error">{{ $errors->first() }}</div> @endif
    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="POST" action="{{ route('admin.content.sections.store', $course) }}" class="form form--wide">
        @csrf
        <div class="field-row">
            <label class="field"><span class="field__label">??? ?????</span><input name="title" required></label>
            <label class="field"><span class="field__label">?????</span><input name="description"></label>
            <button class="btn btn--primary">????? ???</button>
        </div>
    </form>

    <div class="curriculum-builder">
        @forelse ($course->sections as $section)
            <section class="card">
                <form method="POST" action="{{ route('admin.content.sections.update', [$course, $section]) }}" class="form">
                    @csrf @method('PUT')
                    <div class="field-row">
                        <input name="title" value="{{ $section->title }}" required>
                        <input name="description" value="{{ $section->description }}">
                        <button class="btn btn--ghost btn--sm">??? ?????</button>
                    </div>
                </form>

                <ol class="curriculum-list">
                    @foreach ($section->items as $item)
                        <li>
                            <span>{{ $item->title }} ? {{ $item->type === 'lesson' ? '???' : '??????' }}</span>
                            <form method="POST" action="{{ route('admin.content.sections.items.destroy', [$course, $section, $item]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn--ghost btn--sm">?????</button>
                            </form>
                        </li>
                    @endforeach
                </ol>

                <form method="POST" action="{{ route('admin.content.sections.items.store', [$course, $section]) }}" class="form">
                    @csrf
                    <div class="field-row">
                        <select name="content_id" required>
                            <option value="">???? ????? ?? ??????</option>
                            @foreach ($eligibleItems as $item)
                                <option value="{{ $item->id }}">{{ $item->title }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn--ghost btn--sm">????? ??????</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.content.sections.destroy', [$course, $section]) }}" data-confirm="??? ????? ?????? ?????? ?? ??? ???????">
                    @csrf @method('DELETE')
                    <button class="btn btn--ghost btn--sm">??? ?????</button>
                </form>
            </section>
        @empty
            <p class="empty-state">?? ???? ???? ???.</p>
        @endforelse
    </div>
@endsection
