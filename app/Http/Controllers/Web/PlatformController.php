<?php

namespace App\Http\Controllers\Web;

use App\Domain\Marketing\Models\CmsPage;
use App\Http\Controllers\Controller;
use App\Support\PlatformSectionCatalog;
use Illuminate\Contracts\View\View;

class PlatformController extends Controller
{
    public function home(): View
    {
        return view('pages.home', PlatformSectionCatalog::home());
    }

    public function about(): View
    {
        $cms = CmsPage::publishedBySlug('about');

        return view('pages.about', [
            'cms' => $cms,
            'title' => $cms?->title ?? 'عن خالد سعد — المنصة الاستراتيجية',
            'description' => $cms?->meta_description ?? 'تعرّف على خالد سعد، مستشار التسويق الاستراتيجي ومؤسس المنصة، ورؤيته في جعل التسويق العربي أوضح وأقرب إلى التنفيذ.',
        ]);
    }

    public function show(string $page): View
    {
        if ($page === 'paths') {
            return view('pages.paths', PlatformSectionCatalog::paths());
        }

        return view('pages.section', PlatformSectionCatalog::section($page));
    }
}
