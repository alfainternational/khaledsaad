@props(['recommendation', 'print' => false])
@if ($recommendation['degraded'] ?? false)
    <div class="recommendation-contract recommendation-contract--missing" role="note">
        <strong>{{ __('هذه التوصية غير جاهزة للتنفيذ') }}</strong>
        <p>{{ __('لا يوجد قالب مطابق ومكتمل لهذه التوصية بعد؛ لذلك لم تُعرض كإجراء.') }}</p>
    </div>
@else
    <div class="recommendation-contract">
        <dl class="recommendation-contract__grid">
            <div><dt>{{ __('الناتج') }}</dt><dd>{{ $recommendation['deliverable'] }}</dd></div>
            <div><dt>{{ __('تعريف الإنجاز') }}</dt><dd>{{ $recommendation['done_when'] }}</dd></div>
            <div><dt>{{ __('أول خمس دقائق') }}</dt><dd>{{ $recommendation['first_five_minutes'] }}</dd></div>
            <div><dt>{{ __('الفشل المتوقع ومخرجه') }}</dt><dd>{{ $recommendation['expected_failure'] }}</dd></div>
        </dl>

        @if (! empty($recommendation['template']))
            @php
                $template = $recommendation['template'];
                $gaps = $template['gaps'] ?? [];
            @endphp

            <section class="recommendation-template recommendation-template--{{ $template['kind'] ?? 'checklist' }}">
                <header>
                    <strong>{{ $template['title'] }}</strong>
                    @if ($template['is_hypothesis'] ?? false)<span class="badge">{{ __('فرضية') }}</span>@endif
                </header>

                <dl class="recommendation-template__blocks">
                    @foreach ($template['blocks'] ?? [] as $block)
                        <div>
                            <dt>{{ $block['label'] ?? '' }}</dt>
                            <dd>{{ $block['value'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if (($template['tips'] ?? []) !== [])
                    <ul class="recommendation-template__tips">
                        @foreach ($template['tips'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                @endif

                {{--
                    الورقة الناقصة تخرج ناقصةً معلنة النقص، لا تُحجب. حجبها كان
                    يترك صاحبها بلا ورقة وبلا سبب؛ وإعلانها يقول له بالضبط أي
                    سؤال يملأ أي فراغ.
                --}}
                @if ($gaps !== [])
                    <p class="recommendation-template__gaps">
                        {{ __('فراغات هذه الورقة تُملأ بإجاباتك عن:') }}
                        {{ collect($gaps)->pluck('label')->implode('، ') }}
                    </p>
                @endif

                @unless ($print)
                    <button type="button" class="btn btn--ghost btn--sm" data-copy-template>{{ __('نسخ القالب') }}</button>
                @endunless
            </section>
        @else
            <p class="recommendation-contract__missing">{{ __('لا يوجد قالب مطابق لهذه التوصية بعد.') }}</p>
        @endif
    </div>
@endif
