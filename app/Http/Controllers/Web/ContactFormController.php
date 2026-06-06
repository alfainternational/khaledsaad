<?php

namespace App\Http\Controllers\Web;

use App\Domain\Marketing\Models\ContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreContactMessageRequest;
use Illuminate\Http\RedirectResponse;

class ContactFormController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $messageType = (string) $data['message_type'];

        $subject = (string) ($data['subject'] ?? '');
        $body = (string) ($data['body'] ?? '');
        $payload = null;

        if ($messageType === ContactMessage::TYPE_CONSULTATION) {
            $payload = [
                'contact' => [
                    'company_name' => (string) ($data['company_name'] ?? ''),
                ],
                'business' => [
                    'summary' => (string) ($data['business_summary'] ?? ''),
                    'offer' => (string) ($data['offer'] ?? ''),
                    'market' => (string) ($data['market'] ?? ''),
                ],
                'audience' => [
                    'ideal_customer' => (string) ($data['ideal_customer'] ?? ''),
                    'pain_points' => (string) ($data['pain_points'] ?? ''),
                ],
                'goals' => [
                    'primary_goal' => (string) ($data['primary_goal'] ?? ''),
                    'success_metric' => (string) ($data['success_metric'] ?? ''),
                    'timeframe' => (string) ($data['timeframe'] ?? ''),
                ],
                'current_marketing' => [
                    'channels' => array_values(array_filter((array) ($data['current_channels'] ?? []))),
                    'current_state' => (string) ($data['current_state'] ?? ''),
                ],
                'execution' => [
                    'priority' => (string) ($data['priority'] ?? ''),
                ],
                'commercial' => [
                    'budget_range' => (string) ($data['budget_range'] ?? ''),
                ],
                'services' => array_values(array_filter((array) ($data['services'] ?? []))),
                'notes' => [
                    'additional_context' => (string) ($data['additional_context'] ?? ''),
                ],
            ];

            $subject = 'طلب استشارة مشروع: '.($data['company_name'] ?: $data['name']);
            $body = implode("\n", array_filter([
                'النشاط: '.($data['business_summary'] ?? ''),
                'العرض: '.($data['offer'] ?? ''),
                'الجمهور: '.($data['ideal_customer'] ?? ''),
                'الهدف: '.($data['primary_goal'] ?? ''),
                'الأولوية الحالية: '.($data['priority'] ?? ''),
                isset($data['budget_range']) && $data['budget_range'] !== '' ? 'الميزانية: '.$data['budget_range'] : null,
                isset($data['timeframe']) && $data['timeframe'] !== '' ? 'الإطار الزمني: '.$data['timeframe'] : null,
                isset($data['additional_context']) && $data['additional_context'] !== '' ? 'ملاحظات إضافية: '.$data['additional_context'] : null,
            ]));
        }

        ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => $subject,
            'body' => $body,
            'message_type' => $messageType,
            'source' => 'website',
            'payload' => $payload,
            'status' => ContactMessage::STATUS_NEW,
        ]);

        $statusMessage = $messageType === ContactMessage::TYPE_CONSULTATION
            ? 'تم استلام ملف الاستشارة الأولي. سنراجعه ونعود إليك برد عملي قريباً.'
            : 'تم إرسال رسالتك بنجاح. سنعود إليك قريباً.';

        return back()->with('status', $statusMessage);
    }
}
