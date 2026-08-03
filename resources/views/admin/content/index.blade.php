@extends('layouts.app')
@section('layout', 'index')
@section('title', '????? ???????')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">???????</p>
            <h1>????? ???????</h1>
            <p class="muted">?????? ??????? ???????? ??????? ?????????? ????????. ?? ???? ????? ?????????.</p>
        </div>
        <a href="{{ route('admin.content.create') }}" class="btn btn--primary">????? ????</a>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="GET" class="form filter-bar">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="???? ???????? ?? ??????">
        <select name="type">
            <option value="">?? ???????</option>
            @foreach (['article' => '????', 'lesson' => '???', 'lecture' => '??????', 'course' => '????'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">?? ???????</option>
            @foreach (['draft' => '?????', 'scheduled' => '?????', 'published' => '?????', 'archived' => '?????'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="access_level">
            <option value="">?? ??????? ??????</option>
            <option value="public" @selected(request('access_level') === 'public')>?????</option>
            <option value="subscribers" @selected(request('access_level') === 'subscribers')>??? ????? ??????</option>
        </select>
        <button class="btn btn--ghost">?????</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>???????</th><th>?????</th><th>??????</th><th>??????</th><th>??? ?????</th><th></th></tr></thead>
            <tbody>
                @forelse ($contents as $item)
                    <tr>
                        <td><strong>{{ $item->title }}</strong><p class="muted">/{{ $item->slug }}</p></td>
                        <td>{{ ['article' => '????', 'lesson' => '???', 'lecture' => '??????', 'course' => '????'][$item->type] ?? $item->type }}</td>
                        <td><span class="badge">{{ ['draft' => '?????', 'scheduled' => '?????', 'published' => '?????', 'archived' => '?????'][$item->status] ?? $item->status }}</span></td>
                        <td>{{ $item->isSubscriberOnly() ? '??? ????? ??????' : '?????' }}</td>
                        <td>{{ $item->updated_at?->diffForHumans() }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.content.edit', $item) }}" class="btn btn--ghost btn--sm">?????</a>
                            @if ($item->status === 'archived')
                                <form method="POST" action="{{ route('admin.content.restore', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">???????</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.content.archive', $item) }}">@csrf @method('PATCH')<button class="btn btn--ghost btn--sm">?????</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">?? ???? ????? ???. ???? ???? ???? ?? ???.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $contents->links() }}
@endsection
