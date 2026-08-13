@if ($learningGallery['enabled'] ?? false)
    <section class="marketing-course-gallery" data-marketing-course-gallery aria-labelledby="marketing-course-gallery-title">
        <div class="marketing-course-gallery__intro">
            <div>
                <p class="eyebrow">كل التعلم في مكان واحد</p>
                <h2 id="marketing-course-gallery-title">معرض الدروس والتطبيقات</h2>
                <p>اختر الدرس، ثم افتح التطبيق الذي تحتاجه مباشرة. لا يشترط وجود مشروع، وتبقى إجاباتك ونتائجك مرتبطة بحسابك.</p>
            </div>
            <div class="marketing-course-gallery__stats" aria-label="ملخص المسار">
                <span><strong>{{ $learningGallery['lesson_count'] }}</strong> درس</span>
                <span><strong>{{ $learningGallery['exercise_count'] }}</strong> تطبيق</span>
                <span><strong>{{ $learningGallery['completed_count'] }}</strong> مكتمل</span>
                <span><strong>{{ $learningGallery['remaining_count'] }}</strong> متبقٍ</span>
                @if ($learningGallery['average_score'] !== null)
                    <span class="marketing-course-gallery__score"><strong>{{ $learningGallery['average_score'] }}/100</strong> متوسط النتيجة</span>
                @endif
            </div>
        </div>

        @if ($learningGallery['access_state'] === 'guest')
            <div class="marketing-course-gallery__notice">
                يمكنك استعراض كل التطبيقات الآن. عند بدء أي تطبيق سنطلب تسجيل الدخول ثم نعيدك إليه مباشرة.
            </div>
        @elseif ($learningGallery['access_state'] === 'locked')
            <div class="marketing-course-gallery__notice marketing-course-gallery__notice--locked">
                <span><strong>هذه التطبيقات غير متاحة في باقتك الحالية.</strong> يمكنك الاطلاع على جميع الدروس واختيار الباقة المناسبة للتطبيق العملي.</span>
                <a href="{{ route('app.billing') }}">اطّلع على الباقات</a>
            </div>
        @elseif ($learningGallery['progress_unavailable'])
            <div class="marketing-course-gallery__notice">
                المعرض متاح كاملًا، لكن تعذر تحميل تقدمك الآن. يمكنك متابعة أي تطبيق كالمعتاد.
            </div>
        @endif

        <div class="marketing-course-gallery__lessons">
            @foreach ($learningGallery['lessons'] as $lesson)
                <details class="marketing-course-gallery__lesson" data-gallery-lesson @if ($lesson['open']) open @endif>
                    <summary>
                        <span class="marketing-course-gallery__lesson-number">{{ str_pad((string) $lesson['number'], 2, '0', STR_PAD_LEFT) }}</span>
                        <span>
                            <small>الدرس {{ $lesson['number'] }}</small>
                            <strong>{{ $lesson['title'] }}</strong>
                        </span>
                        <span class="marketing-course-gallery__lesson-progress">
                            {{ $lesson['completed_count'] }}/{{ $lesson['exercise_count'] }}
                        </span>
                    </summary>
                    <div class="marketing-course-gallery__lesson-body">
                        <a class="marketing-course-gallery__lesson-link" href="{{ $lesson['source_url'] }}">اقرأ الدرس <span aria-hidden="true">↗</span></a>
                        <div class="marketing-course-gallery__applications">
                            @foreach ($lesson['exercises'] as $application)
                                <article class="marketing-course-gallery__application">
                                    <div>
                                        <div class="marketing-course-gallery__application-meta">
                                            <span>{{ $application['status_label'] }}</span>
                                            @if ($application['score'] !== null)<strong>{{ $application['score'] }}/100</strong>@endif
                                        </div>
                                        <h3>{{ $application['title'] }}</h3>
                                        <p>{{ $application['purpose'] }}</p>
                                        <small>{{ $application['duration_minutes'] }} دقيقة · {{ $application['deliverable'] }}</small>
                                    </div>
                                    <a class="btn btn--primary" href="{{ $application['action_url'] }}">{{ $application['action_label'] }}</a>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endif
