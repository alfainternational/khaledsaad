<?php

namespace App\Http\Requests\Admin;

use App\Models\Content;
use App\Models\ContentResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $resourcesJson = $this->input('resources_json', '[]');
        $resources = is_string($resourcesJson) ? json_decode($resourcesJson, true) : null;
        $learningMetaJson = $this->input('learning_meta');
        $learningMeta = is_string($learningMetaJson) && $learningMetaJson !== ''
            ? json_decode($learningMetaJson, true)
            : null;

        $this->merge([
            'access_level' => $this->input('access_level', Content::ACCESS_PUBLIC),
            'sort_order' => $this->input('sort_order', 0),
            'resources' => is_array($resources) ? $resources : null,
            'learning_meta_payload' => is_array($learningMeta) ? $learningMeta : null,
        ]);
    }

    public function rules(): array
    {
        $contentId = $this->route('content')?->getKey();

        return [
            'type' => ['required', Rule::in(Content::types())],
            'category_id' => ['nullable', 'integer', Rule::exists('content_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u', Rule::unique('contents', 'slug')->ignore($contentId)],
            'source_key' => ['nullable', 'string', 'max:255', Rule::unique('contents', 'source_key')->ignore($contentId)],
            'source_filename' => ['nullable', 'string', 'max:255'],
            'source_text_hash' => ['nullable', 'string', 'size:64'],
            'learning_order' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'learning_meta' => ['nullable', 'json'],
            'learning_meta_payload' => ['nullable', 'array'],
            'learning_meta_payload.series' => ['nullable', 'string', 'max:255'],
            'learning_meta_payload.word_count' => ['nullable', 'integer', 'min:0'],
            'learning_meta_payload.outline' => ['nullable', 'array', 'max:100'],
            'learning_meta_payload.outline.*' => ['array'],
            'learning_meta_payload.outline.*.id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'learning_meta_payload.outline.*.title' => ['required', 'string', 'max:500'],
            'learning_meta_payload.faq' => ['nullable', 'array', 'max:20'],
            'learning_meta_payload.faq.*' => ['array'],
            'learning_meta_payload.faq.*.question' => ['required', 'string', 'max:1000'],
            'learning_meta_payload.faq.*.answer' => ['required', 'string', 'max:5000'],
            'learning_meta_payload.cover' => ['nullable', 'array'],
            'learning_meta_payload.cover.hero' => ['nullable', 'string', 'max:255'],
            'learning_meta_payload.cover.card' => ['nullable', 'string', 'max:255'],
            'learning_meta_payload.cover.og' => ['nullable', 'string', 'max:255'],
            'learning_meta_payload.cover.alt' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string'],
            'body_json' => ['nullable', 'json'],
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(Content::statuses())],
            'access_level' => ['required', Rule::in([Content::ACCESS_PUBLIC, Content::ACCESS_SUBSCRIBERS])],
            'published_at' => [
                'nullable',
                'date',
                'required_if:status,'.Content::STATUS_SCHEDULED,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $timestamp = strtotime((string) $value);

                    if ($this->input('status') === Content::STATUS_SCHEDULED
                        && $timestamp !== false
                        && $timestamp <= now()->timestamp) {
                        $fail('يجب أن يكون موعد النشر المجدول في المستقبل.');
                    }
                },
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'resources_json' => ['nullable', 'json'],
            'resources' => ['nullable', 'array', 'max:50'],
            'resources.*.type' => ['required', Rule::in([ContentResource::TYPE_FILE, ContentResource::TYPE_LINK])],
            'resources.*.title' => ['required', 'string', 'max:255'],
            'resources.*.media_id' => [
                'nullable',
                'integer',
                'required_if:resources.*.type,'.ContentResource::TYPE_FILE,
                'exists:content_media,id',
            ],
            'resources.*.url' => [
                'nullable',
                'required_if:resources.*.type,'.ContentResource::TYPE_LINK,
                'url:http,https',
                'max:2048',
            ],
        ];
    }
}
