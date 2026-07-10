<?php

namespace App\Http\Requests\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * دعم الموبايل: ترجمة item_public_id إلى item_id الداخلي ضمن مساحة العمل.
     * لا أثر على الويب (لا يرسل item_public_id).
     */
    protected function prepareForValidation(): void
    {
        $publicId = $this->input('item_public_id');
        if ($this->filled('item_id') || ! is_string($publicId) || $publicId === '') {
            return;
        }

        /** @var Workspace|null $workspace */
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
        if ($workspace === null) {
            return;
        }

        $itemId = match ($this->input('item_type')) {
            'tool_run' => ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $publicId)
                ->value('id'),
            'ai_generation' => AIGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->where('public_id', $publicId)
                ->value('id'),
            default => null,
        };

        if ($itemId !== null) {
            $this->merge(['item_id' => $itemId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', Rule::in(['tool_run', 'ai_generation', 'workspace_data'])],
            'item_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
