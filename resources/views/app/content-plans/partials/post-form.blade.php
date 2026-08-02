@php($value = fn (string $field, mixed $fallback = '') => old($field, $post?->{$field} ?? $fallback))
<div class="field-row">
    <label class="field"><span class="field__label">عنوان البطاقة</span><input name="title" maxlength="220" required value="{{ $value('title') }}"></label>
    <label class="field"><span class="field__label">موعد النشر</span><input type="datetime-local" name="publish_at" required value="{{ old('publish_at', $post?->publish_at?->format('Y-m-d\TH:i')) }}"></label>
    <label class="field"><span class="field__label">المحور</span><input name="pillar" maxlength="140" required value="{{ $value('pillar') }}"></label>
    <label class="field form-check"><input type="checkbox" name="requires_design" value="1" @checked(old('requires_design', $post?->requires_design ?? true))><span>يحتاج تصميمًا</span></label>
</div>
<label class="field"><span class="field__label">نص X</span><textarea name="x_content" rows="6" required>{{ $value('x_content') }}</textarea></label>
<label class="field"><span class="field__label">نص لينكد إن</span><textarea name="linkedin_content" rows="8" required>{{ $value('linkedin_content') }}</textarea></label>
<div class="field-row">
    <label class="field"><span class="field__label">موجز التصميم</span><textarea name="design_brief" rows="6">{{ $value('design_brief') }}</textarea></label>
    <label class="field"><span class="field__label">ملاحظات النشر</span><textarea name="publishing_notes" rows="6">{{ $value('publishing_notes') }}</textarea></label>
</div>
<div class="field-row">
    <label class="field"><span class="field__label">النص البديل Alt</span><textarea name="alt_text" rows="3">{{ $value('alt_text') }}</textarea></label>
    <label class="field"><span class="field__label">الهاشتاقات</span><input name="hashtags_text" value="{{ old('hashtags_text', implode(' ', $post?->hashtags ?? [])) }}" placeholder="#التعليم #التمريض"></label>
</div>
