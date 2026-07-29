<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\AgencyPortfolio;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * لوحة الوكالة: محفظة الأنشطة ودرجاتها واتجاهها.
 *
 * لا تأخذ مشروعًا في المسار: النطاق هو مساحة العمل كلها، وهي حاوية الملكية
 * (§٥.٢). وكالة تدير عشرة عملاء تفتح شاشة واحدة لا عشرًا.
 */
class PortfolioController extends Controller
{
    public function __construct(private readonly AgencyPortfolio $portfolio) {}

    public function __invoke(Request $request): View
    {
        return view('app.portfolio.index', [
            'portfolio' => $this->portfolio->for($request->user()->primaryWorkspace()),
        ]);
    }
}
