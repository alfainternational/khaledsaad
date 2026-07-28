@php($brief = $snapshot['agency_brief'])

<section class="agency-doc print-report">

@if ($brief['readiness']['legacy'] ?? false)
    <section class="card card--warn">
        <p>{{ $brief['readiness']['message'] }}</p>
    </section>
@endif

<section class="card print-section">
    <h2 class="section-title">المشروع في سطور واضحة</h2>
    <p><b>المشروع:</b> {{ $brief['project']['name'] }}</p>
    <p>{{ $brief['project']['description'] }}</p>
    <p><b>المجال:</b> {{ $brief['project']['industry'] ?: 'غير محدد' }}</p>
    <p><b>طريقة العمل:</b> {{ $brief['project']['business_model'] ?: 'غير محددة' }}</p>
    <p><b>السوق:</b> {{ $brief['project']['geography'] ?: 'غير محدد' }}</p>
    <p><b>المرحلة:</b> {{ $brief['project']['stage'] }}</p>
    <p><b>ما يميّز العرض:</b> {{ $brief['project']['value_proposition'] ?: 'لم تُكتب صياغته النهائية بعد' }}</p>
    @if ($brief['project']['website'])<p><b>الموقع:</b> {{ $brief['project']['website'] }}</p>@endif
    @if ($brief['project']['audiences'] !== [])<p><b>الجمهور:</b> {{ implode('، ', $brief['project']['audiences']) }}</p>@endif
    @foreach (($brief['project']['audience_details'] ?? []) as $audience)
        <article>
            <h3>{{ $audience['name'] }}</h3>
            <p><b>الحاجة أو المشكلة:</b> {{ $audience['needs'] ?: 'لم توثق بعد' }}</p>
            <p><b>النتيجة المطلوبة:</b> {{ $audience['desired_result'] ?: 'لم توثق بعد' }}</p>
            <p><b>السلوك المعروف:</b> {{ $audience['behaviour'] ?: 'لم يوثق بعد' }}</p>
        </article>
    @endforeach
    @if (($brief['project']['competitors'] ?? []) !== [])
        <h3>المنافسون المعروفون</h3>
        <ul class="bullets">
            @foreach ($brief['project']['competitors'] as $competitor)
                <li>
                    {{ $competitor['name'] }}
                    @if (! empty($competitor['tier_label'])) — {{ $competitor['tier_label'] }}@endif
                    @if ($competitor['url']) — {{ $competitor['url'] }}@endif
                </li>
            @endforeach
        </ul>
    @endif
    @if (($brief['project']['known_context'] ?? []) !== [])
        <h3>حقائق مسجلة عن المشروع</h3>
        @foreach ($brief['project']['known_context'] as $item)
            <p><b>{{ $item['label'] }}:</b> {{ $item['value'] }}</p>
        @endforeach
    @endif
</section>

<section class="card print-section print-section--long">
    <h2 class="section-title">خط الأساس</h2>
    <p class="muted">هذه هي نقطة البداية. أي رقم غير معروف يجب قياسه قبل الحكم على أثر العمل.</p>
    <div class="table-scroll">
        <table class="data-table print-table">
            <thead><tr><th>المعلومة</th><th>الوضع الحالي</th></tr></thead>
            <tbody>
                @forelse ($brief['baseline']['rows'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                @empty
                    <tr><td>الأرقام الحالية</td><td>غير معروفة حتى الآن؛ يبدأ العمل بتركيب القياس.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p><b>وضع القياس:</b> {{ $brief['baseline']['tracking'] }}</p>
    <p><b>ما جُرّب سابقًا:</b> {{ $brief['baseline']['previous_attempts'] ?: 'لا توجد تجربة سابقة موثقة' }}</p>
    @if ($brief['baseline']['previous_provider'])<p><b>التعامل السابق مع وكالة أو مستقل:</b> {{ $brief['baseline']['previous_provider'] }}</p>@endif
    <p><b>مصدر العملاء الحالي:</b> {{ $brief['baseline']['current_customer_source'] ?: 'غير معروف حتى الآن' }}</p>
    @if (($brief['baseline']['kpis'] ?? []) !== [])
        <h3>المؤشرات المتفق على متابعتها</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>المؤشر</th><th>خط البداية</th><th>الهدف</th><th>آخر قراءة</th></tr></thead>
                <tbody>
                    @foreach ($brief['baseline']['kpis'] as $kpi)
                        <tr>
                            <td>{{ $kpi['name'] }}</td>
                            <td>{{ $kpi['baseline'] ?? 'غير معروف' }} {{ $kpi['unit'] }}</td>
                            <td>{{ $kpi['target'] ?? 'غير محدد' }} {{ $kpi['unit'] }}</td>
                            <td>{{ $kpi['latest'] ?? 'لا توجد قراءة بعد' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card">
    <h2 class="section-title">الهدف الذي سنعمل عليه</h2>
    <p><b>الهدف الأساسي:</b> {{ $brief['goal']['primary'] }}</p>
    <p><b>تعريف النجاح:</b> {{ $brief['goal']['success_metric'] }}</p>
    @if ($brief['goal']['period'])<p><b>ما نريد تغييره خلال 90 يومًا:</b> {{ $brief['goal']['period'] }}</p>@endif
</section>

<section class="card">
    <h2 class="section-title">النطاق المطلوب</h2>
    <p><b>الخدمات المطلوبة:</b> {{ implode('، ', $brief['scope']['services']) }}</p>
    <p><b>موعد البدء أو الموسم المهم:</b> {{ $brief['scope']['start_window'] ?: 'يُحدد مع الجدول التنفيذي' }}</p>
    <p><b>القيود التي يجب احترامها:</b> {{ $brief['scope']['constraints'] ?: 'لا توجد قيود إضافية موثقة' }}</p>
    <h3>خارج النطاق ما لم يرد صراحة في العرض</h3>
    <ul class="bullets">@foreach ($brief['scope']['out_of_scope'] as $item)<li>{{ $item }}</li>@endforeach</ul>
</section>

<section class="card">
    <h2 class="section-title">الأصول والوصول</h2>
    <p class="muted">يوضح هذا الجدول ما يمكن بدء العمل عليه فورًا وما يحتاج تجهيزًا قبل الإطلاق.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الأصل أو الحساب</th><th>حالته</th></tr></thead>
            <tbody>
                @forelse (($brief['assets']['rows'] ?? []) as $asset)
                    <tr>
                        <td>{{ $asset['label'] }}</td>
                        <td>{{ $asset['status_label'] }}@if ($asset['detail']) — {{ $asset['detail'] }}@endif</td>
                    </tr>
                @empty
                    <tr><td>قائمة الأصول</td><td>لم تُوثق بعد؛ تُراجع قبل تحديد يوم البدء.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2 class="section-title">آلية العمل</h2>
    <p><b>صاحب القرار:</b> {{ $brief['workflow']['decision_maker'] ?: 'يُحدد قبل البدء' }}</p>
    <p><b>مدة الاعتماد:</b> {{ $brief['workflow']['approval_time'] ?: 'تُحدد قبل البدء' }}</p>
    <p><b>من يرد على العملاء:</b> {{ $brief['workflow']['lead_response_owner'] ?: 'يُحدد قبل البدء' }}</p>
    <p><b>فريق المشروع:</b> {{ $brief['workflow']['internal_capacity'] ?: 'يُحدد في اجتماع البدء' }}</p>
    <p><b>قيود الدفع للمنصات:</b> {{ $brief['workflow']['payment_constraints'] ?: 'لا توجد قيود موثقة' }}</p>
    <p><b>المراجعة:</b> {{ $brief['workflow']['review_cadence'] }}</p>
</section>

<section class="card">
    <h2 class="section-title">الملكية وشروط الانتهاء</h2>
    <p>{{ $brief['terms']['account_ownership'] }}</p>
    @if ($brief['terms']['declared_ownership'])<p><b>الوضع المعلن حاليًا:</b> {{ $brief['terms']['declared_ownership'] }}</p>@endif
    @if ($brief['terms']['engagement_model'])<p><b>شكل التعاقد المفضل:</b> {{ $brief['terms']['engagement_model'] }}</p>@endif
    @if ($brief['terms']['contract_duration'])<p><b>المدة المفضلة:</b> {{ $brief['terms']['contract_duration'] }}</p>@endif
    @if ($brief['terms']['budget_flexibility'])<p><b>مرونة الميزانية:</b> {{ $brief['terms']['budget_flexibility'] }}</p>@endif
    <p><b>عند انتهاء التعاقد:</b> {{ $brief['terms']['exit_condition'] }}</p>
</section>

<section class="card">
    <h2 class="section-title">ما يجب أن يتضمنه عرضكم</h2>
    @php($budget = $brief['proposal']['budget'])
    <h3>الميزانية التي سيُبنى عليها العرض</h3>
    <p>
        <b>المبلغ الشهري المسجل:</b>
        {{ $budget['stated_budget'] !== null ? number_format((float) $budget['stated_budget']) : 'لم يُحدد' }}
        {{ $budget['budget_currency'] ?? '' }}
    </p>
    <p><b>هل يشمل أتعاب الوكالة؟</b>
        @if ($budget['includes_agency_fee'] === true)
            نعم، المبلغ يشمل الأتعاب والإنفاق معًا.
        @elseif ($budget['includes_agency_fee'] === false)
            لا، المبلغ مخصص للإنفاق والأتعاب تضاف فوقه.
        @else
            لم تُحسم بعد.
        @endif
    </p>
    @if (($budget['effective_media'] ?? null) !== null)
        <p><b>المتاح للإعلان بعد البنود المحسوبة:</b> {{ number_format((float) $budget['effective_media']) }} {{ $budget['budget_currency'] ?? '' }}</p>
    @endif
    @if (! empty($budget['verdict']['headline']))
        <p><b>مدى ملاءمة المبلغ للنطاق:</b> {{ $budget['verdict']['headline'] }} — {{ $budget['verdict']['detail'] }}</p>
    @endif
    <ol class="bullets">@foreach ($brief['proposal']['requirements'] as $requirement)<li>{{ $requirement }}</li>@endforeach</ol>

    <h3>جدول التسعير المطلوب</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>البند</th><th>المبلغ</th><th>ما يشمله</th><th>ما لا يشمله</th></tr></thead>
            <tbody>
                @foreach ($brief['proposal']['pricing_rows'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td>يُملأ من الوكالة</td><td>يُملأ من الوكالة</td><td>يُملأ من الوكالة</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($brief['proposal']['evaluation_criteria'])<p><b>معيار الاختيار:</b> {{ $brief['proposal']['evaluation_criteria'] }}</p>@endif
</section>

<section class="card">
    <h2 class="section-title">موعد وطريقة تسليم العرض</h2>
    <p><b>آخر موعد:</b> {{ $brief['submission']['deadline'] }}</p>
    <p><b>طريقة التسليم:</b> {{ $brief['submission']['method'] ?: 'ملف PDF عبر وسيلة التواصل المعتمدة للمشروع.' }}</p>
</section>

</section>
