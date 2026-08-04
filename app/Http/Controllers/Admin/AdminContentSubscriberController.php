<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminContentSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = ContentSubscriber::query()
            ->when($request->filled('q'), fn ($query) => $query->where('email', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('subscribed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.content.subscribers', compact('subscribers'));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['email', 'status', 'consented_at', 'subscribed_at']);

            ContentSubscriber::query()->orderBy('id')->chunk(500, function ($subscribers) use ($output): void {
                foreach ($subscribers as $subscriber) {
                    fputcsv($output, array_map($this->safeCsvCell(...), [
                        $subscriber->email,
                        $subscriber->status,
                        $subscriber->consented_at?->toAtomString(),
                        $subscriber->subscribed_at?->toAtomString(),
                    ]));
                }
            });

            fclose($output);
        }, 'content-subscribers-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateStatus(Request $request, ContentSubscriber $subscriber): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,disabled'],
        ]);

        $subscriber->update($data);

        return back()->with('success', 'تم تحديث حالة المشترك.');
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
