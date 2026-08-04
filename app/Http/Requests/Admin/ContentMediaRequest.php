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
                'max:'.config('content.media.max_kb', 262144),
                'mimes:jpg,jpeg,png,webp,gif,pdf,mp3,wav,m4a,ogg,mp4,webm,doc,docx,ppt,pptx,xls,xlsx,txt,csv,zip',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg,video/mp4,video/webm,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv,application/zip,application/x-zip-compressed',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'الحد الأقصى لحجم الصورة أو المرفق هو 256 ميجابايت.',
            'file.mimes' => 'نوع الملف غير مدعوم. استخدم صورة أو مستندًا أو ملفًا صوتيًا أو مرئيًا من الأنواع المتاحة.',
            'file.mimetypes' => 'تعذر التحقق من نوع الملف. اختر ملفًا أصليًا من الأنواع المدعومة.',
        ];
    }
}
