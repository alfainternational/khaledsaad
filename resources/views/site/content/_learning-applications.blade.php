@if ($learning['enabled'] && count($learning['applications'] ?? []))
    <section class="lesson-applications" aria-labelledby="lesson-applications-title">
        <div class="lesson-applications__head">
            <div>
                <p class="eyebrow">التطبيق جزء من الدرس</p>
                <h2 id="lesson-applications-title">طبّق هذا الدرس الآن</h2>
            </div>
            <p>اختر التطبيق المناسب وابدأ مباشرة. لا تحتاج إلى إنشاء مشروع، وتبقى إجاباتك محفوظة في مسارك.</p>
        </div>
        <div class="lesson-applications__list">
            @foreach ($learning['applications'] as $application)
                <article>
                    <div>
                        <h3>{{ $application['title'] }}</h3>
                        <p>{{ $application['purpose'] }}</p>
                        <small>{{ $application['duration_minutes'] }} دقيقة · المخرج: {{ $application['deliverable'] }}</small>
                    </div>
                    <a class="btn btn--primary" href="{{ route('app.learning.marketing.course.exercise', $application['key']) }}">ابدأ التطبيق</a>
                </article>
            @endforeach
        </div>
    </section>
@endif
