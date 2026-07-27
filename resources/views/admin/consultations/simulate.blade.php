@extends('layouts.app')
@section('layout', 'index')
@section('content')
<section class="page-head"><p class="eyebrow">محاكاة آمنة بلا حفظ</p><h1>محاكاة الإصدار {{ $version->version }}</h1></section>
<form method="GET" class="card"><label>معرّف المشروع (slug)<input name="project" value="{{ request('project') }}" required></label><button class="btn btn--primary">شغّل المحاكاة</button></form>
@if($project)<h2>نطاق {{ $project->name }}</h2>@foreach($result as $row)<article class="card"><strong>{{ $row['module'] }}</strong><p>{{ $row['state'] }} — {{ $row['reason'] }}</p></article>@endforeach@endif
@endsection
