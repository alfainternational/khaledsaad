{{--
    بطاقة القرار: الصفحة الأولى وحدها. من يقرر قبول العميل لا يقرأ تسع صفحات.

    لا معلومة هنا غير موجودة في المستند — كلها اشتقاق، فلا يمكن أن تتناقض
    البطاقة مع تفاصيله. الوسيط $print يفرّق بين طباعة PDF وعرض الشاشة.
--}}

@php($card = $snapshot['decision_card'] ?? null)

@if ($card)
    @php($print = $print ?? false)

    <div class="{{ $print ? 'card' : 'card card--decision' }}">
        <p class="{{ $print ? 'small' : 'eyebrow' }}">بطاقة القرار · تُقرأ في تسعين ثانية</p>
        <h2 class="section-title">{{ $card['identity']['project'] }}</h2>

        <p class="muted">
            {{ collect([
                $card['identity']['industry'],
                $card['identity']['geography'],
                $card['identity']['business_model'],
                $card['identity']['stage'],
            ])->filter()->implode(' · ') }}
        </p>

        <p>{{ $card['readiness']['score'] }}</p>

        @if ($card['readiness']['trend'])
            @php($trend = $card['readiness']['trend'])
            <p class="muted">
                الاتجاه: {{ $trend['direction_label'] }}
                (من {{ $trend['from'] }} إلى {{ $trend['to'] }} خلال {{ $trend['days'] }} يومًا،
                {{ $trend['measurements'] }} قياسات).
            </p>
        @else
            <p class="muted">قياس واحد فقط حتى الآن — لا يمكن الحكم على الاتجاه، ركودًا كان أم صعودًا.</p>
        @endif

        <table class="{{ $print ? '' : 'data-table' }}">
            <tbody>
                <tr>
                    <th>ما يصل إلى الإعلان</th>
                    <td>
                        {{--
                            سببان مختلفان للفراغ، ولا يجوز الخلط بينهما: الحجب
                            قرار صاحب المشروع، وعدم الحساب نقص في البيانات
                            يُحسم بسؤال. نسبة الأول إلى الثاني تضليل.
                        --}}
                        @if ($card['money']['mode'] !== 'full')
                            غير معروض في هذه النسخة بطلب صاحب المشروع
                        @elseif ($card['money']['effective_media'] === null)
                            لم يُحسم بعد: الميزانية أو ما إذا كانت تشمل أتعاب الإدارة
                        @else
                            {{ number_format((float) $card['money']['effective_media']) }} شهريًا بعد الأتعاب
                            @if (! empty($card['money']['verdict']) && $card['money']['verdict']['level'] !== 'sufficient')
                                — {{ $card['money']['verdict']['headline'] }}
                            @endif
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>تعريف النجاح</th>
                    <td>{{ $card['success_metric'] ?: 'لم يُكتب بعد — يُحسم قبل التسعير' }}</td>
                </tr>
                <tr>
                    <th>نطاق الخدمات</th>
                    <td>{{ $card['scope_declared'] ? 'محدد في قسم التكليف' : 'غير محدد بعد' }}</td>
                </tr>
                <tr>
                    <th>ما نعرفه فعلًا</th>
                    <td>
                        معرفة {{ $card['coverage']['knowledge_percent'] }}٪ ·
                        أرقام {{ $card['coverage']['numbers_known'] }} من {{ $card['coverage']['numbers_total'] }} ·
                        أصول مصرّح بها {{ $card['coverage']['assets_percent'] }}٪
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>ثلاث إشارات</h3>
        <ul class="bullets">
            <li><b>أعلى فرصة:</b> {{ $card['signals']['opportunity'] ?: 'لم تُرشَّح فرصة بعد' }}</li>
            <li><b>أكبر خطر:</b> {{ $card['signals']['risk'] ?: 'لم تُسجَّل مشكلة ذات خطورة' }}</li>
            <li><b>أكبر مجهول:</b> {{ $card['signals']['unknown'] ?: 'لا مجهول جوهري مسجّل' }}</li>
        </ul>
    </div>
@endif
