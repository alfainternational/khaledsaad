<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PlatformSectionCatalog;
use Illuminate\Contracts\View\View;

class PlatformController extends Controller
{
    public function home(): View
    {
        return view('pages.home', PlatformSectionCatalog::home());
    }

    public function show(string $page): View
    {
        if ($page === 'paths') {
            return view('pages.paths', PlatformSectionCatalog::paths());
        }

        return view('pages.section', PlatformSectionCatalog::section($page));
    }
}
