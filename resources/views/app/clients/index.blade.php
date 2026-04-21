@extends('layouts.app', ['title' => 'العملاء', 'pageTitle' => 'إدارة العملاء', 'pageKicker' => 'Clients'])

@section('content')
<section class="app-hero mb-8">
    <div>
        <h2 class="heading-lg mb-4">عملاؤك داخل <span class="text-gradient">مساحة العمل الحالية</span></h2>
        <p class="text-body-lg">من هنا تنظم كل عميل وتربطه بمشاريعه دون الحاجة إلى ملفات منفصلة أو متابعة خارج النظام.</p>
    </div>
    <div class="app-hero-actions">
        <a href="{{ route('clients.create') }}" class="btn btn-primary btn-lg">إضافة عميل</a>
    </div>
</section>

<section class="card">
    <div class="app-list">
        @forelse ($clients as $client)
            <div class="app-list-item">
                <div>
                    <strong>{{ $client->name }}</strong>
                    <small>{{ $client->status }} · {{ $client->contact_info['email'] ?? 'بدون بريد' }} · {{ $client->projects_count }} مشاريع</small>
                    <small>آخر مشروع: {{ $client->projects->first()?->name ?? 'لا يوجد مشروع بعد' }}</small>
                </div>
                <div class="app-inline-actions">
                    <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary btn-sm">تعديل</a>
                    <form method="POST" action="{{ route('clients.destroy', $client) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="app-empty">لا يوجد عملاء بعد. ابدأ بإضافة أول عميل داخل المساحة.</p>
        @endforelse
    </div>

    <div class="admin-pagination mt-4">
        {{ $clients->links() }}
    </div>
</section>
@endsection
