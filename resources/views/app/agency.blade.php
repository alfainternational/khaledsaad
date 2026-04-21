@extends('layouts.app', ['title' => 'وضع الوكالة', 'pageTitle' => 'وضع الوكالة', 'pageKicker' => 'Agency'])

@section('content')
<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">جاهزية وضع الوكالة</h3>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div><strong>Entitlement</strong></div>
                <span class="app-badge">{{ $agencyEntitled ? 'enabled' : 'disabled' }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>Feature Flag</strong></div>
                <span class="app-badge">{{ $agencyFlagEnabled ? 'enabled' : 'off' }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>نوع المساحة الحالية</strong></div>
                <span class="app-badge">{{ $workspace->type }}</span>
            </div>
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">ملخص العملاء</h3>
        </div>
        <div class="app-list">
            @forelse ($clients as $client)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $client->name }}</strong>
                        <small>{{ $client->status }}</small>
                    </div>
                    <span class="app-badge">{{ $client->projects_count }} مشاريع</span>
                </div>
            @empty
                <p class="app-empty">لا يوجد عملاء داخل هذه المساحة بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">موافقات تحتاج مراجعة</h3>
            <a href="{{ route('approvals.index') }}" class="btn btn-secondary btn-sm">كل الاعتمادات</a>
        </div>
        <div class="app-list">
            @forelse ($pendingApprovals as $approval)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $approval->project?->name ?? 'بدون مشروع' }}</strong>
                        <small>{{ $approval->project?->client?->name ?? 'بدون عميل' }} · {{ $approval->item_type }}</small>
                    </div>
                    <span class="app-badge">{{ $approval->status }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد موافقات معلقة حالياً.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">صحة ملفات العملاء</h3>
        </div>
        <div class="app-list">
            @forelse ($clientSummaries as $client)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $client->name }}</strong>
                        <small>{{ $client->projects_count }} مشاريع · {{ $client->status }}</small>
                    </div>
                    <span class="app-badge">{{ $client->projects->first()?->status ?? 'بدون مشروع' }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد ملفات عملاء جاهزة للعرض بعد.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
