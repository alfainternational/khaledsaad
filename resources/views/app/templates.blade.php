@extends('layouts.app', ['title' => 'القوالب', 'pageTitle' => 'قوالب جاهزة', 'pageKicker' => 'Templates'])

@section('content')
<section class="app-card-grid">
    @forelse ($templates as $template)
        <x-app.card>
            <div class="mb-4">
                <x-app.badge :label="$template->module ?? 'general'" type="success" />
            </div>
            <h3 class="heading-sm mb-3">{{ $template->name }}</h3>
            <p class="text-body mb-4">{{ $template->description }}</p>
            <div class="app-inline-actions">
                <x-app.badge :label="(string) $template->credit_cost . ' credits'" type="success" />
                <x-app.badge :label="(string) $template->model" type="success" />
            </div>
        </x-app.card>
    @empty
        <p class="app-empty">لا توجد قوالب منشورة بعد.</p>
    @endforelse
</section>
@endsection
