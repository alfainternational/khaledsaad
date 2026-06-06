@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
<section class="section-lg internal-page-hero">
    <div class="site-container max-w-3xl">
        <p class="text-sm text-muted mb-3"><a href="{{ route('community.index') }}" class="hover:underline">المجتمع</a></p>
        <h1 class="heading-lg mb-4">{{ $post->title }}</h1>
        <p class="text-sm text-muted mb-8">{{ optional($post->published_at)->translatedFormat('d M Y') }} @if($post->author_display_name) · {{ $post->author_display_name }} @endif</p>
        <div class="marketing-cms-body text-body leading-relaxed space-y-4">
            {!! $post->body_html !!}
        </div>
        <div class="mt-10">
            <a href="{{ route('community.index') }}" class="btn btn-secondary">← العودة للمجتمع</a>
        </div>
    </div>
</section>
@endsection
