@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'فجوات التقارير')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>فجوات التقارير</h1>
            <p class="muted">
                ما يعجز النظام عن تسليمه، ولماذا. الجدول الأول نقصٌ عندنا يُصلَح بتأليف قالب،
                والثاني نقصٌ في بيانات أصحاب الأنشطة يُصلَح بسؤال أوضح أو حقل يُضاف إلى الاستقبال.
            </p>
        </div>
    </header>

    <section aria-labelledby="template-gaps-heading">
        <div class="section-head">
            <h2 id="template-gaps-heading" class="section-title">قوالب غائبة أو ناقصة السياق</h2>
        </div>

        @if ($templateGaps->isEmpty())
            <p class="muted">لا فجوة قوالب مسجّلة. كل هدف طُلب كان له قالب مكتمل.</p>
        @else
            <div class="table-wrap">
                <table data-table>
                    <thead>
                        <tr>
                            <th scope="col">الهدف</th>
                            <th scope="col">مرات الطلب</th>
                            <th scope="col">السياق الناقص</th>
                            <th scope="col">آخر مرة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templateGaps as $gap)
                            <tr>
                                <td data-label="الهدف">{{ $gap->objective?->name ?? '—' }}</td>
                                <td data-label="مرات الطلب">{{ $gap->occurrences }}</td>
                                <td data-label="السياق الناقص">
                                    {{-- مصفوفة فارغة تعني أن القالب نفسه غائب، لا أن سياقه ناقص. --}}
                                    {{ ($gap->missing_context ?? []) === [] ? 'القالب نفسه غير موجود' : implode('، ', $gap->missing_context) }}
                                </td>
                                <td data-label="آخر مرة">{{ $gap->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="field-gaps-heading">
        <div class="section-head">
            <h2 id="field-gaps-heading" class="section-title">معلومات لا يعطيها أصحاب الأنشطة</h2>
        </div>

        @if ($fieldGaps === [])
            <p class="muted">لا فجوات بيانات معلنة في التقارير الأخيرة.</p>
        @else
            <p class="muted">
                الحقل الذي يتكرر غيابه عبر عشرات التقارير مشكلته في السؤال لا في من يجيبه.
                وعمود «سُدَّت» يقول كم منها أكملها أصحابها بعد قراءة التقرير.
            </p>

            <div class="table-wrap">
                <table data-table>
                    <thead>
                        <tr>
                            <th scope="col">الحقل</th>
                            <th scope="col">مصدره</th>
                            <th scope="col">تقارير أعلنته</th>
                            <th scope="col">سُدَّت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fieldGaps as $gap)
                            <tr>
                                <td data-label="الحقل">
                                    <strong>{{ $gap['label'] }}</strong>
                                    <span class="muted">{{ $gap['key'] }}</span>
                                </td>
                                <td data-label="مصدره">{{ $gap['source'] }}</td>
                                <td data-label="تقارير أعلنته">{{ $gap['reports'] }}</td>
                                <td data-label="سُدَّت">
                                    {{ $gap['answered'] }}
                                    <span class="muted">من {{ $gap['reports'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
