<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\Shared\I18n\TranslatedConfig;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalController extends Controller
{
    public function __invoke(string $page): View
    {
        $content = config("legal.{$page}");

        if ($content === null) {
            throw new NotFoundHttpException;
        }

        return view('site.legal', [
            'brand' => TranslatedConfig::get('brand'),
            'page' => $content,
        ]);
    }
}
