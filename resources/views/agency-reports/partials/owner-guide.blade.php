{{--
    خاص بصاحب المشروع. لا يُدرج في الرابط المشترك ولا في PDF الوكالة:
    هذه أوراقك في التفاوض، وعرضها على الطرف الآخر يُفقدها معناها.
--}}

@php($guide = $snapshot['owner_guide'] ?? null)

@if ($guide)
    @php($budget = $guide['budget'])

    <section class="card card--warn">
        <h2 class="section-title">قبل أن ترسل: هل ميزانيتك تكفي هذا النطاق؟</h2>

        <p><b>{{ $budget['verdict']['headline'] }}</b> — {{ $budget['verdict']['detail'] }}</p>

        @if (! empty($budget['verdict']['gap']))
            <p class="evidence">
                الفارق المطلوب تقريبًا: {{ number_format((float) $budget['verdict']['gap']) }}
                {{ $budget['market']['currency_label'] }} شهريًا.
            </p>
        @endif

        @if ($budget['breakdown']['mode'] === 'undeclared' && $budget['stated_budget'] !== null)
            <p class="muted">
                إذا كان المبلغ شاملًا للأتعاب، يتبقى للإعلان نحو
                <b>{{ number_format((float) $budget['breakdown']['if_inclusive_media']) }}</b>
                {{ $budget['market']['currency_label'] }}.
                وإذا كان مخصصًا للإعلان فقط، تصبح التكلفة الشهرية الإجمالية نحو
                <b>{{ number_format((float) $budget['breakdown']['if_media_only_total']) }}</b>.
                الفرق بين المعنيين هو الفرق بين خطة تعمل وخطة تتوقف بعد شهر.
            </p>
        @endif

        <h3>تكاليف تقريبية في سوق {{ $budget['market']['label'] }}</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr><th>البند</th><th>من</th><th>إلى</th><th>الوحدة</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>أتعاب الإدارة — {{ $budget['tier']['label'] }}</td>
                        <td>{{ number_format((float) $budget['reference']['agency_fee']['min']) }}</td>
                        <td>{{ number_format((float) $budget['reference']['agency_fee']['max']) }}</td>
                        <td>شهريًا</td>
                    </tr>
                    <tr>
                        <td>تأسيس وإعداد أول مرة</td>
                        <td>{{ number_format((float) $budget['reference']['setup_once']['min']) }}</td>
                        <td>{{ number_format((float) $budget['reference']['setup_once']['max']) }}</td>
                        <td>مرة واحدة</td>
                    </tr>
                    <tr>
                        <td>إنتاج محتوى وتصوير</td>
                        <td>{{ number_format((float) $budget['reference']['production_monthly']['min']) }}</td>
                        <td>{{ number_format((float) $budget['reference']['production_monthly']['max']) }}</td>
                        <td>شهريًا</td>
                    </tr>
                    <tr>
                        <td>أدوات واشتراكات</td>
                        <td>{{ number_format((float) $budget['reference']['tools_monthly']['min']) }}</td>
                        <td>{{ number_format((float) $budget['reference']['tools_monthly']['max']) }}</td>
                        <td>شهريًا</td>
                    </tr>
                    <tr>
                        <td>أقل إنفاق إعلاني مجدٍ للقناة الواحدة</td>
                        <td colspan="2">{{ number_format((float) $budget['reference']['media_floor_per_channel']) }}</td>
                        <td>شهريًا</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="muted">
            كل الأرقام بـ{{ $budget['market']['currency_label'] }}. {{ $budget['disclaimer'] }}
        </p>

        @foreach ($budget['market']['notes'] as $note)
            <p class="muted">— {{ $note }}</p>
        @endforeach
    </section>

    <section class="card">
        <h2 class="section-title">أسئلة مقارنة عروض الوكالات</h2>
        <p class="muted">اطرحها على كل وكالة بنفس الترتيب، ودوّن الإجابات في جدول واحد.</p>
        <ol class="bullets">
            @foreach ($guide['comparison_questions'] as $question)<li>{{ $question }}</li>@endforeach
        </ol>
    </section>

    <section class="card">
        <h2 class="section-title">علامات إنذار في العروض</h2>
        <ul class="bullets">
            @foreach ($guide['red_flags'] as $flag)<li>{{ $flag }}</li>@endforeach
        </ul>
    </section>

    <section class="card">
        <h2 class="section-title">ما لا تتنازل عنه مهما كان العرض</h2>
        <ul class="bullets">
            @foreach ($guide['non_negotiables'] as $item)<li>{{ $item }}</li>@endforeach
        </ul>
    </section>
@endif
