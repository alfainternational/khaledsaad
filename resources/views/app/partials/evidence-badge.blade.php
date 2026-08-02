{{--
    وسم تدرّج الدليل (§٤.١) — مفردة واحدة لا رابع لها.

    الوسم يظهر على `inferred` وحده لأن ما يحتاج تنبيهًا هو ما قد يُقرأ
    كحقيقة وهو ليس كذلك. المقيس والمحسوب يعرضان أساسهما بدل الوسم.

    @param \App\Modules\Shared\Evidence\EvidenceLevel $level
    @param string|null $note  سبب كون المخرج فرضية — جملة قصيرة لا فقرة
--}}
@if ($level->needsAssumptionBadge())
    <span class="badge badge--assumption">{{ $level->label() }}</span>
    @if (! empty($note))
        <span class="muted evidence-note">{{ $note }}</span>
    @endif
@endif
