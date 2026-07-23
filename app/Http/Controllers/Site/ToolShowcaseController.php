<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Services\Tools\ToolShowcase;
use Illuminate\View\View;

class ToolShowcaseController extends Controller
{
    public function __construct(private readonly ToolShowcase $showcase) {}

    public function index(): View
    {
        return view('site.tools.index', [
            'brand' => config('brand'),
            'tools' => $this->showcase->cards(),
            'toolStats' => $this->showcase->stats(),
        ]);
    }

    public function show(Tool $tool): View
    {
        return view('site.tools.show', [
            'brand' => config('brand'),
            'tool' => $this->showcase->detail($tool),
            'related' => collect($this->showcase->cards())
                ->reject(fn (array $card) => $card['key'] === $tool->key)
                ->take(3)
                ->values()
                ->all(),
        ]);
    }
}
