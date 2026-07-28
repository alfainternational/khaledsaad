@php
    $owner = $snapshot['owner_report'];
    $details = $owner['private_details'];
    $highlightedProblemTitles = collect($owner['problems'])->pluck('title');
    $humanAnswer = static function (mixed $value): string {
        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->map(fn ($item) => is_bool($item) ? ($item ? 'نعم' : 'لا') : trim((string) $item))
                ->filter(fn (string $item) => $item !== '')
                ->implode('، ');
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        return trim((string) ($value ?? ''));
    };
@endphp

<section class="card">
    <p class="eyebrow">الصورة المختصرة</p>
    <h2 class="section-title">{{ $owner['overview']['title'] }}</h2>
    <p>{{ $owner['overview']['description'] }}</p>
    <p class="evidence"><b>أكبر نقطة تحتاج انتباهك الآن:</b> {{ $owner['overview']['main_issue'] }}</p>
</section>

<section class="card">
    <h2 class="section-title">صورة مشروعك الكاملة</h2>
    <p><b>المشروع:</b> {{ $details['project']['name'] }}</p>
    <p><b>المجال:</b> {{ $details['project']['industry'] ?: 'لم تحدده بعد' }}</p>
    <p><b>مرحلتك الآن:</b> {{ $details['project']['stage'] ?: 'لم تحددها بعد' }}</p>
    <p><b>السوق الذي تعمل فيه:</b> {{ $details['project']['geography'] ?: 'لم تحدده بعد' }}</p>
    <p><b>طريقة تحقيق الدخل:</b> {{ $details['project']['business_model'] ?: 'لم تحددها بعد' }}</p>
    <p><b>الهدف الذي يقود عملك:</b> {{ $details['project']['primary_goal'] ?: 'يحتاج إلى حسم' }}</p>
    <p><b>لماذا يختارك العميل؟</b> {{ $details['project']['value_proposition'] ?: 'تحتاج إلى صياغة سبب واضح لاختيارك' }}</p>
    @if ($details['project']['website'])<p><b>الموقع:</b> {{ $details['project']['website'] }}</p>@endif
</section>

<section class="card">
    <h2 class="section-title">عملاؤك كما نفهمهم الآن</h2>
    <p class="muted">نوضح من تحاول الوصول إليه، وما الذي يزعجه، وما النتيجة التي يبحث عنها. إذا كانت خانة ناقصة فهذه دعوة لسؤال العملاء، وليست معلومة سنخمنها.</p>
    @forelse ($details['audiences'] as $audience)
        <article>
            <h3>{{ $audience['name'] }}</h3>
            <p><b>ما الذي يزعجهم؟</b> {{ $audience['pains'] ?: 'لا توجد إجابة كافية بعد' }}</p>
            <p><b>ما الذي يريدون الوصول إليه؟</b> {{ $audience['gains'] ?: 'لا توجد إجابة كافية بعد' }}</p>
            <p><b>كيف يتصرفون عادة؟</b> {{ $audience['behaviors'] ?: 'نحتاج إلى ملاحظتهم أو سؤالهم' }}</p>
        </article>
    @empty
        <p>لم تُحدد شرائح عملائك بعد. ابدأ بخمس محادثات قصيرة مع عملاء حاليين أو محتملين.</p>
    @endforelse
</section>

<section class="card">
    <h2 class="section-title">أرقامك ببساطة</h2>
    <p class="muted">هذه الأرقام لا تحكم على مشروعك؛ هي فقط تخبرك بما نعرفه الآن وما يحتاج إلى قياس.</p>
    @if (! empty($owner['numbers']['rows']))
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ما الذي نقيسه؟</th><th>ماذا نعرف؟</th><th>ماذا يعني ذلك لك؟</th></tr></thead>
                <tbody>
                    @foreach ($owner['numbers']['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['value'] !== null ? $row['value'].' '.($row['unit'] ?? '') : 'لا نعرفه حتى الآن' }}</td>
                            <td>{{ $row['confidence_label'] ?? 'نحتاج إلى تثبيت مصدر هذا الرقم.' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p>لا توجد أرقام كافية بعد. أول خطوة صحيحة هي تركيب القياس قبل زيادة الإنفاق.</p>
    @endif
</section>

<section class="card">
    <h2 class="section-title">ما لديك جاهز وما يحتاج تجهيزًا</h2>
    <p class="muted">هذا الجزء يمنعك من دفع المال على حملة قبل أن تصبح الحسابات والمواد الضرورية جاهزة.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>الحساب أو الأصل</th><th>وضعه الآن</th></tr></thead>
            <tbody>
                @forelse (($details['assets']['rows'] ?? []) as $asset)
                    <tr><td>{{ $asset['label'] }}</td><td>{{ $asset['status_label'] }}@if ($asset['detail']) — {{ $asset['detail'] }}@endif</td></tr>
                @empty
                    <tr><td>الأصول والحسابات</td><td>لم توثقها بعد؛ راجعها قبل بدء أي إنفاق.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2 class="section-title">أين يتوقف الناس؟</h2>
    <p>{{ $owner['journey']['description'] }}</p>
    <p class="muted"><b>وضع القياس الآن:</b> {{ $owner['numbers']['tracking_label'] ?? 'لا توجد قراءة مؤكدة بعد.' }}</p>
</section>

<section>
    <h2 class="section-title">ماذا قالت كل التشخيصات؟</h2>
    <p class="muted">جمعنا النتائج هنا حتى لا تبقى كل نتيجة في صفحة منفصلة. نعرض معنى النتيجة وما الذي تحتاجه، من دون رموز داخلية.</p>
    @foreach ($details['tools'] as $tool)
        <article class="card">
            <h3>{{ $tool['title'] }}</h3>
            <p>{{ $tool['summary'] ?: 'لم تُكتب خلاصة كافية لهذه النتيجة بعد.' }}</p>
            @if (($tool['scored'] ?? false) && $tool['score'] !== null)
                <p><b>القراءة الحالية:</b> {{ $tool['score'] }} من 100 — {{ $tool['score_band'] }}</p>
            @endif
            @foreach (($tool['findings'] ?? []) as $finding)
                @continue($highlightedProblemTitles->contains($finding['title']))
                <div class="finding">
                    <h4>{{ $finding['title'] }}</h4>
                    <p>{{ $finding['description'] }}</p>
                    @if ($finding['evidence'])<p class="evidence"><b>ما الذي يدعمها؟</b> {{ $finding['evidence'] }}</p>@endif
                    <p class="muted"><b>مدى اعتمادنا عليها:</b> {{ $finding['is_assumption'] ? 'تحتاج إلى تحقق إضافي' : 'تستند إلى معلومة مسجلة' }}</p>
                </div>
            @endforeach
        </article>
    @endforeach
</section>

<section>
    <h2 class="section-title">أهم ثلاث مشكلات</h2>
    <p class="muted">رتبناها لك حتى لا تتشتت بين عشرات الملاحظات. كل مشكلة تظهر هنا مرة واحدة فقط.</p>
    @forelse ($owner['problems'] as $problem)
        <article class="finding">
            <h3>{{ $problem['title'] }}</h3>
            <p>{{ $problem['description'] }}</p>
            @if (! empty($problem['evidence']))<p class="evidence">ما الذي أوصلنا لهذه النتيجة؟ {{ $problem['evidence'] }}</p>@endif
        </article>
    @empty
        <p class="muted">لا توجد ثلاث مشكلات مؤكدة بعد. أكمل القياس أولًا حتى لا نبني حكمًا على تخمين.</p>
    @endforelse
</section>

<section class="card">
    <h2 class="section-title">المؤشرات التي تتابعها</h2>
    <p class="muted">خط البداية هو الرقم قبل تنفيذ أي تغيير. بدونه لن تعرف هل تحسن العمل فعلًا.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>المؤشر</th><th>البداية</th><th>الهدف</th><th>آخر قراءة</th></tr></thead>
            <tbody>
                @forelse ($details['kpis'] as $kpi)
                    <tr>
                        <td>{{ $kpi['name'] }}</td>
                        <td>{{ $kpi['baseline'] ?? 'غير مسجل' }} {{ $kpi['unit'] }}</td>
                        <td>{{ $kpi['target'] ?? 'غير مسجل' }} {{ $kpi['unit'] }}</td>
                        <td>{{ $kpi['latest'] ?? 'لا توجد قراءة بعد' }}</td>
                    </tr>
                @empty
                    <tr><td>المؤشرات</td><td colspan="3">لم تسجل مؤشرًا بعد. اختر مؤشرًا واحدًا يرتبط بهدفك وثبت رقمه الحالي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2 class="section-title">المنافسون والمعلومات التي بُني عليها التقرير</h2>
    @if (($details['competitors']['items'] ?? []) !== [])
        <h3>المنافسون المسجلون</h3>
        <ul class="bullets">
            @foreach ($details['competitors']['items'] as $competitor)
                <li>{{ $competitor['name'] }}@if ($competitor['url']) — {{ $competitor['url'] }}@endif</li>
            @endforeach
        </ul>
    @else
        <p>لم تسجل منافسين مؤكدين بعد. هذا لا يعني عدم وجودهم؛ يعني فقط أننا لم نوثقهم.</p>
    @endif
    <p><b>حجم المعلومات الداعمة:</b> {{ $details['evidence']['count'] ?? 0 }} معلومة مسجلة. ستجد كل معلومة بجوار النتيجة التي تدعمها، حتى لا تتكرر عليك في أكثر من موضع.</p>
    @if ($details['assumptions'] !== [])
        <h3>أمور تحتاج إلى تأكيد</h3>
        <ul class="bullets">@foreach ($details['assumptions'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    @endif
</section>

@if (! empty($details['consultation']))
    <section class="card">
        <h2 class="section-title">ما سجلته في التشخيص الذكي</h2>
        <p class="muted">هذه الإجابات والقراءات جزء من الصورة نفسها، وليست تقريرًا منفصلًا عنها.</p>
        @foreach (($details['consultation']['answers'] ?? []) as $answer)
            @php($answerText = $humanAnswer($answer['value'] ?? null))
            <p><b>{{ $answer['question'] }}</b><br>{{ $answer['is_unknown'] ? 'أجبت بأنك لا تعرفها بعد' : ($answerText !== '' ? $answerText : 'لم تسجل إجابة') }}</p>
        @endforeach
        @foreach (($details['consultation']['inferences'] ?? []) as $inference)
            <p><b>قراءة مستخلصة من إجاباتك:</b> {{ $inference['statement'] }}</p>
        @endforeach
        @foreach (($details['consultation']['evidence'] ?? []) as $item)
            <p><b>معلومة داعمة: {{ $item['name'] }}</b>@if ($item['text'])<br>{{ $item['text'] }}@endif</p>
        @endforeach
    </section>
@endif

@if (($details['different_readings'] ?? []) !== [])
    <section class="card">
        <h2 class="section-title">مقارنة نتائج التشخيصات</h2>
        <p class="muted">عندما تقرأ التشخيصات الموضوع نفسه بطريقتين، نعرض الاختلاف هنا لتعرف ما يحتاج إلى حسم.</p>
        @foreach ($details['different_readings'] as $reading)
            <p><b>اختلاف يحتاج حسمًا:</b> {{ $reading['resolution'] ?? 'راجع السياق قبل اعتماد قراءة واحدة.' }}</p>
        @endforeach
    </section>
@endif

<section class="card">
    <h2 class="section-title">أمور تحتاج أن تحسمها</h2>
    <p class="muted">ليست أخطاء؛ هي إجابات يمكن فهمها بطريقتين، وحسمها يجعل قرارك أوضح.</p>
    @forelse ($owner['conflicts'] as $conflict)
        <article><h3>{{ $conflict['question'] }}</h3><p>{{ $conflict['why'] }}</p></article>
    @empty
        <p>لا توجد إجابات متعارضة تحتاج تدخلك الآن.</p>
    @endforelse
</section>

<section class="card">
    <h2 class="section-title">ما الذي ما زلنا لا نعرفه؟</h2>
    <p class="muted">وضحنا لك كيف تُغلق كل فجوة، بدل أن نتركها كقائمة غامضة.</p>
    @forelse ($owner['unknowns'] as $unknown)
        <p><b>{{ $unknown['resolution'] }}:</b> {{ $unknown['text'] }}</p>
    @empty
        <p>المعلومات الأساسية متاحة. أي نقص جديد سيظهر هنا بوضوح.</p>
    @endforelse
</section>

<section>
    <h2 class="section-title">ما يمكنك فعله هذا الأسبوع</h2>
    <p class="muted">خمس خطوات كحد أقصى، حتى تبدأ بشيء يمكن إنجازه بدل خطة طويلة لا تتحرك.</p>
    <div class="card-grid card-grid--prose">
        @forelse ($owner['this_week'] as $item)
            <article class="card">
                <p class="eyebrow">الوقت المتوقع: {{ $item['estimated_time'] }}</p>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['description'] }}</p>
            </article>
        @empty
            <p class="muted">لا توجد خطوة موثقة بعد. ارجع إلى قسم المعلومات الناقصة وابدأ بأول بند.</p>
        @endforelse
    </div>
</section>

<section>
    <h2 class="section-title">قبل أن تتحدث مع أي وكالة</h2>
    <p class="muted">هذا الجزء لك وحدك: يساعدك على فهم التكلفة، ومقارنة العروض، وحماية حساباتك وبياناتك.</p>
    @include('agency-reports.partials.owner-guide', ['snapshot' => $snapshot])
</section>

<section class="card {{ $owner['readiness']['is_ready'] ? 'card--link' : 'card--warn' }}">
    <p class="eyebrow">الخطوة التالية</p>
    <h2 class="section-title">هل أصبح موجز الوكالة جاهزًا؟</h2>
    <p>{{ $owner['readiness']['message'] }}</p>
    <ul class="bullets">
        @foreach ($owner['readiness']['requirements'] as $requirement)
            <li>{{ $requirement['complete'] ? 'مكتمل:' : 'ينقصك:' }} {{ $requirement['label'] }}</li>
        @endforeach
    </ul>
</section>

<section class="card">
    <h2 class="section-title">تفاصيل تساعدك على فهم قدرتك على التنفيذ</h2>
    @php($behaviour = $details['behaviour'])
    <p>
        أنجزت {{ $behaviour['tasks']['done'] ?? 0 }} من {{ $behaviour['tasks']['total'] ?? 0 }} مهمة مسجلة.
        هذا الرقم لا يقيّمك؛ هو يساعدك على اختيار خطة تستطيع متابعتها فعلًا.
    </p>
    <h3>خطة عملك الشخصية خلال 30 و60 و90 يومًا</h3>
    @foreach (['30_days' => 'أول 30 يومًا', '60_days' => 'حتى 60 يومًا', '90_days' => 'حتى 90 يومًا'] as $key => $label)
        <h4>{{ $label }}</h4>
        <ul class="bullets">
            @forelse (($details['plan'][$key] ?? []) as $item)
                <li>{{ $item['title'] }}</li>
            @empty
                <li>لا توجد خطوة إضافية في هذه الفترة الآن.</li>
            @endforelse
        </ul>
    @endforeach
</section>
