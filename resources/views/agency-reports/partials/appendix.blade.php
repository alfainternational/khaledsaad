{{--
    الملحقان: ما رفعه صاحب المشروع من أدلة، وما هو جاهز للنشر فورًا.

    الأدلة المرفوعة تخضع لمفتاح خصوصية الأدلة: المحجوب يُعلن عدده ولا
    يُعرض مضمونه، فالقرار لصاحب المشروع لا للقالب.
--}}

@php($appendix = $snapshot['appendix'] ?? null)
@php($print = $print ?? false)

@if ($appendix)
    <h2 class="section-title">ملحق أ — الأدلة المرفوعة</h2>

    @if ($appendix['files']['count'] === 0)
        <p class="muted">لم يرفع صاحب المشروع أي ملف داعم. كل رقم في هذا المستند مصدره إجاباته المكتوبة.</p>
    @elseif ($appendix['files']['mode'] !== 'full')
        <p class="muted">
            {{ $appendix['files']['count'] }} ملفًا مرفوعًا، محجوبة المحتوى بطلب صاحب المشروع.
            يمكن طلب الاطلاع عليها مباشرة.
        </p>
    @else
        <p class="muted">
            {{ $appendix['files']['count'] }} ملفًا رفعها صاحب المشروع كإسناد لإجاباته.
        </p>
        <div class="{{ $print ? '' : 'table-scroll' }}">
            <table class="{{ $print ? '' : 'data-table' }}">
                <thead><tr><th>الملف</th><th>الحجم</th><th>مقتطف مما قُرئ منه</th></tr></thead>
                <tbody>
                    @foreach ($appendix['files']['items'] as $file)
                        <tr>
                            <td>{{ $file['name'] }}<br><span class="{{ $print ? 'small' : 'muted' }}">{{ $file['type'] }}</span></td>
                            <td>{{ $file['size_kb'] }} ك.ب</td>
                            <td>
                                @if ($file['extracted'] && $file['excerpt'])
                                    {{ $file['excerpt'] }}…
                                @else
                                    <span class="muted">لم يُستخرج نصه — يُطلب أصله عند الحاجة</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="section-title">ملحق ب — أصول جاهزة للنشر</h2>

    @if ($appendix['ready_assets'] === null)
        <p class="muted">لم تُولَّد حزمة ظهور بعد. توليدها من داخل المنصة يستغرق دقائق ولا يحتاج إنتاجًا جديدًا.</p>
    @else
        @php($ready = $appendix['ready_assets'])
        <p>
            حزمة ظهور مولّدة في {{ $ready['generated_at'] }}:
            {{ $ready['facts'] }} حقيقة موثقة ·
            {{ $ready['faq'] }} سؤالًا شائعًا ·
            {{ $ready['has_jsonld'] ? 'بيانات منظمة JSON-LD جاهزة' : 'بلا بيانات منظمة' }} ·
            {{ $ready['has_llms_txt'] ? 'ملف llms.txt جاهز' : 'بلا ملف llms.txt' }}.
        </p>
        <p class="{{ $print ? 'small' : 'muted' }}">{{ $ready['note'] }}</p>
    @endif
@endif
