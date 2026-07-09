@extends('layouts.admin', ['title' => 'قدرات الوكلاء', 'pageTitle' => 'قدرات الوكلاء الذكية', 'pageKicker' => 'Agent Capabilities'])

@section('content')
<section class="admin-panel">
    <div class="admin-panel-head">
        <h2>نظرة عامة — {{ $total }} قدرة</h2>
    </div>
    <p class="admin-hint">
        كل قدرة هنا تجسيد محلي لوكيل من وكلاء التسويق، يعمل كمهارة داخل النواة. تُكشف انتقائياً
        عبر الحالة (lifecycle) والصلاحية (entitlement) ومفتاح الميزة (feature flag). المصدر:
        <code>config/agent_registry.php</code>.
    </p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>حسب الموجة</th><th>حسب الحالة</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @foreach ($byWave as $wave => $count)
                            <span class="agent-badge agent-wave-{{ $wave }}">موجة {{ $wave }}: {{ $count }}</span>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($byStatus as $status => $count)
                            <span class="agent-badge agent-status-{{ $status }}">{{ $status }}: {{ $count }}</span>
                        @endforeach
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@foreach ($grouped as $clusterLabel => $items)
<section class="admin-panel">
    <div class="admin-panel-head">
        <h2>{{ $clusterLabel }} <span class="agent-count">{{ count($items) }}</span></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>القدرة</th>
                    <th>ماذا يحصل عليه المستخدم</th>
                    <th>المرحلة</th>
                    <th>الحالة</th>
                    <th>الموجة</th>
                    <th>الصلاحية</th>
                    <th>مفتاح الميزة</th>
                    <th>الشرائح</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $agent)
                    <tr>
                        <td>
                            <strong>{{ $agent->name }}</strong>
                            <div class="agent-code">{{ $agent->code }}</div>
                        </td>
                        <td class="agent-summary">{{ $agent->summary }}</td>
                        <td>{{ $agent->stage === 0 ? 'عابرة' : 'م' . $agent->stage }}</td>
                        <td><span class="agent-badge agent-status-{{ $agent->status }}">{{ $agent->status }}</span></td>
                        <td><span class="agent-badge agent-wave-{{ $agent->wave }}">{{ $agent->wave }}</span></td>
                        <td>{{ $agent->entitlement ?? 'أساسية' }}</td>
                        <td>{{ $agent->featureFlag ?? '—' }}</td>
                        <td>{{ $agent->isInfrastructure() ? 'بنية تحتية' : count($agent->personas) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endforeach
@endsection
