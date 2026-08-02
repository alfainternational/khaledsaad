@extends('layouts.app')
@section('layout', 'detail')
@section('title', $plan->title)

@section('content')
    <div class="content-dashboard" data-content-dashboard>
        <header class="page-head content-dashboard__head">
            <div>
                <p class="eyebrow">{{ $plan->project->name }} · {{ $plan->month->translatedFormat('F Y') }}</p>
                <h1>{{ $plan->title }}</h1>
                <p class="muted">من المسودة إلى قياس الأداء — المصدر: {{ $plan->source_filename ?: 'إدخال يدوي' }}</p>
            </div>
            <div class="page-head__actions">
                <a href="{{ route('app.content-plans.index') }}" class="btn btn--ghost">كل الخطط</a>
                <form method="POST" action="{{ route('app.content-plans.archive', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn--ghost">
                        {{ $plan->status === 'archived' ? 'استعد الخطة' : 'أرشف الخطة' }}
                    </button>
                </form>
            </div>
        </header>

        <section class="content-metrics" aria-label="ملخص الخطة">
            <article class="stat"><span class="stat__value">{{ $metrics['total'] }}</span><span class="stat__label">إجمالي المنشورات</span></article>
            <article class="stat"><span class="stat__value">{{ $metrics['awaiting_design'] }}</span><span class="stat__label">بانتظار التصميم</span></article>
            <article class="stat"><span class="stat__value">{{ $metrics['ready'] }}</span><span class="stat__label">جاهز للنشر</span></article>
            <article class="stat"><span class="stat__value">{{ $metrics['published'] }}</span><span class="stat__label">منشور</span></article>
            <article class="stat stat--progress"><span class="stat__value">{{ $metrics['progress'] }}%</span><span class="stat__label">نسبة الإنجاز</span></article>
        </section>

        <section class="card content-toolbar" aria-label="أدوات العرض والتصفية">
            <div class="content-view-switch" role="group" aria-label="طريقة العرض">
                <button type="button" class="btn btn--sm is-active" data-content-view="overview" aria-pressed="true">تقويم النشر</button>
                <button type="button" class="btn btn--ghost btn--sm" data-content-view="board" aria-pressed="false">مسار التنفيذ</button>
                <button type="button" class="btn btn--ghost btn--sm" data-content-view="table" aria-pressed="false">الجدول التشغيلي</button>
            </div>
            <label class="field content-toolbar__search">
                <span class="sr-only">ابحث في المنشورات</span>
                <input type="search" placeholder="ابحث بالعنوان أو النص…" data-content-search>
            </label>
            <label class="field">
                <span class="sr-only">صفِّ حسب المحور</span>
                <select data-content-pillar>
                    <option value="">كل المحاور</option>
                    @foreach ($pillars as $pillar)
                        <option value="{{ $pillar }}">{{ $pillar }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="sr-only">صفِّ حسب الحالة</span>
                <select data-content-stage>
                    <option value="">كل الحالات</option>
                    <option value="design">قيد التصميم</option>
                    <option value="review">قيد المراجعة</option>
                    <option value="ready">جاهز للنشر</option>
                    <option value="partially_published">نُشر جزئيًا</option>
                    <option value="published">منشور</option>
                </select>
            </label>
        </section>

        <section class="content-view" data-content-panel="overview">
            <div class="content-overview-grid">
                <article class="card content-calendar" aria-labelledby="calendar-heading">
                    <div class="content-section-head"><h2 id="calendar-heading" class="section-title">تقويم النشر</h2><span>{{ $plan->month->translatedFormat('F Y') }}</span></div>
                    <div class="content-calendar__weekdays" aria-hidden="true">
                        @foreach (['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'] as $day)
                            <span>{{ $day }}</span>
                        @endforeach
                    </div>
                    <div class="content-calendar__grid">
                        @for ($blank = 0; $blank < $plan->month->copy()->startOfMonth()->dayOfWeek; $blank++)
                            <span class="content-calendar__blank" aria-hidden="true"></span>
                        @endfor
                        @for ($day = 1; $day <= $plan->month->daysInMonth; $day++)
                            <div @class(['content-calendar__day', 'has-posts' => isset($calendar[$day])])>
                                <b>{{ $day }}</b>
                                @foreach (($calendar[$day] ?? collect())->take(3) as $post)
                                    <a href="#post-{{ $post->id }}" data-content-item data-search="{{ $post->title }} {{ $post->x_content }}" data-pillar="{{ $post->pillar }}" data-stage="{{ $post->workflowStage() }}">
                                        {{ $post->title }}
                                    </a>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </article>

                <aside class="card content-upcoming" aria-labelledby="upcoming-heading">
                    <div class="content-section-head"><h2 id="upcoming-heading" class="section-title">الأقرب تنفيذًا</h2><span>{{ $activePosts->count() }} بطاقة</span></div>
                    @foreach ($activePosts->sortBy('publish_at')->take(5) as $post)
                        <a href="#post-{{ $post->id }}" class="content-upcoming__item" data-content-item data-search="{{ $post->title }} {{ $post->x_content }}" data-pillar="{{ $post->pillar }}" data-stage="{{ $post->workflowStage() }}">
                            <span><strong>{{ $post->title }}</strong><small>{{ $post->publish_at->translatedFormat('j F · H:i') }} · {{ $post->pillar }}</small></span>
                            <b>{{ $post->progressPercent() }}%</b>
                        </a>
                    @endforeach
                </aside>
            </div>
        </section>

        <section class="content-view" data-content-panel="board" hidden>
            @php($columns = ['design' => 'قيد التصميم', 'review' => 'قيد المراجعة', 'ready' => 'جاهز للنشر', 'published' => 'منشور'])
            <div class="content-board">
                @foreach ($columns as $stage => $label)
                    @php($columnPosts = $stage === 'published'
                        ? $activePosts->filter(fn ($post) => in_array($post->workflowStage(), ['published', 'partially_published'], true))
                        : ($stages[$stage] ?? collect()))
                    <section class="content-board__column" aria-labelledby="column-{{ $stage }}">
                        <h2 id="column-{{ $stage }}">{{ $label }} <span>{{ $columnPosts->count() }}</span></h2>
                        @foreach ($columnPosts as $post)
                            <a href="#post-{{ $post->id }}" class="content-board__card" data-content-item data-search="{{ $post->title }} {{ $post->x_content }} {{ $post->linkedin_content }}" data-pillar="{{ $post->pillar }}" data-stage="{{ $post->workflowStage() }}">
                                <span class="badge">{{ $post->pillar }}</span>
                                <strong>{{ $post->title }}</strong>
                                <small>{{ $post->publish_at->translatedFormat('j F · H:i') }}</small>
                                <span class="content-progress"><i style="width: {{ $post->progressPercent() }}%"></i></span>
                            </a>
                        @endforeach
                    </section>
                @endforeach
            </div>
        </section>

        <section class="content-view card" data-content-panel="table" hidden>
            <div class="content-section-head"><h2 class="section-title">الجدول التشغيلي</h2><span>{{ $activePosts->count() }} منشورًا</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>التاريخ</th><th>الموضوع</th><th>المحور</th><th>الحالة</th><th>التقدم</th></tr></thead>
                    <tbody>
                        @foreach ($activePosts as $post)
                            <tr data-content-item data-search="{{ $post->title }} {{ $post->x_content }} {{ $post->linkedin_content }}" data-pillar="{{ $post->pillar }}" data-stage="{{ $post->workflowStage() }}">
                                <td>{{ $post->position }}</td>
                                <td>{{ $post->publish_at->translatedFormat('j F · H:i') }}</td>
                                <td><a href="#post-{{ $post->id }}"><strong>{{ $post->title }}</strong></a></td>
                                <td>{{ $post->pillar }}</td><td>{{ $post->stageLabel() }}</td><td>{{ $post->progressPercent() }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <p class="alert alert--info content-filter-empty" data-content-empty hidden>لا توجد منشورات تطابق البحث والتصفية الحالية.</p>

        <section class="content-workspace" aria-labelledby="posts-heading">
            <div class="content-section-head"><div><p class="eyebrow">النص والتصميم والنشر</p><h2 id="posts-heading" class="section-title">بطاقات المنشورات</h2></div></div>

            @foreach ($posts as $post)
                <details id="post-{{ $post->id }}" @class(['card', 'content-post', 'is-archived' => $post->archived_at]) data-content-item data-search="{{ $post->title }} {{ $post->x_content }} {{ $post->linkedin_content }}" data-pillar="{{ $post->pillar }}" data-stage="{{ $post->workflowStage() }}">
                    <summary>
                        <span class="content-post__number">{{ str_pad((string) $post->position, 2, '0', STR_PAD_LEFT) }}</span>
                        <span><strong>{{ $post->title }}</strong><small>{{ $post->publish_at->translatedFormat('l j F Y · H:i') }} · {{ $post->pillar }}</small></span>
                        <span class="badge">{{ $post->stageLabel() }} · {{ $post->progressPercent() }}%</span>
                    </summary>

                    <div class="content-post__body">
                        <div class="content-channel-grid">
                            <article class="content-copy-card">
                                <div class="content-section-head"><h3>منشور X</h3><button type="button" class="btn btn--ghost btn--sm" data-copy-content="x-{{ $post->id }}">نسخ النص</button></div>
                                <p id="x-{{ $post->id }}" class="content-copy-text">{{ $post->x_content }}</p>
                            </article>
                            <article class="content-copy-card">
                                <div class="content-section-head"><h3>منشور لينكد إن</h3><button type="button" class="btn btn--ghost btn--sm" data-copy-content="linkedin-{{ $post->id }}">نسخ النص</button></div>
                                <p id="linkedin-{{ $post->id }}" class="content-copy-text">{{ $post->linkedin_content }}</p>
                            </article>
                        </div>

                        <div class="content-detail-grid">
                            <div><h3>موجز التصميم</h3><p>{{ $post->design_brief ?: 'لا يوجد موجز تصميم.' }}</p></div>
                            <div><h3>ملاحظات النشر</h3><p>{{ $post->publishing_notes ?: 'لا توجد ملاحظات.' }}</p></div>
                            <div><h3>النص البديل</h3><p id="alt-{{ $post->id }}">{{ $post->alt_text ?: 'لا ينطبق.' }}</p>@if($post->alt_text)<button type="button" class="btn btn--ghost btn--sm" data-copy-content="alt-{{ $post->id }}">نسخ Alt</button>@endif</div>
                            <div><h3>الهاشتاقات</h3><p>{{ implode(' ', $post->hashtags ?? []) ?: 'بدون هاشتاقات' }}</p></div>
                        </div>

                        <div class="content-workflow" aria-label="حالة تنفيذ {{ $post->title }}">
                            @unless ($post->requires_design)
                                <span class="content-step is-done"><span aria-hidden="true">✓</span>لا يحتاج تصميمًا</span>
                            @endunless
                            @foreach ([
                                'designed' => ['صُمِّم', $post->designed_at],
                                'reviewed' => ['رُوجع', $post->reviewed_at],
                                'x_published' => ['نُشر على X', $post->x_published_at],
                                'linkedin_published' => ['نُشر على لينكد إن', $post->linkedin_published_at],
                            ] as $step => [$label, $date])
                                @continue($step === 'designed' && ! $post->requires_design)
                                <form method="POST" action="{{ route('app.content-posts.workflow', $post) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="step" value="{{ $step }}">
                                    <input type="hidden" name="completed" value="{{ $date ? 0 : 1 }}">
                                    <button type="submit" @class(['content-step', 'is-done' => $date])><span aria-hidden="true">{{ $date ? '✓' : '○' }}</span>{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>

                        <details class="content-subpanel">
                            <summary>تحرير البطاقة</summary>
                            <form method="POST" action="{{ route('app.content-posts.update', $post) }}" class="form layout-flow">
                                @csrf @method('PATCH')
                                @include('app.content-plans.partials.post-form', ['post' => $post])
                                <button type="submit" class="btn btn--primary">حفظ التعديلات</button>
                            </form>
                        </details>

                        <details class="content-subpanel">
                            <summary>تسجيل الأداء بعد 48 ساعة</summary>
                            <form method="POST" action="{{ route('app.content-posts.metrics', $post) }}" class="field-row">
                                @csrf @method('PATCH')
                                @foreach (['x_reach' => 'وصول X', 'x_engagement' => 'تفاعل X', 'linkedin_reach' => 'وصول لينكد إن', 'linkedin_engagement' => 'تفاعل لينكد إن'] as $field => $label)
                                    <label class="field"><span class="field__label">{{ $label }}</span><input type="number" min="0" name="{{ $field }}" value="{{ $post->{$field} }}"></label>
                                @endforeach
                                <button type="submit" class="btn btn--primary btn--sm">حفظ الأداء</button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('app.content-posts.archive', $post) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn--ghost btn--sm">{{ $post->archived_at ? 'استعد المنشور' : 'أرشف المنشور' }}</button>
                        </form>
                    </div>
                </details>
            @endforeach

            <details class="card content-add-post">
                <summary>+ إضافة منشور</summary>
                <form method="POST" action="{{ route('app.content-posts.store', $plan) }}" class="form layout-flow">
                    @csrf
                    @include('app.content-plans.partials.post-form', ['post' => null])
                    <button type="submit" class="btn btn--primary">أضف إلى الخطة</button>
                </form>
            </details>
        </section>

        <section class="content-guidelines" aria-labelledby="guidelines-heading">
            <div class="content-section-head"><div><p class="eyebrow">مرجع الفريق</p><h2 id="guidelines-heading" class="section-title">المواصفات وقواعد الأمان التحريري</h2></div></div>
            <div class="content-guidelines__grid">
                <article class="card"><h3>مواصفات التصميم</h3><dl>@foreach (($plan->design_specifications ?? []) as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></article>
                <article class="card"><h3>مواصفات النشر</h3><dl>@foreach (($plan->publishing_specifications ?? []) as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></article>
                <article class="card"><h3>النشر الإضافي</h3><ul>@foreach (($plan->activity_protocol ?? []) as $rule)<li><strong>{{ $rule['الحالة'] ?? '' }}:</strong> {{ $rule['الشكل'] ?? '' }}</li>@endforeach</ul></article>
                <article class="card"><h3>قواعد الأمان التحريري</h3><ol>@foreach (($plan->safety_rules ?? []) as $rule)<li>{{ $rule }}</li>@endforeach</ol></article>
            </div>
        </section>
    </div>
@endsection
