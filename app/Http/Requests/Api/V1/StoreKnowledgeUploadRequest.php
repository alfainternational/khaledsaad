<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $maxKilobytes = max(1, (int) ceil((int) config('services.knowledge.upload_max_bytes', 8388608) / 1024));

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimetypes:text/plain,text/markdown,text/csv,application/csv,application/json,text/json,text/html,application/xhtml+xml,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/png,image/jpeg,image/webp,image/tiff',
            ],
        ];
    }
}
