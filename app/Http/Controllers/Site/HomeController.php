<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Tools\ToolShowcase;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly ToolShowcase $showcase) {}

    public function __invoke(): View
    {
        return view('home', [
            'brand' => config('brand'),
            'tools' => $this->showcase->cards(limit: 8),
            'toolStats' => $this->showcase->stats(),
            'entryTool' => $this->showcase->entryTool(),
        ]);
    }
}
