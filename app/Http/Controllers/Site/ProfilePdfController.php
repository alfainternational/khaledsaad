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
        $pdf = $this->engine->make(__('السيرة المهنية'), 25);

        $pdf->SetTitle(__('السيرة المهنية - خالد سعد'));
        $pdf->SetAuthor((string) $brand['name']);
        $pdf->SetSubject((string) $brand['professional_headline']);
        $pdf->WriteHTML($html);

        /*
         * لاحقة اللغة في اسم الملف تتبع لغته فعلًا.
         *
         * كانت `-ar` ثابتة، والقالب `site.pages.profile-pdf` قالب Blade
         * يمرّ بالمغلّف كغيره — أي أن ملفًّا إنجليزيًّا كان ينزل باسم
         * ينتهي بـ`-ar`. لاحقةٌ تكذب على من يرتّب ملفاته بلغتها.
         */
        $suffix = app()->getLocale();

        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Khaled-Saad-CV-'.$suffix.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
