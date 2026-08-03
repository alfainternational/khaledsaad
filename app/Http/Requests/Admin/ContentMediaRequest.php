<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ContentMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:262144',
                'mimes:jpg,jpeg,png,webp,gif,pdf,mp3,mp4,webm',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,audio/mpeg,video/mp4,video/webm',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
