{{--
    بطاقة شخصية واحدة — مصدر واحد للوحة الجمهور والاستوديو معًا.

    مقسومة كما تُستعمل: كتلة استهداف تُنقل حرفيًّا إلى لوحة الإعلان،
    وكتلة رسالة تُبنى عليها الصياغة. ما لم يُحدَّد يظهر «غير محدد» ولا يُخفى.

    @param array  $persona
    @param bool   $compact      يخفي الاقتباس والأوجاع في السياقات الضيقة
    @param mixed  $suggestFor   المشروع — يُمرَّر ليظهر زر توليد الرسالة
    @param string $personaKey   مفتاح الشخصية، إلزامي مع suggestFor
--}}
@php($compact = $compact ?? false)
@php($suggestFor = $suggestFor ?? null)

<article class="card persona-card">
    <p class="eyebrow">
        {{ $persona['age_range'] ?? 'غير محدد' }} · {{ $persona['gender'] ?? 'الجنسان' }}
        @include('app.partials.evidence-badge', [
            'level' => \App\Modules\Shared\Evidence\EvidenceLevel::Inferred,
        ])
    </p>
    <h3>{{ \App\Support\Messaging\PersonaName::display($persona['name'] ?? null) }}</h3>
    <p class="muted">{{ $persona['role'] ?? '' }}</p>

    @if (! $compact && ! empty($persona['quote']))
        <blockquote class="persona-quote">«{{ $persona['quote'] }}»</blockquote>
    @endif

    <dl class="persona-facts">
        @if (! empty($persona['locations']))
            <div><dt>المدن</dt><dd>{{ implode('، ', (array) $persona['locations']) }}</dd></div>
        @endif
        @if (! empty($persona['interests']))
            <div><dt>الاهتمامات</dt><dd>{{ implode('، ', (array) $persona['interests']) }}</dd></div>
        @endif
        @if (! empty($persona['platforms']))
            <div><dt>المنصات</dt><dd>{{ implode('، ', (array) $persona['platforms']) }}</dd></div>
        @endif
        @if (! empty($persona['spending_level']))
            <div><dt>مستوى الإنفاق</dt><dd>{{ $persona['spending_level'] }}</dd></div>
        @endif
    </dl>

    @if (! $compact && ! empty($persona['pains']))
        <ul class="bullets">
            @foreach (array_slice((array) $persona['pains'], 0, 3) as $pain)
                <li>{{ $pain }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($persona['motivation']))
        <p class="muted"><strong>يشتري لأنه:</strong> {{ $persona['motivation'] }}</p>
    @endif
    @if (! empty($persona['objection']))
        <p class="persona-objection">يتردد لأنه: {{ $persona['objection'] }}</p>
    @endif
    @if (! empty($persona['buying_style']))
        <p class="muted"><strong>أسلوب الشراء:</strong> {{ $persona['buying_style'] }}</p>
    @endif
    @if (! empty($persona['tone']))
        <p class="muted"><strong>النبرة التي تصله:</strong> {{ $persona['tone'] }}</p>
    @endif

    {{-- الزر هنا لأن القرار يُتخذ أمام البطاقة: من قرأ اعتراضها يريد ردًّا عليه الآن. --}}
    @if ($suggestFor && ! empty($personaKey))
        <form method="POST" action="{{ route('app.messages.suggest', $suggestFor) }}">
            @csrf
            <input type="hidden" name="persona_key" value="{{ $personaKey }}">
            <input type="hidden" name="channel" value="ad">
            <input type="hidden" name="objective" value="attention">
            <button type="submit" class="btn btn--ghost btn--sm" data-once>ولّد رسالتها المقترحة</button>
        </form>
    @endif
</article>
