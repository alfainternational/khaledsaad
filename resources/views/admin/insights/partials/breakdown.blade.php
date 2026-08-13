{{--
    جدول تقسيم موحّد.

    كل تقسيم في هذه اللوحة يعرض الأعمدة الستة نفسها بالترتيب نفسه، لأن
    السؤال واحد في كل مرة: هذا المصدر يجلب كم، ويبقى كم، ويرتدّ كم،
    ويحوّل كم. أربعة جداول بأربعة ترتيبات تجعل المقارنة بينها مستحيلة.

    @param array $rows        صفوف من InsightsReport::breakdown
    @param string $heading    عنوان العمود الأول
    @param string $empty      نص الفراغ
--}}
@php($maxSessions = collect($rows)->max('sessions') ?: 1)

<div class="table-wrap">
    <table class="table" data-table="matrix">
        <thead>
            <tr>
                <th>{{ $heading }}</th>
                <th>الجلسات</th>
                <th>الحصة</th>
                <th>الزوّار</th>
                <th>متوسط البقاء</th>
                <th>الارتداد</th>
                <th>التحويل</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ number_format($row['sessions']) }}</td>
                    <td>
                        <div class="budget-bar" role="img" aria-label="{{ __(':percent٪ من الجلسات', ['percent' => $row['share_percent']]) }}">
                            <span class="budget-bar__fill" style="inline-size: {{ min(100, (int) round($row['sessions'] / $maxSessions * 100)) }}%"></span>
                        </div>
                        {{ $row['share_percent'] }}٪
                    </td>
                    <td>{{ number_format($row['visitors']) }}</td>
                    <td>{{ \App\Modules\Insights\Models\VisitorSession::secondsForHumans($row['avg_seconds']) }}</td>
                    <td>{{ $row['bounce_rate'] }}٪</td>
                    <td>{{ $row['conversions'] }} <span class="muted">({{ $row['conversion_rate'] }}٪)</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ $empty }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
