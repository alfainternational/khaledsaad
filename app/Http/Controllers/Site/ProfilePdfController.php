<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ProfilePdfController extends Controller
{
    public function __invoke(): Response
    {
        return Pdf::loadView('site.pages.profile-pdf', ['brand' => config('brand')])
            ->setPaper('a4')
            ->download('Khaled-Saad-CV-ar.pdf');
    }
}
