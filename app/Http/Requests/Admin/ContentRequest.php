<?php

namespace App\Http\Requests\Admin;

use App\Models\Content;
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
        $this->merge([
            'access_level' => $this->input('access_level', Content::ACCESS_PUBLIC),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }

    public function rules(): array
    {
        $contentId = $this->route('content')?->getKey();

        return [
            'type' => ['required', Rule::in(Content::types())],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u', Rule::unique('contents', 'slug')->ignore($contentId)],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string'],
            'body_json' => ['nullable', 'json'],
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(Content::statuses())],
            'access_level' => ['required', Rule::in([Content::ACCESS_PUBLIC, Content::ACCESS_SUBSCRIBERS])],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
