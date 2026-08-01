@extends('layouts.app')
@section('layout', 'form')

@section('title', 'معلومات مشروعك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">معلومات مشروعك</p>
            <h1>{{ $project->name }}</h1>
            <p class="muted">
                هذه المعلومات تُستخدم في كل خطوة، فلا نسألك عنها مرة أخرى.
                عدّلها متى ما تغيّر شيء — والنتائج التي استلمتها سابقًا تبقى كما هي.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('app.projects.update', $project) }}" class="form form--wide form-layout question-form">
        @csrf
        @method('PUT')

        @php
            // القيم الحالية بمفاتيح الإعلان نفسها: الملف مصدرُ بعضها والمشروع مصدر بعضها.
            $values = [
                'name' => $project->name,
                'sector' => $project->sector,
                'industry' => $project->industry,
                'stage' => $project->stage,
                'description' => $project->profile?->description,
                'value_proposition' => $project->profile?->value_proposition,
                'geography' => $project->profile?->geography,
                'monthly_budget' => $project->profile?->monthly_budget,
            ];
        @endphp

        @foreach (\App\Modules\Intake\Assist\ProfileQuestions::fields() as $field)
            @include('app.projects._profile-field', [
                'field' => \App\Modules\Intake\Assist\ProfileQuestions::find($field['key']),
                'value' => $values[$field['key']] ?? null,
                'project' => $project,
            ])
        @endforeach

        <button type="submit" class="btn btn--primary">احفظ التعديلات</button>
    </form>

    @if (($known ?? []) !== [])
        {{-- كل ما كتبه المستخدم داخل الخطوات يظهر هنا في مكان واحد، لا يضيع داخل أداة. --}}
        <section class="card known-summary">
            <h2 class="section-title">معلومات محفوظة من تشخيصاتك</h2>
            <p class="muted">ستُستخدم هذه الإجابات في الخطوات المناسبة حتى لا تحتاج إلى إدخالها مرة أخرى.</p>

            <ul class="kv">
                @foreach ($known as $item)
                    <li>
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ is_array($item['value']) ? implode('، ', $item['value']) : $item['value'] }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
