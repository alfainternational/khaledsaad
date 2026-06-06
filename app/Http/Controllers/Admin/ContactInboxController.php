<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Client\Models\Client;
use App\Domain\Marketing\Models\ContactMessage;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConvertContactMessageRequest;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactInboxController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $q = ContactMessage::query()
            ->with(['convertedWorkspace', 'convertedProject'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('message_type')) {
            $q->where('message_type', $request->string('message_type'));
        }

        return view('admin.contact-messages.index', [
            'messages' => $q->paginate(30)->withQueryString(),
            'statusFilter' => $request->string('status')->toString(),
            'typeFilter' => $request->string('message_type')->toString(),
            'statusOptions' => ContactMessage::statusOptions(),
            'typeOptions' => ContactMessage::typeOptions(),
        ]);
    }

    public function show(ContactMessage $contact_message): View
    {
        if ($contact_message->status === ContactMessage::STATUS_NEW) {
            $contact_message->update([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ]);
        }

        return view('admin.contact-messages.show', [
            'message' => $contact_message->fresh(['convertedWorkspace.account', 'convertedClient', 'convertedProject']),
            'statusOptions' => ContactMessage::statusOptions(),
            'typeOptions' => ContactMessage::typeOptions(),
            'workspaces' => Workspace::query()
                ->with('account')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, ContactMessage $contact_message, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContactMessage::statusOptions()))],
        ]);
        $contact_message->update($data);
        if ($data['status'] === ContactMessage::STATUS_READ && ! $contact_message->read_at) {
            $contact_message->update(['read_at' => now()]);
        }

        return back()->with('status', $flash->updated('الرسالة'));
    }

    public function convert(
        ConvertContactMessageRequest $request,
        ContactMessage $contact_message,
        ProjectMarketingBriefStore $briefStore,
    ): RedirectResponse {
        $data = $request->validated();
        $workspace = Workspace::query()->findOrFail($data['workspace_id']);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['client_name'] ?: data_get($contact_message->payload, 'contact.company_name') ?: $contact_message->name,
            'contact_info' => [
                'email' => $contact_message->email,
                'phone' => $contact_message->phone,
                'company' => data_get($contact_message->payload, 'contact.company_name'),
                'notes' => $contact_message->body,
            ],
            'status' => 'active',
        ]);

        $projectName = $data['project_name']
            ?: data_get($contact_message->payload, 'contact.company_name')
            ?: $contact_message->subject;

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => $projectName,
            'stage' => (int) ($data['project_stage'] ?? 2),
            'status' => 'active',
        ]);

        if ($contact_message->isConsultation()) {
            $briefStore->put($workspace, $project, $contact_message->toProjectBriefPayload());
        }

        $contact_message->update([
            'status' => ContactMessage::STATUS_CONVERTED,
            'read_at' => $contact_message->read_at ?: now(),
            'converted_workspace_id' => $workspace->id,
            'converted_client_id' => $client->id,
            'converted_project_id' => $project->id,
            'converted_at' => now(),
        ]);

        $this->auditLogger->record(
            action: 'admin.contact_message.converted',
            targetType: 'contact_message',
            targetId: $contact_message->id,
            actor: $request->user(),
            workspace: $workspace,
            meta: [
                'client_id' => $client->id,
                'project_id' => $project->id,
                'message_type' => $contact_message->message_type,
            ],
        );

        return redirect()
            ->route('admin.contact-messages.show', $contact_message)
            ->with('status', 'تم تحويل الطلب إلى عميل ومشروع وربط brief المشروع بهما.');
    }

    public function destroy(ContactMessage $contact_message, FlashMessageCatalog $flash): RedirectResponse
    {
        $contact_message->delete();

        return redirect()->route('admin.contact-messages.index')->with('status', $flash->deleted('الرسالة'));
    }
}
