{{--
    مساحة عمل شخصية واحدة: ما يهمها، مثال البداية، لماذا تناسبها، ثم المحرر.

    لا يظهر هنا تقييم شخصية أخرى ولا رسالتها — التبويب مغلق على صاحبته
    حتى لا يتسرّب اعتراض غيرها إلى صياغتها.

    @param \App\Models\Project $project
    @param array $tab   key, persona, profile, current, history
--}}
@php($persona = $tab['persona'])
@php($current = $tab['current'])
@php($profile = $tab['profile'])

<div class="studio-grid">
    <div class="studio-grid__side">
        @include('app.partials.persona-card', ['persona' => $persona, 'compact' => false])

        <div class="card">
            <p class="eyebrow">ما يجب تجنّبه معها</p>
            <p class="muted">{{ $profile['avoid'] }}</p>
        </div>
    </div>

    <div class="studio-grid__main">
        @if ($current?->teaching_note)
            <div class="card">
                <p class="eyebrow">لماذا تناسبها هذه الصياغة</p>
                <p>{{ $current->teaching_note }}</p>
                @if ($current->reusable_formula)
                    <p class="muted"><strong>القالب:</strong> {{ $current->reusable_formula }}</p>
                @endif
            </div>
        @endif

        <div class="card">
            <div class="studio-head">
                <p class="eyebrow">
                    رسالتها — {{ $channel->label() }} · {{ $objective->label() }}
                    <span class="badge">{{ \App\Support\Messaging\MessageStatus::label($current?->status) }}</span>
                </p>
                <form method="POST" action="{{ route('app.messages.suggest', $project) }}">
                    @csrf
                    <input type="hidden" name="persona_key" value="{{ $tab['key'] }}">
                    <input type="hidden" name="channel" value="{{ $channel->value }}">
                    <input type="hidden" name="objective" value="{{ $objective->value }}">
                    <button type="submit" class="btn btn--ghost btn--sm" data-once>اقترح لي</button>
                </form>
            </div>

            @if ($current)
                @php($messageId = 'variant-'.$current->id)
                <blockquote id="{{ $messageId }}" class="persona-message">{{ $current->content }}</blockquote>
                <div class="studio-actions">
                    <button type="button" class="btn btn--ghost btn--sm" data-copy-message="{{ $messageId }}">
                        انسخ الرسالة
                    </button>

                    <form method="POST" action="{{ route('app.messages.test', $project) }}">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $current->id }}">
                        <button type="submit" class="btn btn--primary btn--sm" data-once>اختبرها وحدها</button>
                    </form>

                    @if ($current->status !== \App\Models\MessageVariant::STATUS_APPROVED)
                        <form method="POST" action="{{ route('app.messages.status', [$project, $current]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="btn btn--ghost btn--sm">اعتمد</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('app.messages.status', [$project, $current]) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="archived">
                        <button type="submit" class="btn btn--ghost btn--sm">أرشف</button>
                    </form>
                </div>
            @else
                <p class="muted">لا رسالة لهذه الشخصية على هذه القناة بعد — اقترح لك، أو اكتبها بنفسك.</p>
            @endif
        </div>

        {{-- التحرير يُنشئ إصدارًا جديدًا دائمًا: ما اختُبر لا يُكتب فوقه. --}}
        <div class="card">
            <p class="eyebrow">{{ $current ? 'اكتب إصدارًا جديدًا' : 'اكتب بنفسك' }}</p>
            <form method="POST" action="{{ route('app.messages.store', $project) }}" class="stack">
                @csrf
                <input type="hidden" name="persona_key" value="{{ $tab['key'] }}">
                <input type="hidden" name="channel" value="{{ $channel->value }}">
                <input type="hidden" name="objective" value="{{ $objective->value }}">
                @if ($current)
                    <input type="hidden" name="parent_id" value="{{ $current->id }}">
                @endif
                <textarea name="content" rows="4" required minlength="20"
                    maxlength="{{ $channel->maxLength() }}"
                    aria-label="رسالة {{ $persona['name'] }}"
                    placeholder="اكتب رسالة {{ $persona['name'] }} — {{ $channel->hint() }}">{{ old('content') }}</textarea>
                <button type="submit" class="btn btn--ghost btn--sm">احفظ الإصدار</button>
            </form>
        </div>

        @if ($tab['history']->count() > ($current ? 1 : 0))
            <details class="card">
                <summary>سجل إصداراتها ({{ $tab['history']->count() }})</summary>
                <ul class="pulse-items">
                    @foreach ($tab['history'] as $variant)
                        <li class="pulse-item">
                            <strong>
                                {{ \App\Support\Messaging\MessageStatus::label($variant->status) }}
                                · {{ \App\Support\Messaging\MessageChannel::from($variant->channel)->label() }}
                                @if ($variant->results->isNotEmpty())
                                    <span class="score-chip">{{ $variant->results->first()->score }}/100</span>
                                @endif
                            </strong>
                            <p class="muted">{{ $variant->content }}</p>
                            <p class="eyebrow">{{ $variant->created_at->translatedFormat('j F Y — H:i') }}</p>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
