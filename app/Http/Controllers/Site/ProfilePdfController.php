<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\ArabicPdfEngine;
use Symfony\Component\HttpFoundation\Response;

class ProfilePdfController extends Controller
{
    public function __construct(private readonly ArabicPdfEngine $engine) {}

    public function __invoke(): Response
    {
        $brand = config('brand');
        $html = view('site.pages.profile-pdf', compact('brand'))->render();
        $pdf = $this->engine->make('السيرة المهنية', 25);

        $pdf->SetTitle('السيرة المهنية - خالد سعد');
        $pdf->SetAuthor((string) $brand['name']);
        $pdf->SetSubject((string) $brand['professional_headline']);
        $pdf->WriteHTML($html);

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Khaled-Saad-CV-ar.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
