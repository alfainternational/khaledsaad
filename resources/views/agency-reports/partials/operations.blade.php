{{--
    الأقسام التشغيلية: أرقام بثقتها، جرد وصول، سجل تنفيذ، وملحقان.

    ترتيبها مقصود — الوكالة تقرأ لتسعّر لا لتتثقف: الأرقام ثم الأصول ثم
    السلوك. الوسيط $print يفرّق بين طباعة PDF وعرض الشاشة.
--}}

@php($print = $print ?? false)
@php($tableClass = $print ? '' : 'data-table')

@if (! empty($snapshot['numbers']))
    @php($numbers = $snapshot['numbers'])
    <h2 class="section-title">الوضع الحالي بالأرقام</h2>
    <p class="muted">
        نضج التتبع المعلن: {{ $numbers['tracking_label'] ?: 'غير محدد' }}.
        {{ $numbers['summary']['known'] }} رقمًا معروفًا من {{ $numbers['summary']['total'] }}،
        منها {{ $numbers['summary']['measured'] }} مقيسة بتتبع كامل.
    </p>

    <div class="{{ $print ? '' : 'table-scroll' }}">
        <table class="{{ $tableClass }}">
            <thead>
                <tr>
                    <th>المؤشر</th>
                    <th>قيمة المشروع</th>
                    <th>مرجع السوق</th>
                    <th>مستوى الثقة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($numbers['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>
                            @if ($row['value'] === null)
                                <span class="muted">لم يُصرَّح به</span>
                            @else
                                {{ $row['value'] }}{{ $row['unit'] ? ' '.$row['unit'] : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($row['benchmark'])
                                {{ $row['benchmark']['range'] }} {{ $row['benchmark']['unit'] }}
                                <br><span class="{{ $print ? 'small' : 'muted' }}">
                                    {{ $row['benchmark']['source'] }} · {{ $row['benchmark']['fetched_at'] }}
                                </span>
                            @else
                                <span class="muted">لا مرجع مسجّل</span>
                            @endif
                        </td>
                        <td>{{ $row['confidence_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="{{ $print ? 'small' : 'muted' }}">{{ $numbers['note'] }}</p>
@endif

@if (! empty($snapshot['assets']))
    @php($assets = $snapshot['assets'])
    <h2 class="section-title">الأصول والوصول</h2>
    <p class="muted">
        مصرّح به {{ $assets['readiness_percent'] }}٪ من الأصول.
        هذا القسم يحدد ما يمكن البدء به في اليوم الأول وما يحتاج صلاحيات أولًا.
    </p>

    <div class="{{ $print ? '' : 'table-scroll' }}">
        <table class="{{ $tableClass }}">
            <thead>
                <tr>
                    <th>الأصل</th>
                    <th>الحالة المصرّح بها</th>
                    <th>لماذا يهم التنفيذ</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assets['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>
                            @if ($row['status'] === 'unknown')
                                <span class="muted">غير معروف — يُسأل عنه في أول اجتماع</span>
                            @else
                                {{ $row['detail'] }}
                            @endif
                        </td>
                        <td class="{{ $print ? 'small' : 'muted' }}">{{ $row['why'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($assets['ownership_note'])
        <p><b>الملكية والصلاحيات كما صرّح بها صاحب المشروع:</b> {{ $assets['ownership_note'] }}</p>
    @endif
    <p class="{{ $print ? 'small' : 'muted' }}">{{ $assets['note'] }}</p>
@endif

@if (! empty($snapshot['behaviour']))
    @php($behaviour = $snapshot['behaviour'])
    <h2 class="section-title">سجل التنفيذ والتفاعل</h2>

    <div class="{{ $print ? '' : 'table-scroll' }}">
        <table class="{{ $tableClass }}">
            <tbody>
                <tr>
                    <th>المهام المسجّلة</th>
                    <td>
                        {{ $behaviour['tasks']['total'] }} مهمة ·
                        منجزة {{ $behaviour['tasks']['done'] }} ·
                        قيد التنفيذ {{ $behaviour['tasks']['in_progress'] }} ·
                        مفتوحة {{ $behaviour['tasks']['open'] }}
                        @if ($behaviour['tasks']['completion_percent'] !== null)
                            ({{ $behaviour['tasks']['completion_percent'] }}٪ إنجاز)
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>تقييم التوصيات السابقة</th>
                    <td>
                        مفيدة {{ $behaviour['feedback']['useful'] }} ·
                        غير مفيدة {{ $behaviour['feedback']['not_useful'] }}
                    </td>
                </tr>
                <tr>
                    <th>عمق الاستخدام</th>
                    <td>
                        {{ $behaviour['engagement']['tools_completed'] }} تشخيصات مكتملة ·
                        {{ $behaviour['engagement']['reports_total'] }} تقريرًا ·
                        أول نشاط {{ $behaviour['engagement']['first_activity'] }}
                    </td>
                </tr>
                @if ($behaviour['trend'])
                    <tr>
                        <th>اتجاه الجاهزية</th>
                        <td>
                            {{ $behaviour['trend']['direction_label'] }} —
                            من {{ $behaviour['trend']['from'] }} إلى {{ $behaviour['trend']['to'] }}
                            خلال {{ $behaviour['trend']['days'] }} يومًا
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    <p class="{{ $print ? 'small' : 'muted' }}">{{ $behaviour['note'] }}</p>
@endif
