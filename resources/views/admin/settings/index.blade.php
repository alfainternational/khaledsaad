@extends('layouts.app')
@section('layout', 'form')

@section('title', 'الإعدادات والمفاتيح')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>الإعدادات والمفاتيح</h1>
            <p class="muted">أدخل المفاتيح هنا بدل ملفات النظام. أي تغيير يسري فورًا على كل مكان ذي صلة.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="form form--wide">
        @csrf
        @method('PUT')

        @foreach ($groups as $group)
            <section class="settings-group">
                <h2 class="section-title">{{ $group['group'] }}</h2>

                @foreach ($group['fields'] as $field)
                    @php $name = str_replace('.', '__', $field['key']); @endphp

                    <div class="field">
                        <span class="field__label">
                            {{ $field['label'] }}
                            @if ($field['is_overridden'])
                                <span class="badge badge--live">من اللوحة</span>
                            @else
                                <span class="badge">من النظام</span>
                            @endif
                        </span>

                        @if ($field['type'] === 'bool')
                            <label class="field--inline">
                                <input type="checkbox" name="{{ $name }}" value="1" @checked($field['current_bool'])>
                                <span>مفعّل</span>
                            </label>
                        @elseif ($field['type'] === 'secret')
                            <input type="password" name="{{ $name }}" autocomplete="new-password"
                                placeholder="{{ $field['display'] ?: 'غير مضبوط — أدخل المفتاح' }}">
                            @if ($field['is_overridden'])
                                <span class="field__help">محفوظ ومشفّر. اتركه فارغًا للإبقاء عليه، أو أدخل قيمة جديدة لاستبداله.</span>
                            @endif
                        @else
                            <input type="text" name="{{ $name }}" value="{{ $field['display'] }}" dir="ltr">
                        @endif

                        @if (! empty($field['hint']))
                            <span class="field__help">{{ $field['hint'] }}</span>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach

        <button type="submit" class="btn btn--primary">حفظ وتطبيق فورًا</button>
    </form>
@endsection
