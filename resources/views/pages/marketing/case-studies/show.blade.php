@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
<section class="section-lg internal-page-hero">
    <div class="site-container max-w-3xl">
        <p class="text-sm text-muted mb-3"><a href="{{ route('case-studies.index') }}" class="hover:underline">دراسات الحالة</a></p>
        <h1 class="heading-lg mb-2">{{ $study->title }}</h1>
        <p class="text-body text-muted mb-8">{{ $study->client_name }} @if($study->industry) · {{ $study->industry }} @endif</p>
        @if($study->cover_image)
            <div class="mb-8 rounded-xl overflow-hidden border border-[var(--border)]">
                <img src="{{ asset('storage/'.$study->cover_image) }}" alt="" class="w-full object-cover">
            </div>
        @endif
        <p class="text-body-lg mb-8">{{ $study->summary }}</p>
        <div class="marketing-cms-body text-body leading-relaxed space-y-4">
            {!! $study->body_html !!}
        </div>
        <div class="mt-10">
            <a href="{{ route('case-studies.index') }}" class="btn btn-secondary">← العودة لدراسات الحالة</a>
        </div>
    </div>
</section>
@endsection
