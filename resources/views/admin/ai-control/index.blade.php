@extends('layouts.admin', ['title' => 'مركز تحكم الذكاء', 'pageTitle' => 'مركز تحكم الذكاء', 'pageKicker' => 'الذكاء الاصطناعي'])

@section('content')

@if (session('success'))
    <div class="admin-alert success mb-6">{{ session('success') }}</div>
@endif

{{-- الحالة الحيّة --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>حالة المحرّك</h2></div>
    <div class="admin-stats-grid">
        <div class="admin-stat">
            <span class="admin-stat-label">المزوّد النشط</span>
            <strong class="admin-stat-value">{{ $status['provider'] }}</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">Gemini</span>
            <span class="app-badge {{ $status['gemini_ready'] ? 'app-badge-success' : 'app-badge-danger' }}">{{ $status['gemini_ready'] ? 'مهيّأ' : 'بلا مفتاح' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">NVIDIA</span>
            <span class="app-badge {{ $status['nvidia_ready'] ? 'app-badge-success' : 'app-badge-danger' }}">{{ $status['nvidia_ready'] ? 'مهيّأ' : 'بلا مفتاح' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">تحقّق TLS</span>
            <span class="app-badge {{ $status['verify_tls'] ? 'app-badge-success' : 'app-badge-danger' }}">{{ $status['verify_tls'] ? 'مفعّل' : 'معطّل' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">الكاش</span>
            <span class="app-badge {{ $status['cache'] ? 'app-badge-success' : 'app-badge-warning' }}">{{ $status['cache'] ? 'يعمل' : 'متوقف' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">حارس الرصيد</span>
            <span class="app-badge {{ $status['enforce_credits'] ? 'app-badge-success' : 'app-badge-warning' }}">{{ $status['enforce_credits'] ? 'مُطبَّق' : 'تتبّع فقط' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">البحث الحيّ</span>
            <strong class="admin-stat-value">{{ $status['search_provider'] }}</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">إثراء الأدوات بالبحث</span>
            <span class="app-badge {{ $status['enrich_tools'] ? 'app-badge-success' : 'app-badge-warning' }}">{{ $status['enrich_tools'] ? 'مفعّل' : 'متوقف' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">Cascade (تصعيد ذكي)</span>
            <span class="app-badge {{ $status['cascade'] ? 'app-badge-success' : 'app-badge-warning' }}">{{ $status['cascade'] ? 'مفعّل · عتبة '.$status['cascade_threshold'] : 'متوقف' }}</span>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">قاضي الجودة (Gemini)</span>
            <span class="app-badge {{ $status['quality_judge'] ? 'app-badge-success' : 'app-badge-warning' }}">{{ $status['quality_judge'] ? 'مفعّل' : 'متوقف' }}</span>
        </div>
    </div>
</section>

{{-- صحة المزوّدين --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>صحة المزوّدين <small>(فحص اتصال — لا يستهلك رصيداً)</small></h2></div>
    <div class="admin-stats-grid">
        @foreach ($health as $provider)
            <div class="admin-stat">
                <span class="admin-stat-label">{{ $provider['label'] }}</span>
                @if (! $provider['ready'])
                    <span class="app-badge app-badge-warning">غير مهيّأ</span>
                @elseif ($provider['reachable'])
                    <span class="app-badge app-badge-success">متاح · {{ $provider['ms'] }}ms</span>
                @else
                    <span class="app-badge app-badge-danger">غير متاح</span>
                @endif
            </div>
        @endforeach
    </div>
</section>

{{-- المقاييس --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>المقاييس</h2></div>
    <div class="admin-stats-grid">
        <div class="admin-stat">
            <span class="admin-stat-label">نسبة إصابة الكاش</span>
            <strong class="admin-stat-value">{{ $metrics['cache_hit_rate'] }}%</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">إصابات / إخفاقات الكاش</span>
            <strong class="admin-stat-value">{{ $metrics['cache_hit'] }} / {{ $metrics['cache_miss'] }}</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">عمليات بحث حيّ</span>
            <strong class="admin-stat-value">{{ $metrics['web_search'] }}</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">إخفاقات بحث</span>
            <strong class="admin-stat-value">{{ $metrics['web_fail'] }}</strong>
        </div>
        <div class="admin-stat">
            <span class="admin-stat-label">تصعيدات Cascade</span>
            <strong class="admin-stat-value">{{ $metrics['cascade_escalated'] }}</strong>
        </div>
    </div>
</section>

{{-- مفاتيح التحكم --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>الإعدادات</h2></div>
    <form method="POST" action="{{ route('admin.ai-control.update') }}" class="admin-form-stack">
        @csrf
        @method('PATCH')
        <label class="admin-field">
            <span>مزوّد LLM</span>
            <select name="provider" class="admin-input">
                @foreach ([
                    'chain' => 'سلسلة مزوّدات (موصى — حسب الترتيب أدناه)',
                    'groq' => 'Groq (سريع · Llama 3.3 70B)',
                    'cerebras' => 'Cerebras (سريع)',
                    'openrouter' => 'OpenRouter (نماذج مجانية متنوّعة)',
                    'nvidia' => 'NVIDIA NIM',
                    'gemini' => 'Gemini (Google)',
                    'fallback' => 'هجين (Gemini ثم NVIDIA)',
                ] as $val => $label)
                    <option value="{{ $val }}" @selected($status['provider'] === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>مزوّد البحث الحيّ</span>
            <select name="search_provider" class="admin-input">
                <option value="duckduckgo" @selected($status['search_provider'] === 'duckduckgo')>DuckDuckGo (بلا مفتاح)</option>
            </select>
        </label>
        <label class="admin-field">
            <span>الكاش (تقليل تكلفة الـ API)</span>
            <select name="cache" class="admin-input">
                <option value="1" @selected($status['cache'])>يعمل</option>
                <option value="0" @selected(! $status['cache'])>متوقف</option>
            </select>
        </label>
        <label class="admin-field">
            <span>حارس الرصيد (يمنع التوليد عند نفاد الرصيد)</span>
            <select name="enforce_credits" class="admin-input">
                <option value="1" @selected($status['enforce_credits'])>مُطبَّق</option>
                <option value="0" @selected(! $status['enforce_credits'])>تتبّع فقط</option>
            </select>
        </label>
        <label class="admin-field">
            <span>إثراء الأدوات ببيانات السوق الحيّة</span>
            <select name="enrich_tools" class="admin-input">
                <option value="1" @selected($status['enrich_tools'])>مفعّل</option>
                <option value="0" @selected(! $status['enrich_tools'])>متوقف</option>
            </select>
        </label>
        <label class="admin-field">
            <span>Cascade (تصعيد للـ LLM عند ثقة منخفضة فقط)</span>
            <select name="cascade" class="admin-input">
                <option value="1" @selected($status['cascade'])>مفعّل</option>
                <option value="0" @selected(! $status['cascade'])>متوقف</option>
            </select>
        </label>
        <label class="admin-field">
            <span>عتبة الثقة للتصعيد (0–100، الأقل = تصعيد أندر)</span>
            <input type="number" name="cascade_threshold" class="admin-input" value="{{ $status['cascade_threshold'] }}" min="0" max="100" required>
        </label>
        <label class="admin-field">
            <span>قاضي الجودة (تقييم المضمون عبر Gemini داخل تحليل الأدوات)</span>
            <select name="quality_judge" class="admin-input">
                <option value="1" @selected($status['quality_judge'])>مفعّل</option>
                <option value="0" @selected(! $status['quality_judge'])>متوقف</option>
            </select>
        </label>
        <label class="admin-field">
            <span>Kill Switch (إيقاف فوري لكل نداءات الذكاء الخارجية)</span>
            <select name="kill_switch" class="admin-input">
                <option value="0" @selected(! $status['kill_switch'])>الذكاء يعمل</option>
                <option value="1" @selected($status['kill_switch'])>إيقاف كامل</option>
            </select>
        </label>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-lg">حفظ الإعدادات</button>
        </div>
    </form>
</section>

{{-- المزوّدات والمفاتيح والسرعة --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>المزوّدات والمفاتيح والسرعة</h2></div>
    <form method="POST" action="{{ route('admin.ai-control.providers') }}" class="admin-form-stack">
        @csrf
        @method('PATCH')

        <p class="report-muted">
            بدائل مجانية ممتازة لـGemini (بلا بطاقة): Groq (console.groq.com) · Cerebras (cloud.cerebras.ai) ·
            OpenRouter (openrouter.ai). أنشئ حساباً مجانياً، انسخ المفتاح، والصقه هنا. اختر «سلسلة مزوّدات»
            في الأعلى ليُجرَّبوا بالترتيب تلقائياً.
        </p>

        <label class="admin-field">
            <span>ترتيب السلسلة (أسماء مفصولة بفواصل — يُجرَّب الأول فالأول)</span>
            <input type="text" name="chain" class="admin-input" value="{{ $status['chain'] }}" placeholder="groq,cerebras,nvidia" required>
        </label>

        <h3 class="admin-subhead">Groq <small>(موصى — سريع + Llama 3.3 70B)</small></h3>
        <label class="admin-field">
            <span>مفتاح Groq <small>(الحالي: {{ $status['groq_key_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
            <input type="password" name="groq_key" class="admin-input" autocomplete="off" placeholder="gsk_… — من console.groq.com">
        </label>
        <label class="admin-field">
            <span>نموذج Groq</span>
            <input type="text" name="groq_model" class="admin-input" value="{{ $status['groq_model'] }}" required>
        </label>

        <h3 class="admin-subhead">Cerebras <small>(سريع + سعة كبيرة)</small></h3>
        <label class="admin-field">
            <span>مفتاح Cerebras <small>(الحالي: {{ $status['cerebras_key_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
            <input type="password" name="cerebras_key" class="admin-input" autocomplete="off" placeholder="csk-… — من cloud.cerebras.ai">
        </label>
        <label class="admin-field">
            <span>نموذج Cerebras</span>
            <input type="text" name="cerebras_model" class="admin-input" value="{{ $status['cerebras_model'] }}" required>
        </label>

        <h3 class="admin-subhead">OpenRouter <small>(نماذج مجانية متنوّعة)</small></h3>
        <label class="admin-field">
            <span>مفتاح OpenRouter <small>(الحالي: {{ $status['openrouter_key_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
            <input type="password" name="openrouter_key" class="admin-input" autocomplete="off" placeholder="sk-or-… — من openrouter.ai">
        </label>
        <label class="admin-field">
            <span>نموذج OpenRouter</span>
            <input type="text" name="openrouter_model" class="admin-input" value="{{ $status['openrouter_model'] }}" required>
        </label>

        <h3 class="admin-subhead">Gemini (Google)</h3>
        <label class="admin-field">
            <span>مفتاح Gemini <small>(الحالي: {{ $status['gemini_key_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
            <input type="password" name="gemini_key" class="admin-input" autocomplete="off" placeholder="أدخل مفتاحاً جديداً لتغييره">
        </label>
        <label class="admin-field">
            <span>نموذج Gemini</span>
            <input type="text" name="gemini_model" class="admin-input" value="{{ $status['gemini_model'] }}" required>
        </label>
        <label class="admin-field">
            <span>مهلة Gemini (ثانية — الأقل = فشل أسرع)</span>
            <input type="number" name="gemini_timeout" class="admin-input" value="{{ $status['gemini_timeout'] }}" min="10" max="120" required>
        </label>

        <h3 class="admin-subhead">NVIDIA NIM</h3>
        <label class="admin-field">
            <span>مفتاح NVIDIA <small>(الحالي: {{ $status['nvidia_key_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
            <input type="password" name="nvidia_key" class="admin-input" autocomplete="off" placeholder="أدخل مفتاحاً جديداً لتغييره">
        </label>
        <label class="admin-field">
            <span>نموذج NVIDIA <small>(8b أسرع · 70b أدقّ — للسرعة استخدم meta/llama-3.1-8b-instruct)</small></span>
            <input type="text" name="nvidia_model" class="admin-input" value="{{ $status['nvidia_model'] }}" required>
        </label>
        <label class="admin-field">
            <span>حدّ التوليد NVIDIA (max tokens — الأقل = أسرع)</span>
            <input type="number" name="nvidia_max_tokens" class="admin-input" value="{{ $status['nvidia_max_tokens'] }}" min="256" max="16384" required>
        </label>
        <label class="admin-field">
            <span>مهلة NVIDIA (ثانية — عالجنا البطء: الأقل يمنع الحجب الطويل)</span>
            <input type="number" name="nvidia_timeout" class="admin-input" value="{{ $status['nvidia_timeout'] }}" min="10" max="120" required>
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-lg">حفظ المزوّدات والمفاتيح</button>
        </div>
    </form>
</section>

{{-- التعلّم --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head">
        <h2>التعلّم الذاتي</h2>
        <form method="POST" action="{{ route('admin.ai-control.learn') }}">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">تشغيل التعلّم الآن</button>
        </form>
    </div>
    @if ($patterns)
        <div class="admin-stats-grid mb-4">
            <div class="admin-stat">
                <span class="admin-stat-label">عدد المشاريع</span>
                <strong class="admin-stat-value">{{ $patterns['total_projects'] ?? 0 }}</strong>
            </div>
            <div class="admin-stat">
                <span class="admin-stat-label">حجم العيّنة</span>
                <strong class="admin-stat-value">{{ $patterns['sample_size'] ?? 0 }}</strong>
            </div>
            <div class="admin-stat">
                <span class="admin-stat-label">أكثر أداة توقّفاً</span>
                <strong class="admin-stat-value">{{ $patterns['common_drop_off_tool'] ?? '—' }}</strong>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>الأداة</th><th>تشغيلات</th><th>مشاريع</th><th>متوسط الجودة</th><th>معدل الإكمال</th></tr></thead>
                <tbody>
                    @foreach (($patterns['tools'] ?? []) as $tool)
                        <tr>
                            <td>{{ $tool['tool_code'] }}</td>
                            <td>{{ $tool['runs'] }}</td>
                            <td>{{ $tool['projects'] }}</td>
                            <td>{{ $tool['avg_quality'] }}</td>
                            <td>{{ round(($tool['completion_rate'] ?? 0) * 100) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="admin-empty">لم يبدأ التعلّم بعد. اضغط "تشغيل التعلّم الآن" لبناء المعرفة من الاستخدام الفعلي.</p>
    @endif
</section>

{{-- استهلاك الرصيد حسب السبب --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>الاستهلاك حسب النوع</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>النوع</th><th>عدد المرات</th><th>إجمالي الاستهلاك</th></tr></thead>
            <tbody>
                @php
                    $reasonLabels = [
                        'ai.chat' => 'محادثة المستشار',
                        'ai.field_suggestions' => 'اقتراح الحقول',
                        'ai.tool_assessment_enrich' => 'صقل تقييم الأداة',
                        'ai.web_research' => 'بحث حيّ',
                    ];
                @endphp
                @forelse ($creditUsage as $row)
                    <tr>
                        <td>{{ $reasonLabels[$row->reason] ?? $row->reason }}</td>
                        <td>{{ $row->hits }}</td>
                        <td><span class="app-badge app-badge-warning">{{ $row->spent }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="admin-empty">لا استهلاك مسجّل بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- قاعدة المعرفة --}}
<section class="admin-panel panel-modern">
    <div class="admin-panel-head"><h2>قاعدة المعرفة <small>({{ $knowledgeCount }} إدخال)</small></h2></div>
    @forelse ($webKnowledge as $entry)
        <div class="admin-list-item">
            <div>
                <strong>{{ $entry['data']['query'] ?? $entry['key'] }}</strong>
                <small>التصنيف: {{ $entry['data']['top_category'] ?? '—' }} · تعلّم: {{ \Illuminate\Support\Str::limit($entry['learned_at'] ?? '', 16, '') }}</small>
            </div>
            <form method="POST" action="{{ route('admin.ai-control.knowledge.forget') }}" onsubmit="return confirm('حذف هذا الإدخال؟');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="key" value="{{ $entry['key'] }}">
                <button type="submit" class="btn btn-secondary btn-sm">حذف</button>
            </form>
        </div>
    @empty
        <p class="admin-empty">لا توجد معرفة متراكمة من البحث الحيّ بعد.</p>
    @endforelse
</section>

@endsection
